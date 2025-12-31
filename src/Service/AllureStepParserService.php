<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class AllureStepParserService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get parsed steps for a test result.
     *
     * @return array{testName: string, status: string, duration: ?float, steps: array, error: ?string}|null
     */
    public function getStepsForResult(TestResult $result): ?array
    {
        $allurePath = $result->getAllureResultPath();

        if (!$allurePath) {
            $allurePath = $this->findAllureFileForResult($result);
        }

        if (!$allurePath) {
            return null;
        }

        return $this->parseAllureFile($allurePath, $result);
    }

    /**
     * Find Allure JSON file for a test result by searching run directory.
     */
    public function findAllureFileForResult(TestResult $result): ?string
    {
        $testRun = $result->getTestRun();
        if (!$testRun) {
            return null;
        }

        $runDir = sprintf('%s/var/allure-results/run-%d', $this->projectDir, $testRun->getId());

        if (!is_dir($runDir)) {
            $this->logger->debug('Allure run directory not found: {dir}', ['dir' => $runDir]);

            return null;
        }

        $testName = $result->getTestName();
        $testId = $result->getTestId();
        $finder = new Finder();
        $finder->files()->in($runDir)->name('*-result.json');

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            if (!$content) {
                continue;
            }

            $data = json_decode($content, true);
            if (!$data) {
                continue;
            }

            // Try multiple matching strategies
            if ($this->matchesTestResult($data, $testName, $testId)) {
                return $file->getRealPath();
            }
        }

        return null;
    }

    /**
     * Parse Allure JSON file and extract steps.
     *
     * @return array{testName: string, status: string, duration: ?float, steps: array, error: ?string}
     */
    private function parseAllureFile(string $filePath, TestResult $result): array
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return $this->createEmptyResponse($result, 'Could not read Allure file');
        }

        $data = json_decode($content, true);
        if (!$data) {
            return $this->createEmptyResponse($result, 'Invalid JSON in Allure file');
        }

        $steps = $data['steps'] ?? [];
        $parsedSteps = $this->parseSteps($steps);
        $parsedSteps = $this->buildHierarchy($parsedSteps);

        $duration = null;
        if (isset($data['start'], $data['stop'])) {
            $duration = ($data['stop'] - $data['start']) / 1000; // Convert ms to seconds
        }

        $error = null;
        if (isset($data['statusDetails']['message'])) {
            $error = $this->stripAnsiCodes($data['statusDetails']['message']);
        }

        return [
            'testName' => $result->getTestName(),
            'status' => $data['status'] ?? $result->getStatus(),
            'duration' => $duration,
            'steps' => $parsedSteps,
            'error' => $error,
        ];
    }

    /**
     * Recursively parse steps array.
     */
    private function parseSteps(array $steps): array
    {
        $parsed = [];

        foreach ($steps as $step) {
            $duration = null;
            if (isset($step['start'], $step['stop'])) {
                $duration = ($step['stop'] - $step['start']) / 1000;
            }

            $children = [];
            if (!empty($step['steps'])) {
                $children = $this->parseSteps($step['steps']);
            }

            $parsed[] = [
                'name' => $this->stripAnsiCodes($step['name'] ?? 'Unknown step'),
                'status' => $step['status'] ?? 'unknown',
                'duration' => $duration,
                'children' => $children,
            ];
        }

        return $parsed;
    }

    /**
     * Convert flat steps with "entering/exiting action group" markers into nested hierarchy.
     */
    private function buildHierarchy(array $steps): array
    {
        $result = [];
        $stack = [&$result]; // Stack of references to current children arrays

        foreach ($steps as $step) {
            $name = $step['name'] ?? '';

            // Detect "entering action group [X]" pattern
            if (preg_match('/entering action group \[([^\]]+)\]/i', $name, $matches)) {
                $groupName = $matches[1];
                $newGroup = [
                    'name' => $groupName,
                    'status' => $step['status'] ?? 'passed',
                    'duration' => $step['duration'],
                    'children' => [],
                ];
                // Add to current level and push children array to stack
                $current = &$stack[count($stack) - 1];
                $current[] = $newGroup;
                $stack[] = &$current[count($current) - 1]['children'];

                continue;
            }

            // Detect "exiting action group [X]" pattern
            if (preg_match('/exiting action group \[([^\]]+)\]/i', $name)) {
                if (count($stack) > 1) {
                    array_pop($stack);
                }

                continue;
            }

            // Regular step - add to current level
            $current = &$stack[count($stack) - 1];
            $current[] = $step;
        }

        return $result;
    }

    /**
     * Strip ANSI escape codes from text.
     */
    private function stripAnsiCodes(string $text): string
    {
        return preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text) ?? $text;
    }

    /**
     * Check if Allure data matches TestResult using multiple strategies.
     */
    private function matchesTestResult(array $allureData, string $testName, ?string $testId): bool
    {
        $allureName = $allureData['name'] ?? '';
        $allureFullName = $allureData['fullName'] ?? '';

        // Strategy 1: Match by testId (e.g., "MOEC2417") - most reliable
        if ($testId) {
            $testIdUpper = strtoupper($testId);
            if (stripos($allureName, $testIdUpper) !== false) {
                return true;
            }
            if (stripos($allureFullName, $testIdUpper) !== false) {
                return true;
            }
        }

        // Strategy 2: Extract test ID pattern from both and compare
        // Allure name: "MOEC2417: Test for LV Drives..." -> MOEC2417
        // TestResult: "MOEC2417Cest: MOEC2417" -> MOEC2417
        $allureTestId = $this->extractTestId($allureName);
        $resultTestId = $this->extractTestId($testName);

        if ($allureTestId && $resultTestId && strcasecmp($allureTestId, $resultTestId) === 0) {
            return true;
        }

        // Strategy 3: Match Allure fullName against TestResult testName
        // fullName: "Magento\AcceptanceTest\_default\Backend\MOEC2417Cest::MOEC2417"
        // testName: "MOEC2417Cest: MOEC2417"
        if ($allureFullName) {
            $normalizedFull = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $allureFullName));
            $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $testName));

            if (str_contains($normalizedFull, $normalizedName) || str_contains($normalizedName, $normalizedFull)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract test ID (like MOEC2417) from test name.
     */
    private function extractTestId(string $name): ?string
    {
        // Match patterns like MOEC2417, MOEC-2417, TEST123
        if (preg_match('/\b(MOEC[-]?\d+|[A-Z]{2,10}[-]?\d{2,6})\b/i', $name, $matches)) {
            return strtoupper(str_replace('-', '', $matches[1]));
        }

        return null;
    }

    /**
     * Create empty response with error message.
     *
     * @return array{testName: string, status: string, duration: ?float, steps: array, error: ?string}
     */
    private function createEmptyResponse(TestResult $result, string $error): array
    {
        return [
            'testName' => $result->getTestName(),
            'status' => $result->getStatus(),
            'duration' => $result->getDuration(),
            'steps' => [],
            'error' => $error,
        ];
    }
}
