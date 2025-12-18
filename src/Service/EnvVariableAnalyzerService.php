<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Finder\Finder;

/**
 * Analyzes environment variables usage in MFTF test files.
 *
 * This service provides functionality to:
 * - Parse .env files into key-value arrays
 * - Scan MFTF XML test files for {{_ENV.VAR}} patterns
 * - Map environment variables to the tests that use them
 */
class EnvVariableAnalyzerService
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Parse .env file to key-value array.
     *
     * @param string $path Path to .env file
     *
     * @return array<string, string> Parsed KEY => value pairs
     */
    public function parseEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('File not found: %s', $path));
        }

        $data = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Scan MFTF XML test files for {{_ENV.VAR}} patterns.
     *
     * This method resolves transitive dependencies:
     * - Direct: Test uses {{_ENV.VAR}} directly
     * - Indirect: Test uses ActionGroup that uses {{_ENV.VAR}}
     *
     * @param string $modulePath Path to test module root
     *
     * @return array<string, string[]> Map of VAR_NAME => [TEST_ID1, TEST_ID2, ...]
     */
    public function analyzeTestUsage(string $modulePath): array
    {
        $usage = [];
        $testDir = $this->findTestDirectory($modulePath);

        if ($testDir === null) {
            return [];
        }

        // Step 1: Direct env var usage in Test files
        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();

            // Extract test name from <test name="...">
            if (!preg_match('/<test\s+name="([^"]+)"/', $content, $nameMatch)) {
                continue;
            }
            $testName = $nameMatch[1];

            // Match {{_ENV.VAR_NAME}} patterns
            preg_match_all('/\{\{_ENV\.([A-Z][A-Z0-9_]*)\}\}/', $content, $matches);

            foreach (array_unique($matches[1]) as $varName) {
                if (!isset($usage[$varName])) {
                    $usage[$varName] = [];
                }
                $usage[$varName][] = $testName;
            }
        }

        // Step 2: Resolve ActionGroup → Test transitive dependencies
        $actionGroupEnvVars = $this->parseActionGroupEnvVars($modulePath);
        $testActionGroupRefs = $this->parseTestActionGroupRefs($modulePath);

        // For each ActionGroup that uses env vars, find tests that use that ActionGroup
        foreach ($actionGroupEnvVars as $actionGroupName => $envVars) {
            foreach ($testActionGroupRefs as $testName => $usedActionGroups) {
                if (in_array($actionGroupName, $usedActionGroups, true)) {
                    // This test uses an ActionGroup that uses these env vars
                    foreach ($envVars as $varName) {
                        if (!isset($usage[$varName])) {
                            $usage[$varName] = [];
                        }
                        $usage[$varName][] = $testName;
                    }
                }
            }
        }

        // Deduplicate and sort test IDs per variable
        foreach ($usage as $varName => $tests) {
            $usage[$varName] = array_values(array_unique($tests));
            sort($usage[$varName]);
        }

        return $usage;
    }

    /**
     * Extract test ID from XML filename.
     *
     * Examples:
     *   - MOEC1625Test.xml → MOEC1625
     *   - AdminCheckoutTest.xml → AdminCheckoutTest
     *   - CheckoutGuestTest.xml → CheckoutGuestTest
     *
     * @param string $filename The XML filename
     *
     * @return string|null Test ID or null if cannot extract
     */
    public function extractTestId(string $filename): ?string
    {
        // Remove .xml extension
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (empty($name)) {
            return null;
        }

        // Try to extract alphanumeric ID pattern (e.g., MOEC1625)
        if (preg_match('/^([A-Z]+\d+)/', $name, $matches)) {
            return $matches[1];
        }

        // Fall back to full name without "Test" suffix
        return preg_replace('/Test$/', '', $name) ?: $name;
    }

    /**
     * Get default module path.
     */
    public function getDefaultModulePath(): string
    {
        return $this->projectDir . '/var/test-modules/current';
    }

    /**
     * Find the MFTF Test directory within the module.
     */
    private function findTestDirectory(string $modulePath): ?string
    {
        // Common MFTF test directory patterns
        $patterns = [
            '/Test/Mftf/Test',
            '/Mftf/Test',
            '/Test/Test',
        ];

        foreach ($patterns as $pattern) {
            $testDir = rtrim($modulePath, '/') . $pattern;
            if (is_dir($testDir)) {
                return $testDir;
            }
        }

        return null;
    }

    /**
     * Find the MFTF ActionGroup directory within the module.
     */
    private function findActionGroupDirectory(string $modulePath): ?string
    {
        $patterns = [
            '/Test/Mftf/ActionGroup',
            '/Mftf/ActionGroup',
            '/Test/ActionGroup',
        ];

        foreach ($patterns as $pattern) {
            $dir = rtrim($modulePath, '/') . $pattern;
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Parse ActionGroup files to find env vars used by each ActionGroup.
     *
     * @return array<string, string[]> Map of ActionGroupName => [VAR1, VAR2, ...]
     */
    private function parseActionGroupEnvVars(string $modulePath): array
    {
        $actionGroupDir = $this->findActionGroupDirectory($modulePath);
        if ($actionGroupDir === null) {
            return [];
        }

        $usage = [];
        $finder = new Finder();
        $finder->files()->in($actionGroupDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();

            // Extract ActionGroup name from <actionGroup name="...">
            if (!preg_match('/<actionGroup\s+name="([^"]+)"/', $content, $nameMatch)) {
                continue;
            }
            $actionGroupName = $nameMatch[1];

            // Find {{_ENV.VAR_NAME}} patterns
            preg_match_all('/\{\{_ENV\.([A-Z][A-Z0-9_]*)\}\}/', $content, $matches);
            $envVars = array_unique($matches[1]);

            if (!empty($envVars)) {
                $usage[$actionGroupName] = array_values($envVars);
            }
        }

        return $usage;
    }

    /**
     * Parse Test files to find which ActionGroups each test references.
     *
     * @return array<string, string[]> Map of TestName => [ActionGroup1, ActionGroup2, ...]
     */
    private function parseTestActionGroupRefs(string $modulePath): array
    {
        $testDir = $this->findTestDirectory($modulePath);
        if ($testDir === null) {
            return [];
        }

        $refs = [];
        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();

            // Extract test name from <test name="...">
            if (!preg_match('/<test\s+name="([^"]+)"/', $content, $nameMatch)) {
                continue;
            }
            $testName = $nameMatch[1];

            // Find <actionGroup ref="..."> references
            preg_match_all('/<actionGroup\s+ref="([^"]+)"/', $content, $matches);
            $actionGroups = array_unique($matches[1]);

            if (!empty($actionGroups)) {
                $refs[$testName] = array_values($actionGroups);
            }
        }

        return $refs;
    }
}
