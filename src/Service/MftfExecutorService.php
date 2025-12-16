<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Executes MFTF tests via Magento container.
 */
class MftfExecutorService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly GlobalEnvVariableRepository $globalEnvVariableRepository,
        private readonly string $projectDir,
        private readonly string $seleniumHost,
        private readonly int $seleniumPort,
        private readonly string $magentoRoot,
        private readonly string $magentoContainer,
        private readonly string $testModulePath = 'app/code/SiiPoland/Catalog',
    ) {
    }

    /**
     * Execute MFTF tests for a test run.
     * Output is streamed to a file to prevent memory bloat on long-running tests.
     *
     * @return array{output: string, exitCode: int}
     */
    public function execute(TestRun $run): array
    {
        $this->logger->info('Executing MFTF tests', [
            'runId' => $run->getId(),
            'filter' => $run->getTestFilter(),
        ]);

        // Build the MFTF command
        $mftfCommand = $this->buildCommand($run);

        // Create output file for streaming (prevents memory bloat)
        $outputFile = $this->getOutputFilePath($run);
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Store output path on run for async tracking
        $run->setOutputFilePath($outputFile);

        // Execute via docker
        $process = new Process([
            'docker', 'exec',
            $this->magentoContainer,
            'bash', '-c',
            $mftfCommand,
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

        $this->logger->info('MFTF execution completed', [
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
        return $this->projectDir . '/var/test-output/mftf-run-' . $run->getId() . '.log';
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
        $content = "... [truncated - showing last " . round($maxBytes / 1024) . "KB]\n" . fread($handle, $maxBytes);
        fclose($handle);

        return $content;
    }

    /**
     * Build MFTF command string.
     */
    public function buildCommand(TestRun $run): string
    {
        $filter = $run->getTestFilter();
        if (empty($filter)) {
            throw new \InvalidArgumentException('MFTF requires a test filter (test name or group). Please specify --filter option.');
        }

        $runId = $run->getId();
        $parts = [];

        // Create symlink from run directory to correct module path
        // /var/www/html/app/code/Test/run-X -> /var/www/html/app/code/SiiPoland/Catalog
        $modulePath = $this->magentoRoot . '/' . $this->testModulePath;
        $runPath = $this->magentoRoot . '/app/code/Test/run-' . $runId;

        // Create parent directory and symlink
        $parts[] = sprintf('mkdir -p %s', escapeshellarg(dirname($modulePath)));
        $parts[] = sprintf('rm -rf %s', escapeshellarg($modulePath)); // Remove existing
        $parts[] = sprintf('ln -sf %s %s', escapeshellarg($runPath), escapeshellarg($modulePath));

        // Change to acceptance test directory
        $acceptanceDir = $this->magentoRoot . '/dev/tests/acceptance';
        $parts[] = 'cd ' . $acceptanceDir;

        // Build .env file with layered configuration:
        // 1. Global variables (shared across all environments)
        // 2. Module's Cron/data/.env.{env-name} (test-specific data, ~200 vars)
        // 3. TestEnvironment entity values (can override)
        // 4. Infrastructure overrides (Selenium)
        $env = $run->getEnvironment();
        $envFileName = '.env.' . $env->getName(); // e.g., .env.stage-us
        $moduleEnvFile = $runPath . '/Cron/data/' . $envFileName;
        $mftfEnvFile = $acceptanceDir . '/.env';

        // Layer 1: Start with global variables from database
        $globalVars = $this->globalEnvVariableRepository->getAllAsKeyValue();
        if (!empty($globalVars)) {
            $globalContent = "# Global variables (from ATR database)\n";
            foreach ($globalVars as $key => $value) {
                $quotedValue = $this->quoteEnvValue($value);
                $globalContent .= sprintf("%s=%s\n", $key, $quotedValue);
            }
            $parts[] = sprintf('echo %s > %s', escapeshellarg($globalContent), escapeshellarg($mftfEnvFile));
            $parts[] = sprintf('cat %s >> %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        } else {
            $parts[] = sprintf('cp %s %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        }

        // Layer 3: Override with TestEnvironment values if present
        $envVars = $env->getEnvVariables();
        foreach ($envVars as $key => $value) {
            // Quote values with spaces/special chars for .env compatibility
            $quotedValue = $this->quoteEnvValue($value);
            $parts[] = sprintf('echo %s >> %s', escapeshellarg("{$key}={$quotedValue}"), escapeshellarg($mftfEnvFile));
        }

        // Layer 4: Override Selenium configuration (ATR infra)
        $parts[] = sprintf('echo "SELENIUM_HOST=%s" >> %s', $this->seleniumHost, escapeshellarg($mftfEnvFile));
        $parts[] = sprintf('echo "SELENIUM_PORT=%d" >> %s', $this->seleniumPort, escapeshellarg($mftfEnvFile));

        // Generate credentials file from env variables (MFTF requires this for _CREDS references)
        $credentialsFile = $acceptanceDir . '/.credentials';
        $parts[] = $this->buildCredentialsCommand($mftfEnvFile, $credentialsFile);

        // Generate tests from module XML (required for MFTF to discover tests)
        // MFTF expects JSON format for --tests: {"tests":["TEST_NAME1","TEST_NAME2"]}
        $mftfBin = $this->magentoRoot . '/vendor/bin/mftf';
        $testsJson = json_encode(['tests' => explode(' ', $filter)]);
        // Use ; instead of && since generate may have non-fatal annotation warnings
        $parts[] = sprintf('%s generate:tests --tests %s --force', $mftfBin, escapeshellarg($testsJson));

        // Build MFTF run command
        $mftfParts = [$mftfBin . ' run:test'];
        $mftfParts[] = escapeshellarg($filter);
        $mftfParts[] = '-fr'; // failed rerun flag

        $parts[] = implode(' ', $mftfParts);

        return implode(' && ', $parts);
    }

    /**
     * Parse MFTF output to extract test results.
     *
     * Handles Codeception output format where test results appear as:
     * - Test header: "MOEC5157Cest: Moec5157"
     * - Test steps
     * - Result: "PASSED" or "FAIL" on its own line
     *
     * @return TestResult[]
     */
    public function parseResults(TestRun $run, string $output): array
    {
        $results = [];

        // Strip ANSI escape codes for clean parsing
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

        // Pattern to match test blocks in Codeception output
        // Format: "TestCest: MethodName" ... (steps) ... "PASSED/FAIL"
        $pattern = '/^([A-Z][A-Za-z0-9]+Cest):\s*(\w+).*?^\s*(PASSED|FAIL|ERROR|SKIP)\s*$/ms';

        if (preg_match_all($pattern, $cleanOutput, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = new TestResult();
                $result->setTestRun($run);
                $result->setTestName($match[1] . ':' . $match[2]); // e.g., "MOEC5157Cest:Moec5157"
                $result->setStatus($this->mapStatus($match[3]));

                // Extract test ID from Cest name (e.g., MOEC5157Cest -> MOEC5157)
                if (preg_match('/^([A-Z]+\d+)/', $match[1], $idMatch)) {
                    $result->setTestId($idMatch[1]);
                }

                $results[] = $result;
            }
        }

        // Fallback: Parse from Signature lines if block matching fails
        if (empty($results)) {
            $results = $this->parseResultsFallback($run, $cleanOutput);
        }

        // Extract duration from timing line and apply to results
        if (preg_match('/Time:\s*([0-9:.]+)/', $cleanOutput, $timeMatch)) {
            $this->applyDurationToResults($results, $timeMatch[1]);
        }

        return $results;
    }

    /**
     * Get path to MFTF output directory.
     */
    public function getOutputPath(): string
    {
        return $this->projectDir . '/var/mftf-results';
    }

    /**
     * Get path to Allure results from MFTF.
     */
    public function getAllureResultsPath(): string
    {
        return $this->projectDir . '/var/mftf-results/allure-results';
    }

    /**
     * Fallback parser when block matching fails.
     * Uses Signature lines to identify tests and OK/FAILURES summary for status.
     *
     * @return TestResult[]
     */
    private function parseResultsFallback(TestRun $run, string $output): array
    {
        $results = [];

        // Look for Signature lines to get test names
        $testNames = [];
        if (preg_match_all('/Signature:\s*[^\s]+\\\\([A-Z][A-Za-z0-9]+Cest):(\w+)/m', $output, $sigMatches, PREG_SET_ORDER)) {
            foreach ($sigMatches as $m) {
                $testNames[] = ['cest' => $m[1], 'method' => $m[2]];
            }
        }

        // Count results from summary
        $passedCount = 0;
        $failedCount = 0;

        // Check for OK summary: "OK (1 test, 7 assertions)"
        if (preg_match('/OK\s*\((\d+)\s*test/', $output, $okMatch)) {
            $passedCount = (int) $okMatch[1];
        }
        // Check for failures summary: "Tests: 5, Failures: 2"
        if (preg_match('/Tests:\s*(\d+).*?Failures:\s*(\d+)/s', $output, $failMatch)) {
            $failedCount = (int) $failMatch[2];
            $passedCount = (int) $failMatch[1] - $failedCount;
        }

        // Create results from parsed data
        foreach ($testNames as $i => $test) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName($test['cest'] . ':' . $test['method']);

            // Determine status based on position vs counts
            $status = ($i < $passedCount) ? TestResult::STATUS_PASSED : TestResult::STATUS_FAILED;
            $result->setStatus($status);

            if (preg_match('/^([A-Z]+\d+)/', $test['cest'], $idMatch)) {
                $result->setTestId($idMatch[1]);
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Apply duration from timing line to results.
     * Distributes total time evenly if multiple tests.
     *
     * @param TestResult[] $results
     */
    private function applyDurationToResults(array $results, string $timeStr): void
    {
        // Parse time format "MM:SS.mmm" or "SS.mmm"
        $parts = explode(':', $timeStr);
        $seconds = 0.0;
        if (count($parts) === 2) {
            $seconds = (int) $parts[0] * 60 + (float) $parts[1];
        } else {
            $seconds = (float) $parts[0];
        }

        // Distribute evenly if multiple tests
        $perTest = count($results) > 0 ? $seconds / count($results) : 0;
        foreach ($results as $result) {
            $result->setDuration($perTest);
        }
    }

    /**
     * Build command to generate MFTF credentials file from env variables.
     *
     * MFTF uses _CREDS suffix in tests to reference credentials from .credentials file.
     * This extracts relevant variables from .env and formats them for MFTF.
     */
    private function buildCredentialsCommand(string $envFile, string $credentialsFile): string
    {
        // Extract password/secret variables from .env and format for .credentials
        // Format: magento/VAR_NAME=value
        $script = <<<'BASH'
            (
                echo "# MFTF credentials auto-generated from .env"
                echo "magento/tfa/OTP_SHARED_SECRET=ABCDEFGHIJKLMNOP"
                
                # Extract all PASSWORD, SECRET, KEY variables from .env
                grep -E "^(MAGENTO_ADMIN|MAGENTO_TEST|.*PASSWORD|.*SECRET|.*KEY)=" "$1" 2>/dev/null | while IFS='=' read -r key value; do
                    # Remove quotes from value
                    value=$(echo "$value" | sed "s/^['\"]//;s/['\"]$//")
                    echo "magento/${key}=${value}"
                done
            ) > "$2"
            BASH;

        return sprintf(
            'bash -c %s -- %s %s',
            escapeshellarg($script),
            escapeshellarg($envFile),
            escapeshellarg($credentialsFile),
        );
    }

    private function mapStatus(string $codeceptStatus): string
    {
        return match (strtoupper($codeceptStatus)) {
            'PASSED' => TestResult::STATUS_PASSED,
            'FAIL' => TestResult::STATUS_FAILED,
            'ERROR' => TestResult::STATUS_BROKEN,
            'SKIP' => TestResult::STATUS_SKIPPED,
            default => TestResult::STATUS_BROKEN,
        };
    }

    /**
     * Quote env value if it contains spaces or special characters.
     */
    private function quoteEnvValue(string $value): string
    {
        // Already quoted
        if (preg_match('/^([\'"]).*\1$/', $value)) {
            return $value;
        }

        // Needs quoting (spaces, special chars, or empty)
        if ($value === '' || preg_match('/[\s$"\'\\\\#]/', $value)) {
            // Use single quotes and escape existing single quotes
            return "'" . str_replace("'", "'\\''", $value) . "'";
        }

        return $value;
    }
}
