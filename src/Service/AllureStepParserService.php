<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class AllureStepParserService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get parsed steps for a test result.
     *
     * @return array{testName: string, status: string, duration: ?float, steps: array, error: ?string}|null
     */
    public function getStepsForResult(TestResult $result): ?array
    {
        $this->logger->debug('Allure: getStepsForResult called', [
            'resultId' => $result->getId(),
            'testName' => $result->getTestName(),
        ]);

        $allurePath = $result->getAllureResultPath();

        $this->logger->debug('Allure: Stored path', ['allurePath' => $allurePath]);

        if (!$allurePath) {
            $allurePath = $this->findAllureFileForResult($result);
        }

        if (!$allurePath) {
            $this->logger->debug('Allure: No path found, returning null');

            return null;
        }

        $this->logger->debug('Allure: Parsing file', ['path' => $allurePath]);

        return $this->parseAllureFile($allurePath, $result);
    }

    /**
     * Get duration from Allure data for a test result (fallback when MFTF output lacks it).
     */
    public function getDurationForResult(TestResult $result): ?float
    {
        $filePath = $this->findAllureFileForResult($result);
        if (!$filePath) {
            return null;
        }

        $content = file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!$data || !isset($data['start'], $data['stop'])) {
            return null;
        }

        return ($data['stop'] - $data['start']) / 1000; // Convert ms to seconds
    }

    /**
     * Find Allure JSON file for a test result by searching run directory.
     * When multiple files match, prefer the most recent one.
     */
    public function findAllureFileForResult(TestResult $result): ?string
    {
        $testRun = $result->getTestRun();
        if (!$testRun) {
            $this->logger->debug('Allure: TestRun not found for result', ['resultId' => $result->getId()]);

            return null;
        }

        $runDir = sprintf('%s/var/mftf-results/allure-results/run-%d', $this->projectDir, $testRun->getId());

        $this->logger->debug('Allure: Searching for result file', [
            'resultId' => $result->getId(),
            'testName' => $result->getTestName(),
            'testId' => $result->getTestId(),
            'runDir' => $runDir,
        ]);

        // Fallback to root allure-results directory for older runs (before per-run isolation)
        if (!is_dir($runDir)) {
            $runDir = sprintf('%s/var/mftf-results/allure-results', $this->projectDir);
            $this->logger->debug('Per-run directory not found, falling back to root', [
                'runId' => $testRun->getId(),
                'fallbackDir' => $runDir,
            ]);

            if (!is_dir($runDir)) {
                $this->logger->debug('Allure root directory not found: {dir}', ['dir' => $runDir]);

                return null;
            }
        }

        $testName = $result->getTestName();
        $testId = $result->getTestId();
        $finder = new Finder();
        $finder->files()->in($runDir)->name('*-result.json');

        $matchingFiles = [];
        $filesScanned = 0;

        foreach ($finder as $file) {
            ++$filesScanned;
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
                $matchingFiles[] = [
                    'path' => $file->getRealPath(),
                    'mtime' => $file->getMTime(),
                ];
            }
        }

        $this->logger->debug('Allure: Search complete', [
            'filesScanned' => $filesScanned,
            'matchesFound' => count($matchingFiles),
        ]);

        if (empty($matchingFiles)) {
            // Strategy 4: Exclusion-based matching for "Unknown" tests
            // Find Allure files that don't match any other test in this run
            if ('Unknown' === $testName) {
                return $this->findUnmatchedAllureFile($result, $runDir);
            }

            return null;
        }

        // Sort by modification time descending - prefer most recent
        usort($matchingFiles, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        $this->logger->debug('Allure: Returning file', ['path' => $matchingFiles[0]['path']]);

        return $matchingFiles[0]['path'];
    }

    /**
     * Find unmatched Allure file by exclusion for "Unknown" tests.
     * Collects testIds from other results in the run and returns first unmatched Allure file.
     */
    private function findUnmatchedAllureFile(TestResult $result, string $runDir): ?string
    {
        $testRun = $result->getTestRun();
        if (!$testRun) {
            return null;
        }

        // Collect testIds from other results in this run
        $knownTestIds = [];
        foreach ($testRun->getResults() as $otherResult) {
            if ($otherResult->getId() === $result->getId()) {
                continue;
            }
            if ($testId = $otherResult->getTestId()) {
                $knownTestIds[] = strtoupper($testId);
            }
        }

        $this->logger->debug('Allure: Exclusion matching for Unknown test', [
            'knownTestIds' => $knownTestIds,
            'runDir' => $runDir,
        ]);

        $finder = new Finder();
        $finder->files()->in($runDir)->name('*-result.json');

        $unmatchedFiles = [];

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            if (!$content) {
                continue;
            }

            $data = json_decode($content, true);
            if (!$data) {
                continue;
            }

            // Extract testId from this Allure file
            $allureTestId = $this->extractTestId($data['name'] ?? '');
            if (!$allureTestId) {
                $allureTestId = $this->extractTestId($data['fullName'] ?? '');
            }

            // Skip if this Allure file matches a known testId
            if ($allureTestId && in_array(strtoupper($allureTestId), $knownTestIds, true)) {
                continue;
            }

            // This file doesn't match any known test - potential match
            $unmatchedFiles[] = [
                'path' => $file->getRealPath(),
                'mtime' => $file->getMTime(),
                'testId' => $allureTestId,
                'fullName' => $data['fullName'] ?? null,
            ];
        }

        $this->logger->debug('Allure: Exclusion search complete', [
            'unmatchedCount' => count($unmatchedFiles),
        ]);

        if (empty($unmatchedFiles)) {
            return null;
        }

        // Prefer most recent unmatched file
        usort($unmatchedFiles, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        $this->logger->debug('Allure: Returning unmatched file', [
            'path' => $unmatchedFiles[0]['path'],
            'testId' => $unmatchedFiles[0]['testId'],
        ]);

        return $unmatchedFiles[0]['path'];
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

        // Backfill testId/testName for "Unknown" tests from Allure data
        $testName = $result->getTestName();
        if ('Unknown' === $testName) {
            $this->backfillTestInfo($result, $data);
            $testName = $result->getTestName(); // Get updated name
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
            'testName' => $testName,
            'status' => $data['status'] ?? $result->getStatus(),
            'duration' => $duration,
            'steps' => $parsedSteps,
            'error' => $error,
        ];
    }

    /**
     * Backfill testId and testName from Allure data for "Unknown" tests.
     * Persists the extracted info to database for future lookups.
     */
    private function backfillTestInfo(TestResult $result, array $allureData): void
    {
        $fullName = $allureData['fullName'] ?? '';
        $allureName = $allureData['name'] ?? '';

        // Extract testId from Allure data
        $testId = $this->extractTestId($allureName) ?? $this->extractTestId($fullName);

        if (!$testId) {
            return;
        }

        // Extract Cest class and method from fullName
        // Format: "Magento\AcceptanceTest\_default\Backend\MOEC2606Cest::MOEC2606"
        $testName = null;
        if (preg_match('/([A-Z][A-Za-z0-9]+Cest)::(\w+)/', $fullName, $matches)) {
            $testName = $matches[1] . ':' . $matches[2];
        }

        $this->logger->debug('Allure: Backfilling test info', [
            'resultId' => $result->getId(),
            'extractedTestId' => $testId,
            'extractedTestName' => $testName,
        ]);

        // Update the entity
        $result->setTestId($testId);
        if ($testName) {
            $result->setTestName($testName);
        }

        // Persist to database
        $this->entityManager->persist($result);
        $this->entityManager->flush();
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
            if (false !== stripos($allureName, $testIdUpper)) {
                return true;
            }
            if (false !== stripos($allureFullName, $testIdUpper)) {
                return true;
            }
        }

        // Strategy 2: Extract test ID pattern from both and compare
        // Allure name: "MOEC2417: Test for LV Drives..." -> MOEC2417
        // TestResult: "MOEC2417Cest: MOEC2417" -> MOEC2417
        $allureTestId = $this->extractTestId($allureName);
        $resultTestId = $this->extractTestId($testName);

        if ($allureTestId && $resultTestId && 0 === strcasecmp($allureTestId, $resultTestId)) {
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
