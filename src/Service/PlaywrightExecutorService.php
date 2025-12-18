<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Executes Playwright tests via Playwright container.
 */
class PlaywrightExecutorService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly GlobalEnvVariableRepository $globalEnvVariableRepository,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Execute Playwright tests for a test run.
     * Output is streamed to a file to prevent memory bloat on long-running tests.
     *
     * @return array{output: string, exitCode: int}
     */
    public function execute(TestRun $run): array
    {
        $this->logger->info('Executing Playwright tests', [
            'runId' => $run->getId(),
            'filter' => $run->getTestFilter(),
        ]);

        // Build the Playwright command
        $playwrightCommand = $this->buildCommand($run);

        // Create output file for streaming (prevents memory bloat)
        $outputFile = $this->getOutputFilePath($run);
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o755, true);
        }

        // Execute via docker
        $process = new Process([
            'docker', 'exec',
            'atr_playwright',
            'bash', '-c',
            $playwrightCommand,
        ]);
        $process->setTimeout(3600); // 1 hour timeout

        // Stream output to file instead of buffering in memory
        $handle = fopen($outputFile, 'w');
        $process->run(function ($type, $buffer) use ($handle) {
            fwrite($handle, $buffer);
        });
        fclose($handle);

        // Read final output (truncated for entity storage)
        $output = $this->readOutputFile($outputFile);

        $this->logger->info('Playwright execution completed', [
            'runId' => $run->getId(),
            'exitCode' => $process->getExitCode(),
            'outputFile' => $outputFile,
        ]);

        return [
            'output' => $output,
            'exitCode' => $process->getExitCode() ?? 1,
        ];
    }

    /**
     * Get output file path for a test run.
     */
    public function getOutputFilePath(TestRun $run): string
    {
        return $this->projectDir . '/var/test-output/playwright-run-' . $run->getId() . '.log';
    }

    /**
     * Build Playwright command string.
     */
    public function buildCommand(TestRun $run): string
    {
        $parts = ['cd /app/modules'];

        // Layer 1: Export global + environment-specific variables from database
        $env = $run->getEnvironment();
        $globalVars = $this->globalEnvVariableRepository->getAllAsKeyValue($env->getName());
        foreach ($globalVars as $key => $value) {
            $parts[] = sprintf('export %s="%s"', $key, $value);
        }

        // Layer 2: Set TestEnvironment core variables (override globals)
        $parts[] = sprintf('export BASE_URL="%s"', $env->getBaseUrl());

        if ($env->getAdminUsername()) {
            $parts[] = sprintf('export ADMIN_USERNAME="%s"', $env->getAdminUsername());
        }
        if ($env->getAdminPassword()) {
            $parts[] = sprintf('export ADMIN_PASSWORD="%s"', $env->getAdminPassword());
        }

        // Build Playwright command
        $playwrightParts = ['npx', 'playwright', 'test'];

        $filter = $run->getTestFilter();
        if ($filter) {
            $playwrightParts[] = '--grep';
            $playwrightParts[] = escapeshellarg($filter);
        }

        // Output to Allure format
        $playwrightParts[] = '--reporter=allure-playwright';
        $playwrightParts[] = '--output=/app/results';

        $parts[] = implode(' ', $playwrightParts);

        return implode(' && ', $parts);
    }

    /**
     * Parse Playwright output to extract test results.
     *
     * @return TestResult[]
     */
    public function parseResults(TestRun $run, string $output): array
    {
        $results = [];

        // Parse Playwright output format
        // Example: "✓ test name (1.2s)"
        // Example: "✘ test name (500ms)"
        preg_match_all(
            '/^\s*(✓|✘|○|-)\s+(.+?)\s+\(([0-9.]+(?:ms|s))\)/m',
            $output,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName($match[2]);
            $result->setStatus($this->mapStatus($match[1]));
            $result->setDuration($this->parseDuration($match[3]));

            $results[] = $result;
        }

        // Also try JSON reporter format if available
        $jsonResults = $this->parseJsonResults($run);
        if (!empty($jsonResults)) {
            return $jsonResults;
        }

        return $results;
    }

    /**
     * Get path to Playwright output directory.
     */
    public function getOutputPath(): string
    {
        return $this->projectDir . '/var/playwright-results';
    }

    /**
     * Get path to Allure results from Playwright.
     */
    public function getAllureResultsPath(): string
    {
        return $this->projectDir . '/var/playwright-results/allure-results';
    }

    /**
     * Read output file content (truncated to prevent memory issues).
     */
    private function readOutputFile(string $path, int $maxBytes = 102400): string
    {
        if (!file_exists($path)) {
            return '';
        }

        $size = filesize($path);
        if ($size <= $maxBytes) {
            return file_get_contents($path);
        }

        // Read last N bytes for large files
        $handle = fopen($path, 'r');
        fseek($handle, -$maxBytes, SEEK_END);
        $content = '... [truncated - showing last ' . round($maxBytes / 1024) . "KB]\n" . fread($handle, $maxBytes);
        fclose($handle);

        return $content;
    }

    /**
     * Parse JSON results file if available.
     *
     * @return TestResult[]
     */
    private function parseJsonResults(TestRun $run): array
    {
        $jsonPath = $this->getOutputPath() . '/results.json';
        if (!file_exists($jsonPath)) {
            return [];
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($data) || !isset($data['suites'])) {
            return [];
        }

        $results = [];
        foreach ($data['suites'] as $suite) {
            $results = array_merge($results, $this->extractTestsFromSuite($run, $suite));
        }

        return $results;
    }

    /**
     * @return TestResult[]
     */
    private function extractTestsFromSuite(TestRun $run, array $suite): array
    {
        $results = [];

        foreach ($suite['specs'] ?? [] as $spec) {
            foreach ($spec['tests'] ?? [] as $test) {
                $result = new TestResult();
                $result->setTestRun($run);
                $result->setTestName($spec['title'] ?? 'Unknown');
                $result->setStatus($this->mapJsonStatus($test['status'] ?? 'unknown'));
                $result->setDuration(($test['results'][0]['duration'] ?? 0) / 1000);

                if (!empty($test['results'][0]['error']['message'])) {
                    $result->setErrorMessage($test['results'][0]['error']['message']);
                }

                $results[] = $result;
            }
        }

        // Process nested suites
        foreach ($suite['suites'] ?? [] as $nestedSuite) {
            $results = array_merge($results, $this->extractTestsFromSuite($run, $nestedSuite));
        }

        return $results;
    }

    private function mapStatus(string $symbol): string
    {
        return match ($symbol) {
            '✓' => TestResult::STATUS_PASSED,
            '✘' => TestResult::STATUS_FAILED,
            '○' => TestResult::STATUS_SKIPPED,
            '-' => TestResult::STATUS_SKIPPED,
            default => TestResult::STATUS_BROKEN,
        };
    }

    private function mapJsonStatus(string $status): string
    {
        return match (strtolower($status)) {
            'passed', 'expected' => TestResult::STATUS_PASSED,
            'failed', 'unexpected' => TestResult::STATUS_FAILED,
            'skipped' => TestResult::STATUS_SKIPPED,
            default => TestResult::STATUS_BROKEN,
        };
    }

    private function parseDuration(string $duration): float
    {
        if (str_ends_with($duration, 'ms')) {
            return ((float) rtrim($duration, 'ms')) / 1000;
        }

        return (float) rtrim($duration, 's');
    }
}
