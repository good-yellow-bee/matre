<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorIds;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\Security\ShellEscapeService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly ShellEscapeService $shellEscapeService,
        private readonly MagentoContainerPoolService $containerPool,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
        private readonly string $seleniumHost,
        private readonly int $seleniumPort,
        private readonly string $magentoRoot,
        private readonly string $testModulePath = 'app/code/SiiPoland/Catalog',
        private readonly int $webDriverConnectionTimeout = 30,
        private readonly int $webDriverRequestTimeout = 120,
        private readonly int $webDriverPageLoadTimeout = 60,
        private readonly int $webDriverWaitTimeout = 90,
    ) {
    }

    /**
     * Stop a running MFTF process inside the Magento container for this run.
     */
    public function stopRun(TestRun $run): void
    {
        $this->terminateRunProcess($run);
    }

    /**
     * Execute MFTF tests for a test run.
     * Output is streamed to a file to prevent memory bloat on long-running tests.
     *
     * @param callable|null $lockRefreshCallback Optional callback to refresh environment lock during execution
     * @param callable|null $heartbeatCallback   Optional callback to extend message redelivery window
     *
     * @return array{output: string, exitCode: int}
     */
    public function execute(TestRun $run, ?callable $lockRefreshCallback = null, ?callable $heartbeatCallback = null): array
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
            mkdir($outputDir, 0o755, true);
        }

        // Store output path on run for async tracking
        $run->setOutputFilePath($outputFile);

        // Get per-environment container (enables parallel execution)
        $container = $this->containerPool->getContainerForEnvironment($run->getEnvironment());

        // Execute via docker
        // Force ANSI color output for better styled logs
        $process = new Process([
            'docker', 'exec',
            '-e', 'TERM=xterm-256color',
            $container,
            'bash', '-c',
            $mftfCommand,
        ]);
        $process->setTimeout(3600); // 1 hour timeout

        // Stream output to file with cancellation and lock refresh support
        $handle = fopen($outputFile, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Failed to open output file for writing: %s', $outputFile));
        }
        $process->start(function ($type, $buffer) use ($handle) {
            fwrite($handle, $buffer);
        });

        // Poll for completion with cancellation check, lock refresh, and heartbeat
        $lastRefresh = time();
        $lastHeartbeat = time();
        $lockRefreshFailures = 0;
        $heartbeatFailures = 0;
        $refreshInterval = 30; // Refresh lock every 30 seconds
        $heartbeatInterval = 300; // Heartbeat every 5 minutes

        while ($process->isRunning()) {
            usleep(500000); // Check every 500ms
            $this->checkCancellation($run, $process);

            // Refresh environment lock to prevent expiration during long-running tests
            if ($lockRefreshCallback && (time() - $lastRefresh) >= $refreshInterval) {
                try {
                    $lockRefreshCallback();
                    $lastRefresh = time();
                    $lockRefreshFailures = 0;
                } catch (\Throwable $e) {
                    ++$lockRefreshFailures;
                    if ($lockRefreshFailures >= 2) {
                        $this->logger->error('Aborting test run - lock refresh persistently failing', [
                            'runId' => $run->getId(),
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $lockRefreshFailures,
                        ]);
                        $process->stop();
                        fclose($handle);

                        throw new \RuntimeException('Lock refresh failed - aborting to prevent parallel execution conflicts');
                    }
                    $this->logger->warning('Lock refresh failed', [
                        'runId' => $run->getId(),
                        'error' => $e->getMessage(),
                        'consecutiveFailures' => $lockRefreshFailures,
                    ]);
                }
            }

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
     * Execute a single MFTF test with dedicated output file.
     *
     * Used for sequential group execution where each test gets its own output.
     *
     * @param callable|null $lockRefreshCallback Optional callback to refresh environment lock during execution
     * @param callable|null $heartbeatCallback   Optional callback to extend message redelivery window
     *
     * @return array{output: string, exitCode: int, outputFilePath: string}
     */
    public function executeSingleTest(TestRun $run, string $testName, ?callable $lockRefreshCallback = null, ?callable $heartbeatCallback = null): array
    {
        $this->logger->info('Executing single MFTF test', [
            'runId' => $run->getId(),
            'testName' => $testName,
        ]);

        // Use full testName for unique file (avoid collisions)
        $safeFileName = $this->shellEscapeService->sanitizeFilename($testName);
        $outputFile = sprintf(
            '%s/var/test-output/run-%d/%s.log',
            $this->projectDir,
            $run->getId(),
            $safeFileName,
        );

        // Ensure directory exists
        $dir = dirname($outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        // Build command with test name override
        $mftfCommand = $this->buildCommand($run, $testName);

        // Get per-environment container (enables parallel execution)
        $container = $this->containerPool->getContainerForEnvironment($run->getEnvironment());

        // Execute via Docker (same pattern as execute())
        $process = new Process([
            'docker', 'exec',
            '-e', 'TERM=xterm-256color',
            $container,
            'bash', '-c',
            $mftfCommand,
        ]);
        $process->setTimeout(3600); // 1 hour timeout per test

        // Stream to per-test file with cancellation and lock refresh support
        $handle = fopen($outputFile, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Failed to open output file for writing: %s', $outputFile));
        }
        $process->start(function ($type, $buffer) use ($handle) {
            fwrite($handle, $buffer);
        });

        // Poll for completion with cancellation check, lock refresh, and heartbeat
        $lastRefresh = time();
        $lastHeartbeat = time();
        $lockRefreshFailures = 0;
        $heartbeatFailures = 0;
        $refreshInterval = 30; // Refresh lock every 30 seconds
        $heartbeatInterval = 300; // Heartbeat every 5 minutes

        while ($process->isRunning()) {
            usleep(500000); // Check every 500ms
            $this->checkCancellation($run, $process);

            // Refresh environment lock to prevent expiration during long-running tests
            if ($lockRefreshCallback && (time() - $lastRefresh) >= $refreshInterval) {
                try {
                    $lockRefreshCallback();
                    $lastRefresh = time();
                    $lockRefreshFailures = 0;
                } catch (\Throwable $e) {
                    ++$lockRefreshFailures;
                    if ($lockRefreshFailures >= 2) {
                        $this->logger->error('Aborting test run - lock refresh persistently failing', [
                            'runId' => $run->getId(),
                            'testName' => $testName,
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $lockRefreshFailures,
                        ]);
                        $process->stop();
                        fclose($handle);

                        throw new \RuntimeException('Lock refresh failed - aborting to prevent parallel execution conflicts');
                    }
                    $this->logger->warning('Lock refresh failed', [
                        'runId' => $run->getId(),
                        'testName' => $testName,
                        'error' => $e->getMessage(),
                        'consecutiveFailures' => $lockRefreshFailures,
                    ]);
                }
            }

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
                            'testName' => $testName,
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $heartbeatFailures,
                        ]);
                    } else {
                        $this->logger->warning('Heartbeat failed', [
                            'runId' => $run->getId(),
                            'testName' => $testName,
                            'error' => $e->getMessage(),
                            'consecutiveFailures' => $heartbeatFailures,
                        ]);
                    }
                }
            }
        }
        fclose($handle);

        $this->logger->info('Single test execution completed', [
            'runId' => $run->getId(),
            'testName' => $testName,
            'exitCode' => $process->getExitCode(),
            'outputFile' => $outputFile,
        ]);

        return [
            'output' => $this->readOutputFile($outputFile),
            'exitCode' => $process->getExitCode() ?? 1,
            'outputFilePath' => $outputFile,
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
     * Build MFTF command string.
     *
     * SECURITY: All environment variable names and values are validated and escaped
     * to prevent command injection attacks.
     */
    public function buildCommand(TestRun $run, ?string $testNameOverride = null): string
    {
        // Use override for sequential execution, otherwise from run
        $filter = $testNameOverride ?? $run->getTestFilter();
        if (empty($filter)) {
            throw new \InvalidArgumentException('MFTF requires a test filter (test name or group). Please specify --filter option.');
        }

        $runId = $run->getId();
        $parts = [];

        // Create symlink from mounted TestModule to expected Magento path
        // Docker mounts var/test-modules/current -> /var/www/html/app/code/TestModule
        // We symlink TestModule -> app/code/SiiPoland/Catalog (as defined by TEST_MODULE_PATH)
        $modulePath = $this->magentoRoot . '/' . $this->testModulePath;
        $mountedModulePath = $this->magentoRoot . '/app/code/TestModule';

        // Create parent directory and symlink
        $parts[] = sprintf('mkdir -p %s', escapeshellarg(dirname($modulePath)));
        $parts[] = sprintf('rm -rf %s', escapeshellarg($modulePath)); // Remove existing
        $parts[] = sprintf('ln -sf %s %s', escapeshellarg($mountedModulePath), escapeshellarg($modulePath));

        // Change to acceptance test directory
        $acceptanceDir = $this->magentoRoot . '/dev/tests/acceptance';
        $parts[] = 'cd ' . escapeshellarg($acceptanceDir);

        // Ensure WebDriver timeouts are applied in codeception.yml (per-run, safe defaults)
        $codeceptionPatch = <<<'PHP'
            $path = getcwd() . '/codeception.yml';
            if (!is_file($path)) {
                return;
            }
            $autoload = getcwd() . '/../../vendor/autoload.php';
            if (!is_file($autoload)) {
                return;
            }
            require $autoload;
            if (!class_exists('Symfony\Component\Yaml\Yaml')) {
                return;
            }
            $config = Symfony\Component\Yaml\Yaml::parseFile($path);
            if (!is_array($config)) {
                $config = [];
            }
            if (!isset($config['modules']) || !is_array($config['modules'])) {
                $config['modules'] = [];
            }
            if (!isset($config['modules']['config']) || !is_array($config['modules']['config'])) {
                $config['modules']['config'] = [];
            }
            if (!isset($config['modules']['config']['WebDriver']) || !is_array($config['modules']['config']['WebDriver'])) {
                $config['modules']['config']['WebDriver'] = [];
            }
            $wd = &$config['modules']['config']['WebDriver'];
            $wd['connection_timeout'] = %d;
            $wd['request_timeout'] = %d;
            $wd['pageload_timeout'] = %d;
            $wd['wait'] = %d;
            file_put_contents($path, Symfony\Component\Yaml\Yaml::dump($config, 10, 2));
            PHP;
        $parts[] = sprintf(
            'php -r %s',
            escapeshellarg(sprintf(
                $codeceptionPatch,
                $this->webDriverConnectionTimeout,
                $this->webDriverRequestTimeout,
                $this->webDriverPageLoadTimeout,
                $this->webDriverWaitTimeout,
            )),
        );

        // MFTF fix: Ensure AllureCodeception is in the enabled extensions list.
        // Some Magento installations have it only in config section but not enabled.
        // Without this, Allure output directory is never set and screenshots fail.
        // Check for "- Qameta" with 8-space indent (enabled list format), add if missing.
        $parts[] = "grep -q '^        - Qameta' codeception.yml || sed -i '/Subscriber.Console/a\\        - Qameta\\\\Allure\\\\Codeception\\\\AllureCodeception' codeception.yml";

        // MFTF fix: Set per-run outputDirectory for Allure result isolation.
        // allure-framework/allure-codeception doesn't support ALLURE_OUTPUT_PATH env var,
        // so we must modify codeception.yml directly to use per-run subdirectory.
        $perRunOutputDir = 'allure-results/run-' . $runId;
        $parts[] = sprintf(
            "sed -i 's|outputDirectory: allure-results.*|outputDirectory: %s|' codeception.yml",
            $perRunOutputDir,
        );

        // Build .env file with layered configuration:
        // 1. Global variables (shared across all environments)
        // 2. Module's Cron/data/.env.{env-name} (test-specific data, ~200 vars)
        // 3. TestEnvironment entity values (can override)
        // 4. Infrastructure overrides (Selenium)
        $env = $run->getEnvironment();
        $envFileName = '.env.' . $env->getCode(); // e.g., .env.stage-us
        $moduleEnvFile = $mountedModulePath . '/Cron/data/' . $envFileName;

        // Per-environment config directory (tmpfs mount - isolated per container)
        $envConfigDir = $acceptanceDir . '/env-config';
        $mftfEnvFile = $envConfigDir . '/.env';

        // Create env-config dir and symlink to expected .env location
        $parts[] = sprintf('mkdir -p %s', escapeshellarg($envConfigDir));
        $parts[] = sprintf('ln -sf %s %s', escapeshellarg($mftfEnvFile), escapeshellarg($acceptanceDir . '/.env'));

        // Layer 1: Start with global + environment-specific variables from database
        // SECURITY: Validate and escape all variables to prevent command injection
        $globalVars = $this->globalEnvVariableRepository->getAllAsKeyValue($env->getCode());
        if (!empty($globalVars)) {
            $globalContent = "# Global variables (from ATR database)\n";
            foreach ($globalVars as $key => $value) {
                try {
                    // SECURITY: Validate variable name and build safe env file line
                    $globalContent .= $this->shellEscapeService->buildEnvFileLine($key, $value) . "\n";
                } catch (\InvalidArgumentException $e) {
                    $this->logger->error('Invalid environment variable detected', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                        'runId' => $run->getId(),
                        'errorId' => ErrorIds::MFTF_ENV_VAR_INVALID,
                    ]);

                    throw new \RuntimeException(sprintf('Test run aborted: invalid environment variable "%s": %s', $key, $e->getMessage()));
                }
            }
            $parts[] = sprintf('echo %s > %s', escapeshellarg($globalContent), escapeshellarg($mftfEnvFile));
            $parts[] = sprintf('cat %s >> %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        } else {
            $parts[] = sprintf('cp %s %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        }

        // Layer 2: Override Selenium configuration (ATR infra)
        // SECURITY: Use secure building for selenium configuration
        $seleniumHostLine = $this->shellEscapeService->buildEnvFileLine('SELENIUM_HOST', $this->seleniumHost);
        $seleniumPortLine = $this->shellEscapeService->buildEnvFileLine('SELENIUM_PORT', (string) $this->seleniumPort);
        $parts[] = sprintf('echo %s >> %s', escapeshellarg($seleniumHostLine), escapeshellarg($mftfEnvFile));
        $parts[] = sprintf('echo %s >> %s', escapeshellarg($seleniumPortLine), escapeshellarg($mftfEnvFile));

        // Layer 3: Override MFTF wait timeout (align with WebDriver wait)
        $waitTimeoutLine = $this->shellEscapeService->buildEnvFileLine('WAIT_TIMEOUT', (string) $this->webDriverWaitTimeout);
        $parts[] = sprintf('echo %s >> %s', escapeshellarg($waitTimeoutLine), escapeshellarg($mftfEnvFile));

        // Create per-run Allure output directory before MFTF runs
        // (codeception.yml outputDirectory is set via sed earlier in this command)
        $allureOutputPath = $this->magentoRoot . '/dev/tests/acceptance/allure-results/run-' . $runId;
        $parts[] = sprintf('mkdir -p %s', escapeshellarg($allureOutputPath));

        // Generate credentials file from env variables (MFTF requires this for _CREDS references)
        $credentialsFile = $envConfigDir . '/.credentials';
        $parts[] = $this->buildCredentialsCommand($mftfEnvFile, $credentialsFile);
        // Symlink credentials to expected location
        $parts[] = sprintf('ln -sf %s %s', escapeshellarg($credentialsFile), escapeshellarg($acceptanceDir . '/.credentials'));

        // MFTF binary path
        $mftfBin = $this->magentoRoot . '/vendor/bin/mftf';

        // Always use run:test - sequential group execution replaces run:group
        $mftfCommand = 'run:test';

        $mftfParts = [$mftfBin . ' ' . $mftfCommand];
        $mftfParts[] = escapeshellarg($filter);
        $mftfParts[] = '-fr'; // failed rerun flag

        $mftfParts[] = '--ansi'; // Force colored output

        $pidFile = $this->getPidFilePath($runId);
        $parts[] = sprintf('rm -f %s', escapeshellarg($pidFile));
        $parts[] = sprintf('(%s) & echo $! > %s; wait $!', implode(' ', $mftfParts), escapeshellarg($pidFile));

        // Build command chain with && for dependencies
        $mainCommand = implode(' && ', $parts);

        // After MFTF execution, move screenshots to per-run directory to prevent
        // cross-run contamination when multiple runs execute in parallel.
        // Use ; to run regardless of MFTF exit code (we want artifacts even on failure)
        // NOTE: Allure results are NOT moved here - they're in a separate Docker mount
        // and AllureReportService handles copying them to per-run directory.
        $runOutputDir = 'tests/_output/run-' . $runId;
        $moveCommands = [
            'mkdir -p ' . escapeshellarg($runOutputDir),
            'find tests/_output -maxdepth 1 -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.gif" -o -name "*.html" \) -exec mv {} ' . escapeshellarg($runOutputDir) . '/ \; 2>/dev/null || true',
            'rm -f ' . escapeshellarg($pidFile) . ' || true',
        ];

        // Append move commands with ; so they run regardless of test result
        return $mainCommand . ' ; ' . implode(' && ', $moveCommands);
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
    public function parseResults(TestRun $run, string $output, ?string $outputFilePath = null): array
    {
        $results = [];

        // Strip ANSI escape codes for clean parsing
        // Handles both \x1b[...m (ESC sequences) and [...m (raw brackets from logs)
        $parseOutput = $this->getOutputForParsing($output, $outputFilePath);
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m|\[[0-9;]*m/', '', $parseOutput);

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
                $testId = $this->extractStrictTestIdFromCest($match[1]);
                if ($testId) {
                    $result->setTestId($testId);
                }

                $results[] = $result;
            }
        }

        // Fallback: Parse from Signature lines if block matching fails
        if (empty($results)) {
            $results = $this->parseResultsFallback($run, $cleanOutput);
        }

        // If no results parsed, check for explicit success/failure indicators
        if (empty($results)) {
            $testId = null;

            // Extract test ID from output or use filter
            $testId = $this->extractStrictTestIdFromCest($cleanOutput);
            if (!$testId && ($filter = $run->getTestFilter())) {
                $testId = $this->extractStrictTestId($filter);
            }
            // Don't use raw filter as testId - it could be a group name like "us"

            // Check for errors FIRST - errors take priority over individual "PASSED" lines
            // because "PASSED" can appear for steps even when overall test fails
            if ($this->hasErrorIndicators($cleanOutput)) {
                // Create synthetic broken result for real failures
                $result = new TestResult();
                $result->setTestRun($run);
                $result->setTestName($testId ?? 'Unknown');
                if ($testId) {
                    $result->setTestId($testId);
                }
                $result->setStatus(TestResult::STATUS_BROKEN);
                $results[] = $result;
            } elseif (preg_match('/OK\s*\(\d+\s*test/', $cleanOutput)) {
                // Definitive success: "OK (X tests..." - test passed
                $result = new TestResult();
                $result->setTestRun($run);
                $result->setTestName($testId ?? 'Unknown');
                if ($testId) {
                    $result->setTestId($testId);
                }
                $result->setStatus(TestResult::STATUS_PASSED);
                $results[] = $result;
            }
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
     * Get path to Allure results from MFTF for a specific run.
     *
     * Each run writes to isolated per-run directory via ALLURE_OUTPUT_PATH env var.
     * Volume mount syncs container path to host path automatically.
     */
    public function getAllureResultsPath(int $runId): string
    {
        return $this->projectDir . '/var/mftf-results/allure-results/run-' . $runId;
    }

    /**
     * Extract strict testId token from arbitrary text.
     */
    private function extractStrictTestId(string $text): ?string
    {
        if (preg_match('/\b([A-Z]{2,10}-?\d{2,6}[A-Z]{0,3})\b/i', $text, $matches)) {
            return $this->normalizeTestId($matches[1]);
        }

        return null;
    }

    /**
     * Extract strict testId from a Cest class name or text containing "Cest".
     */
    private function extractStrictTestIdFromCest(string $text): ?string
    {
        if (preg_match('/([A-Z]{2,10}-?\d{2,6}[A-Z]{0,3})Cest/i', $text, $matches)) {
            return $this->normalizeTestId($matches[1]);
        }

        $trimmed = preg_replace('/Cest$/i', '', $text);
        if ($trimmed && $trimmed !== $text) {
            $fromTrimmed = $this->extractStrictTestId($trimmed);
            if ($fromTrimmed) {
                return $fromTrimmed;
            }
        }

        return $this->extractStrictTestId($text);
    }

    /**
     * Normalize testId for strict matching (preserves suffix like US/ES).
     */
    private function normalizeTestId(string $testId): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $testId));
    }

    /**
     * Check if run was cancelled and stop process if so.
     *
     * @throws \RuntimeException if cancelled
     */
    private function checkCancellation(TestRun $run, Process $process): void
    {
        // Refresh from DB to get latest status
        $this->entityManager->refresh($run);

        if (TestRun::STATUS_CANCELLED === $run->getStatus()) {
            $this->logger->info('Test run cancelled, stopping MFTF process', [
                'runId' => $run->getId(),
                'pid' => $process->getPid(),
            ]);

            $this->terminateRunProcess($run);
            $process->stop(10); // Give 10 seconds for graceful shutdown

            throw new \RuntimeException('Test run was cancelled');
        }
    }

    private function terminateRunProcess(TestRun $run): void
    {
        $pidFile = $this->getPidFilePath((int) $run->getId());
        // Use getContainerNameForEnvironment to avoid container creation side effects
        $container = $this->containerPool->getContainerNameForEnvironment($run->getEnvironment());

        $killCommand = sprintf(
            'if [ -f %1$s ]; then pid=$(cat %1$s); ' .
            'if [ -n "$pid" ]; then ' .
            'kill -TERM -- -"$pid" 2>/dev/null || true; kill -TERM "$pid" 2>/dev/null || true; ' .
            'sleep 2; ' .
            'kill -KILL -- -"$pid" 2>/dev/null || true; kill -KILL "$pid" 2>/dev/null || true; ' .
            'fi; ' .
            'rm -f %1$s; fi',
            escapeshellarg($pidFile),
        );

        $process = new Process([
            'docker', 'exec',
            $container,
            'bash', '-c',
            $killCommand,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning('Failed to terminate MFTF process', [
                'runId' => $run->getId(),
                'error' => $process->getErrorOutput(),
            ]);
        }
    }

    /**
     * Check if output contains error indicators that suggest test failure.
     *
     * Codeception summary format:
     * - Success: "OK (1 test, 10 assertions)"
     * - Failure: "ERRORS!" or "Tests: X, Assertions: Y, Errors: Z" (where Z > 0)
     *
     * IMPORTANT: Check for errors FIRST because "PASSED" can appear for individual
     * steps even when the overall test fails (e.g., cleanup errors in _after hook).
     */
    private function hasErrorIndicators(string $output): bool
    {
        // FIRST: Check for definitive error summary from Codeception
        // This takes priority over individual "PASSED" lines
        $definitiveErrorPatterns = [
            '/ERRORS!\s*$/m',                            // Codeception error marker
            '/Errors:\s*([1-9]\d*)/i',                   // "Errors: N" where N > 0
            '/Failures:\s*([1-9]\d*)/i',                 // "Failures: N" where N > 0
        ];

        foreach ($definitiveErrorPatterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true; // Definitive failure, regardless of "PASSED" lines
            }
        }

        // Check for definitive success - "OK (X tests..." means all tests passed
        if (preg_match('/OK\s*\(\d+\s*test/', $output)) {
            return false; // Definitive success
        }

        // Exclude known infrastructure/cleanup exceptions that don't indicate test failure
        $excludedPatterns = [
            '/AllureHelper.*unlink/s',                   // Allure cleanup race condition
            '/unlink\(.*_generated.*Cest\.php\)/s',     // Generated test file cleanup
        ];

        foreach ($excludedPatterns as $pattern) {
            if (preg_match($pattern, $output)) {
                // Check if this is the ONLY exception - if so, not a real error
                $output = preg_replace($pattern, '', $output);
            }
        }

        // Check for other error patterns
        $errorPatterns = [
            '/\[.*Exception\]/',                        // Any exception (FastFailException, etc)
            '/Fatal error:/i',                          // PHP fatal error
            '/Uncaught exception/i',                    // Uncaught exception
            '/failed to generate/i',                    // MFTF generation failure
            '/Step\s+\[.*?\]\s+.*?FAIL/s',              // Step failure without proper result line
            '/^\s*FAIL\s*$/m',                          // Standalone FAIL line
        ];

        foreach ($errorPatterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
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
     * Get output suitable for parsing test names/statuses.
     * Prefer head+tail of the full output file to avoid truncation issues.
     */
    private function getOutputForParsing(string $fallback, ?string $outputFilePath): string
    {
        if (!$outputFilePath || !file_exists($outputFilePath)) {
            return $fallback;
        }

        $parsed = $this->readOutputFileForParsing($outputFilePath);

        return '' !== $parsed ? $parsed : $fallback;
    }

    /**
     * Read head+tail of output file to preserve test headers and summaries.
     */
    private function readOutputFileForParsing(string $path, int $headBytes = 200000, int $tailBytes = 200000): string
    {
        $size = filesize($path);
        if (false === $size) {
            return '';
        }

        if ($size <= $headBytes + $tailBytes) {
            return file_get_contents($path) ?: '';
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            return '';
        }

        $head = fread($handle, $headBytes) ?: '';
        fseek($handle, -$tailBytes, SEEK_END);
        $tail = fread($handle, $tailBytes) ?: '';
        fclose($handle);

        return $head . "\n... [truncated for parsing]\n" . $tail;
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

            $testId = $this->extractStrictTestIdFromCest($test['cest']);
            if ($testId) {
                $result->setTestId($testId);
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
        if (2 === count($parts)) {
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
     *
     * Supports MAGENTO_API_AUTH_USERNAME and MAGENTO_API_AUTH_PASSWORD for separate
     * API authentication when SSO browser login uses different credentials.
     */
    private function buildCredentialsCommand(string $envFile, string $credentialsFile): string
    {
        // Extract password/secret variables from .env and format for .credentials
        // Format: magento/VAR_NAME=value
        // If MAGENTO_API_AUTH_USERNAME/PASSWORD is set, use it for MAGENTO_ADMIN_USERNAME/PASSWORD in credentials
        // This allows SSO browser login to use different credentials than REST API auth
        $script = <<<'BASH'
            (
                echo "# MFTF credentials auto-generated from .env"
                echo "magento/tfa/OTP_SHARED_SECRET=ABCDEFGHIJKLMNOP"

                # Check if API auth overrides exist
                API_AUTH_USER=$(grep -E "^MAGENTO_API_AUTH_USERNAME=" "$1" 2>/dev/null | cut -d'=' -f2- | sed "s/^['\"]//;s/['\"]$//")
                API_AUTH_PASS=$(grep -E "^MAGENTO_API_AUTH_PASSWORD=" "$1" 2>/dev/null | cut -d'=' -f2- | sed "s/^['\"]//;s/['\"]$//")

                # Extract all PASSWORD, SECRET, KEY, USERNAME variables from .env
                grep -E "^(MAGENTO_ADMIN|MAGENTO_TEST|.*PASSWORD|.*SECRET|.*KEY)=" "$1" 2>/dev/null | while IFS='=' read -r key value; do
                    # Remove quotes from value
                    value=$(echo "$value" | sed "s/^['\"]//;s/['\"]$//")

                    # Use API auth username for MAGENTO_ADMIN_USERNAME if override exists
                    if [ "$key" = "MAGENTO_ADMIN_USERNAME" ] && [ -n "$API_AUTH_USER" ]; then
                        value="$API_AUTH_USER"
                    fi

                    # Use API auth password for MAGENTO_ADMIN_PASSWORD if override exists
                    if [ "$key" = "MAGENTO_ADMIN_PASSWORD" ] && [ -n "$API_AUTH_PASS" ]; then
                        value="$API_AUTH_PASS"
                    fi

                    # Skip the API auth variables themselves
                    if [ "$key" = "MAGENTO_API_AUTH_USERNAME" ] || [ "$key" = "MAGENTO_API_AUTH_PASSWORD" ]; then
                        continue
                    fi

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

    private function getPidFilePath(int $runId): string
    {
        return $this->magentoRoot . '/dev/tests/acceptance/.mftf-run-' . $runId . '.pid';
    }
}
