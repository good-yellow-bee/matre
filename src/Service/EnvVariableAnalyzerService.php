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

        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $testId = $this->extractTestId($file->getFilename());
            if ($testId === null) {
                continue;
            }

            $content = $file->getContents();
            // Match {{_ENV.VAR_NAME}} patterns
            preg_match_all('/\{\{_ENV\.([A-Z][A-Z0-9_]*)\}\}/', $content, $matches);

            foreach (array_unique($matches[1]) as $varName) {
                if (!isset($usage[$varName])) {
                    $usage[$varName] = [];
                }
                $usage[$varName][] = $testId;
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
}
