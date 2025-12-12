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
        private readonly string $testModulePath = 'app/code/SiiPoland/Catalog',
    ) {
    }

    /**
     * Execute MFTF tests for a test run.
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

        // Execute via docker
        $process = new Process([
            'docker', 'exec',
            'atr_magento',
            'bash', '-c',
            $mftfCommand,
        ]);
        $process->setTimeout(3600); // 1 hour timeout

        $output = '';
        $process->run(function ($type, $buffer) use (&$output) {
            $output .= $buffer;
        });

        $this->logger->info('MFTF execution completed', [
            'runId' => $run->getId(),
            'exitCode' => $process->getExitCode(),
        ]);

        return [
            'output' => $output,
            'exitCode' => $process->getExitCode() ?? 1,
        ];
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
                $globalContent .= sprintf("%s=%s\n", $key, $value);
            }
            $parts[] = sprintf('echo %s > %s', escapeshellarg($globalContent), escapeshellarg($mftfEnvFile));
            $parts[] = sprintf('cat %s >> %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        } else {
            $parts[] = sprintf('cp %s %s', escapeshellarg($moduleEnvFile), escapeshellarg($mftfEnvFile));
        }

        // Layer 3: Override with TestEnvironment values if present
        $envVars = $env->getEnvVariables();
        foreach ($envVars as $key => $value) {
            $parts[] = sprintf('echo "%s=%s" >> %s', $key, $value, escapeshellarg($mftfEnvFile));
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
        $parts[] = sprintf('%s generate:tests --tests %s --force', $mftfBin, escapeshellarg($testsJson));

        // Build MFTF run command
        $mftfParts = [$mftfBin . ' run:test'];
        $mftfParts[] = escapeshellarg($filter);
        $mftfParts[] = '-fr'; // failed rerun flag

        $parts[] = implode(' ', $mftfParts);

        return implode(' && ', $parts);
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
            escapeshellarg($credentialsFile)
        );
    }

    /**
     * Parse MFTF output to extract test results.
     *
     * @return TestResult[]
     */
    public function parseResults(TestRun $run, string $output): array
    {
        $results = [];

        // Parse Codeception output format
        // Example: "PASSED TestName (1.23s)"
        // Example: "FAIL TestName (0.56s)"
        preg_match_all(
            '/^(PASSED|FAIL|ERROR|SKIP)\s+([^\s]+)(?:\s+\(([0-9.]+)s\))?/m',
            $output,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName($match[2]);
            $result->setStatus($this->mapStatus($match[1]));

            if (!empty($match[3])) {
                $result->setDuration((float) $match[3]);
            }

            // Extract test ID from test name (e.g., MOEC1625Test -> MOEC1625)
            if (preg_match('/^([A-Z]+\d+)/', $match[2], $idMatch)) {
                $result->setTestId($idMatch[1]);
            }

            $results[] = $result;
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
}
