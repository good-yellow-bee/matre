<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorIds;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\Security\ShellEscapeService;
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
        private readonly ShellEscapeService $shellEscapeService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Execute Playwright tests for a test run.
     * Output is streamed to a file to prevent memory bloat on long-running tests.
     *
     * @param callable|null $outputCallback Optional callback for real-time output streaming
     * @param callable|null $heartbeatCallback Optional callback to extend message redelivery window
     *
     * @return array{output: string, exitCode: int}
     */
    public function execute(TestRun $run, ?callable $outputCallback = null, ?callable $heartbeatCallback = null): array
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

        // Stream output to file and optionally to callback
        $handle = fopen($outputFile, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Failed to open output file for writing: %s', $outputFile));
        }
        $process->start(function ($type, $buffer) use ($handle, $outputCallback) {
            fwrite($handle, $buffer);
            if (null !== $outputCallback) {
                $outputCallback($buffer);
            }
        });

        // Poll for completion with heartbeat support
        $lastHeartbeat = time();
        $heartbeatFailures = 0;
        $heartbeatInterval = 300; // Heartbeat every 5 minutes

        while ($process->isRunning()) {
            usleep(500000); // Check every 500ms

            // Heartbeat: extend message redelivery window during long-running tests
            if ($heartbeatCallback && (time() - $lastHeartbeat) >= $heartbeatInterval) {
                try {
                    $heartbeatCallback();
                    $lastHeartbeat = time();
                    $heartbeatFailures = 0;
                } catch (\Throwable $e) {
                    ++$heartbeatFailures;
                    if ($heartbeatFailures >= 3) {
                        $this->logger->error('Persistent heartbeat failure - message may be redelivered', [
                            'runId' => $run->getId(),
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $heartbeatFailures,
                        ]);
                    } else {
                        $this->logger->warning('Heartbeat failed', [
                            'runId' => $run->getId(),
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $heartbeatFailures,
                        ]);
                    }
                }
            }
        }
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
     *
     * SECURITY: All environment variable names and values are validated and escaped
     * to prevent command injection attacks.
     */
    public function buildCommand(TestRun $run): string
    {
        $parts = ['cd /app/modules'];

        // Layer 1: Export global + environment-specific variables from database
        // SECURITY: Validate and escape all variables to prevent command injection
        $env = $run->getEnvironment();
        $globalVars = $this->globalEnvVariableRepository->getAllAsKeyValue($env->getCode());
        foreach ($globalVars as $key => $value) {
            try {
                $parts[] = $this->shellEscapeService->buildExportStatement($key, $value);
            } catch (\InvalidArgumentException $e) {
                $this->logger->error('Invalid environment variable detected', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'runId' => $run->getId(),
                    'errorId' => ErrorIds::PLAYWRIGHT_ENV_VAR_INVALID,
                ]);

                throw new \RuntimeException(sprintf('Test run aborted: invalid environment variable "%s": %s', $key, $e->getMessage()));
            }
        }

        // Layer 2: Set TestEnvironment core variables (override globals)
        // SECURITY: Use secure export building for all values
        $parts[] = $this->shellEscapeService->buildExportStatement('BASE_URL', $env->getBaseUrl());

        if ($env->getAdminUsername()) {
            $parts[] = $this->shellEscapeService->buildExportStatement('ADMIN_USERNAME', $env->getAdminUsername());
        }
        if ($env->getAdminPassword()) {
            $parts[] = $this->shellEscapeService->buildExportStatement('ADMIN_PASSWORD', $env->getAdminPassword());
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
        if (false === $size || $size <= $maxBytes) {
            $content = @file_get_contents($path);
            if (false === $content) {
                $this->logger->error('Failed to read Playwright output file', ['path' => $path]);

                return '';
            }

            return $content;
        }

        // Read last N bytes for large files
        $handle = fopen($path, 'r');
        if (false === $handle) {
            $this->logger->error('Failed to open output file for reading', ['path' => $path]);

            return '';
        }
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

        $contents = @file_get_contents($jsonPath);
        if (false === $contents) {
            $this->logger->error('Failed to read Playwright JSON results file', ['path' => $jsonPath]);

            return [];
        }

        $data = json_decode($contents, true);
        if (!is_array($data) || !isset($data['suites'])) {
            $this->logger->warning('Invalid Playwright JSON results format', ['path' => $jsonPath]);

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
