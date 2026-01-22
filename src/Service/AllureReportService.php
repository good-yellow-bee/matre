<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestReport;
use App\Entity\TestRun;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates and manages Allure reports.
 */
class AllureReportService
{
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY_MS = 500;

    private const MIN_INCREMENTAL_REPORT_INTERVAL = 5.0; // seconds

    private ?float $lastIncrementalReportTime = null;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
        private readonly string $allureUrl,
        private readonly string $allurePublicUrl,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Generate Allure report for a test run.
     */
    public function generateReport(TestRun $run, array $resultPaths = []): TestReport
    {
        $this->logger->info('Generating Allure report', [
            'runId' => $run->getId(),
            'resultPaths' => $resultPaths,
        ]);

        $runId = $run->getId();
        $allureResultsPath = $this->getAllureResultsPath($runId);

        // Merge results from sources (uses mergeResults to preserve existing files like synthetic results)
        if (!empty($resultPaths)) {
            $this->mergeResults($resultPaths, $allureResultsPath);
        }

        // Copy attachments from root to per-run directory (allure-codeception bug workaround)
        $this->copyAllAttachmentsForRun($runId);

        // Generate report via Allure service (uses environment name as project ID)
        $reportId = $this->triggerReportGeneration($run);

        // Create report entity
        $report = new TestReport();
        $report->setTestRun($run);
        $report->setReportType(TestReport::TYPE_ALLURE);
        $report->setFilePath($allureResultsPath);
        $report->setPublicUrl($this->getReportUrl($reportId));
        $report->setGeneratedAt(new \DateTimeImmutable());

        // Set expiration (30 days)
        $report->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $this->logger->info('Allure report generated', [
            'runId' => $runId,
            'reportUrl' => $report->getPublicUrl(),
        ]);

        return $report;
    }

    /**
     * Merge multiple result directories into one.
     */
    public function mergeResults(array $sourcePaths, string $targetPath): void
    {
        $this->filesystem->mkdir($targetPath);
        $normalizedTarget = rtrim(realpath($targetPath) ?: $targetPath, '/');

        foreach ($sourcePaths as $sourcePath) {
            if (!$this->filesystem->exists($sourcePath)) {
                continue;
            }

            // Skip self-copy (prevents file truncation when source == target)
            $normalizedSource = rtrim(realpath($sourcePath) ?: $sourcePath, '/');
            if ($normalizedSource === $normalizedTarget) {
                $this->logger->debug('Skipping self-copy for Allure results', [
                    'path' => $sourcePath,
                ]);

                continue;
            }

            // Copy all files from source to target
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                $target = $targetPath . '/' . $iterator->getSubPathname();
                if ($item->isDir()) {
                    $this->filesystem->mkdir($target);
                } else {
                    $this->filesystem->copy($item->getPathname(), $target, true);
                }
            }
        }
    }

    /**
     * Get public URL for a report.
     */
    public function getReportUrl(string $projectId): string
    {
        return $this->allurePublicUrl . '/allure-docker-service/projects/' . $projectId . '/reports/latest/index.html';
    }

    /**
     * Get path to Allure results for a run.
     *
     * Uses same path as MFTF writes to (via ALLURE_OUTPUT_PATH env var).
     * Volume mount syncs container path to host path automatically.
     */
    public function getAllureResultsPath(int $runId): string
    {
        return $this->projectDir . '/var/mftf-results/allure-results/run-' . $runId;
    }

    /**
     * Copy attachments from root allure-results to per-run directory.
     *
     * Allure-codeception writes result/container JSONs to outputDirectory but
     * attachments always go to root allure-results/. This method fixes that by
     * parsing result JSONs and copying referenced attachments to per-run dir.
     */
    public function copyTestAllureResults(int $runId, string $testName): void
    {
        $runDir = $this->getAllureResultsPath($runId);
        $rootDir = $this->projectDir . '/var/mftf-results/allure-results';

        if (!$this->filesystem->exists($runDir)) {
            $this->logger->debug('Per-run Allure directory not found yet', [
                'runId' => $runId,
                'path' => $runDir,
            ]);

            return;
        }

        // Find all result files in per-run directory
        $resultFiles = glob($runDir . '/*-result.json') ?: [];
        $attachmentsCopied = 0;

        foreach ($resultFiles as $resultFile) {
            $content = file_get_contents($resultFile);
            if (false === $content) {
                $this->logger->warning('Failed to read Allure result file', [
                    'file' => $resultFile,
                    'runId' => $runId,
                ]);

                continue;
            }
            if ('' === $content) {
                continue;
            }

            $data = json_decode($content, true);
            if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
                $this->logger->warning('Failed to parse Allure result JSON', [
                    'file' => $resultFile,
                    'runId' => $runId,
                    'jsonError' => json_last_error_msg(),
                ]);

                continue;
            }
            if (empty($data)) {
                continue;
            }

            // Extract all attachment sources from result (including nested steps)
            $attachmentSources = $this->extractAllAttachmentSources($data);

            foreach ($attachmentSources as $source) {
                $sourceFile = $rootDir . '/' . $source;
                $targetFile = $runDir . '/' . $source;

                if ($this->filesystem->exists($sourceFile) && !$this->filesystem->exists($targetFile)) {
                    try {
                        $this->filesystem->copy($sourceFile, $targetFile);
                        ++$attachmentsCopied;
                    } catch (\Symfony\Component\Filesystem\Exception\IOException $e) {
                        $this->logger->warning('Failed to copy attachment', [
                            'source' => $sourceFile,
                            'target' => $targetFile,
                            'runId' => $runId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        if ($attachmentsCopied > 0) {
            $this->logger->debug('Copied attachments to per-run directory', [
                'runId' => $runId,
                'testName' => $testName,
                'attachmentsCopied' => $attachmentsCopied,
            ]);
        }
    }

    /**
     * Generate report incrementally during test execution.
     * Debounced to avoid overwhelming Allure service with rapid test completions.
     */
    public function generateIncrementalReport(TestRun $run): void
    {
        // Debounce: skip if generated within MIN_INCREMENTAL_REPORT_INTERVAL
        $now = microtime(true);
        if (null !== $this->lastIncrementalReportTime
            && ($now - $this->lastIncrementalReportTime) < self::MIN_INCREMENTAL_REPORT_INTERVAL) {
            $this->logger->debug('Skipping incremental report (debounced)', [
                'runId' => $run->getId(),
                'timeSinceLast' => $now - $this->lastIncrementalReportTime,
            ]);

            return;
        }

        $resultsPath = $this->getAllureResultsPath($run->getId());

        // Skip if no results yet
        if (!$this->filesystem->exists($resultsPath)
            || empty(glob($resultsPath . '/*-result.json'))) {
            return;
        }

        try {
            $this->triggerReportGeneration($run);
            $this->lastIncrementalReportTime = microtime(true);

            $this->logger->info('Incremental Allure report generated', [
                'runId' => $run->getId(),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking: log and continue execution
            $this->logger->warning('Incremental report generation failed (non-blocking)', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up old reports.
     */
    public function cleanupExpired(): int
    {
        $cleaned = 0;
        $basePath = $this->projectDir . '/var/mftf-results/allure-results';

        if (!$this->filesystem->exists($basePath)) {
            return 0;
        }

        $dirs = glob($basePath . '/run-*');
        foreach ($dirs as $dir) {
            // Extract run ID and check if report is expired
            // This should be called with expired reports from database
            // For now, just clean up directories older than 30 days
            if (filemtime($dir) < strtotime('-30 days')) {
                $this->filesystem->remove($dir);
                ++$cleaned;
            }
        }

        return $cleaned;
    }

    /**
     * Copy attachment files referenced in Allure result.
     */
    private function copyAttachments(array $data, string $sourceDir, string $targetDir): void
    {
        $attachments = $data['attachments'] ?? [];

        // Also check steps for attachments
        $this->extractAttachmentsFromSteps($data['steps'] ?? [], $attachments);

        foreach ($attachments as $attachment) {
            $source = $attachment['source'] ?? null;
            if (!$source) {
                continue;
            }

            $sourcePath = $sourceDir . '/' . $source;
            $targetPath = $targetDir . '/' . $source;

            if ($this->filesystem->exists($sourcePath) && !$this->filesystem->exists($targetPath)) {
                $this->filesystem->copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Recursively extract attachments from steps.
     */
    private function extractAttachmentsFromSteps(array $steps, array &$attachments): void
    {
        foreach ($steps as $step) {
            if (!empty($step['attachments'])) {
                $attachments = array_merge($attachments, $step['attachments']);
            }
            if (!empty($step['steps'])) {
                $this->extractAttachmentsFromSteps($step['steps'], $attachments);
            }
        }
    }

    /**
     * Extract all attachment source filenames from a result data structure.
     *
     * @return string[] List of attachment source filenames
     */
    private function extractAllAttachmentSources(array $data): array
    {
        $sources = [];

        // Direct attachments
        foreach ($data['attachments'] ?? [] as $attachment) {
            if (!empty($attachment['source'])) {
                $sources[] = $attachment['source'];
            }
        }

        // Attachments in steps (recursive)
        $this->extractSourcesFromSteps($data['steps'] ?? [], $sources);

        return array_unique($sources);
    }

    /**
     * Recursively extract attachment sources from steps.
     */
    private function extractSourcesFromSteps(array $steps, array &$sources): void
    {
        foreach ($steps as $step) {
            foreach ($step['attachments'] ?? [] as $attachment) {
                if (!empty($attachment['source'])) {
                    $sources[] = $attachment['source'];
                }
            }
            if (!empty($step['steps'])) {
                $this->extractSourcesFromSteps($step['steps'], $sources);
            }
        }
    }

    /**
     * Copy all attachments for a run from root to per-run directory.
     *
     * Called before sending results to Allure to ensure screenshots are included.
     */
    private function copyAllAttachmentsForRun(int $runId): void
    {
        $runDir = $this->getAllureResultsPath($runId);
        $rootDir = $this->projectDir . '/var/mftf-results/allure-results';

        if (!$this->filesystem->exists($runDir)) {
            $this->logger->debug('Per-run Allure directory not found, skipping attachment copy', [
                'runId' => $runId,
                'path' => $runDir,
            ]);

            return;
        }

        // Find all result files in per-run directory
        $resultFiles = glob($runDir . '/*-result.json') ?: [];
        $attachmentsCopied = 0;

        foreach ($resultFiles as $resultFile) {
            $content = file_get_contents($resultFile);
            if (false === $content) {
                $this->logger->warning('Failed to read Allure result file', [
                    'file' => $resultFile,
                    'runId' => $runId,
                ]);

                continue;
            }
            if ('' === $content) {
                continue;
            }

            $data = json_decode($content, true);
            if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
                $this->logger->warning('Failed to parse Allure result JSON', [
                    'file' => $resultFile,
                    'runId' => $runId,
                    'jsonError' => json_last_error_msg(),
                ]);

                continue;
            }
            if (empty($data)) {
                continue;
            }

            // Extract all attachment sources and copy them
            $attachmentSources = $this->extractAllAttachmentSources($data);

            foreach ($attachmentSources as $source) {
                $sourceFile = $rootDir . '/' . $source;
                $targetFile = $runDir . '/' . $source;

                if ($this->filesystem->exists($sourceFile) && !$this->filesystem->exists($targetFile)) {
                    try {
                        $this->filesystem->copy($sourceFile, $targetFile);
                        ++$attachmentsCopied;
                    } catch (\Symfony\Component\Filesystem\Exception\IOException $e) {
                        $this->logger->warning('Failed to copy attachment', [
                            'source' => $sourceFile,
                            'target' => $targetFile,
                            'runId' => $runId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        if ($attachmentsCopied > 0) {
            $this->logger->info('Copied attachments to per-run directory before Allure report', [
                'runId' => $runId,
                'attachmentsCopied' => $attachmentsCopied,
            ]);
        }
    }

    /**
     * Execute HTTP request with exponential backoff retry.
     *
     * @throws \Throwable On final failure after all retries
     */
    private function executeWithRetry(callable $requestFn, string $operation): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; ++$attempt) {
            try {
                $response = $requestFn();
                // Consume response body to free memory (prevent leak)
                if (method_exists($response, 'getContent')) {
                    $response->getContent(false); // false = don't throw on error status
                }

                return $response;
            } catch (\Throwable $e) {
                $lastException = $e;
                $delayMs = self::INITIAL_RETRY_DELAY_MS * (2 ** $attempt); // Exponential backoff

                $this->logger->warning('HTTP request failed, retrying', [
                    'operation' => $operation,
                    'attempt' => $attempt + 1,
                    'maxRetries' => self::MAX_RETRIES,
                    'delayMs' => $delayMs,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES - 1) {
                    usleep($delayMs * 1000); // Convert ms to microseconds
                }
            }
        }

        throw $lastException;
    }

    /**
     * Trigger report generation via Allure Docker service.
     */
    private function triggerReportGeneration(TestRun $run): string
    {
        // Use environment code as project ID for per-environment reports
        $projectId = $run->getEnvironment()->getCode();
        $runId = $run->getId();

        try {
            // Create project if not exists (with retry)
            $this->executeWithRetry(
                fn () => $this->httpClient->request('POST', $this->allureUrl . '/allure-docker-service/projects', [
                    'json' => ['id' => $projectId],
                ]),
                'create_allure_project',
            );
        } catch (\Throwable $e) {
            // Project might already exist, that's ok
            $this->logger->debug('Project creation response', ['error' => $e->getMessage()]);
        }

        // Send results to Allure service
        $resultsPath = $this->getAllureResultsPath($runId);
        $hasResults = false;

        if ($this->filesystem->exists($resultsPath)) {
            $region = $run->getEnvironment()->getRegion() ?: 'US';
            $hasResults = $this->sendResultsToAllure($projectId, $resultsPath, $region);
        }

        // Only generate report if we actually sent result files
        if (!$hasResults) {
            $this->logger->warning('No Allure result files sent; skipping report generation', [
                'runId' => $runId,
                'resultsPath' => $resultsPath,
            ]);

            return $projectId;
        }

        // Generate report (with retry)
        try {
            $this->executeWithRetry(
                fn () => $this->httpClient->request(
                    'GET',
                    $this->allureUrl . '/allure-docker-service/generate-report',
                    ['query' => ['project_id' => $projectId]],
                ),
                'generate_allure_report',
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate Allure report after retries', [
                'runId' => $runId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $projectId;
    }

    /**
     * Extract region group (ES/US) from test name.
     * Returns 'US' for tests without explicit region suffix (legacy compatibility).
     */
    private function extractTestRegionGroup(string $testName): string
    {
        // Pattern: MOEC + optional dash + digits + optional variant chars + ES or US
        // Examples: MOEC2609ES, MOEC-2609ES, MOEC7223US, MOEC7223CUS
        if (preg_match('/\bMOEC[-]?\d+[A-Z]{0,2}?(ES|US)\b/i', $testName, $matches)) {
            return strtoupper($matches[1]);
        }

        // Default to US for tests without region suffix
        return 'US';
    }

    /**
     * Filter result files to only include tests from the target region group.
     *
     * @return array{files: string[], skipped: int}
     */
    private function filterResultsByRegionGroup(array $resultFiles, string $targetRegion): array
    {
        $filtered = [];
        $skipped = 0;
        $targetRegion = strtoupper($targetRegion);

        foreach ($resultFiles as $file) {
            $content = @file_get_contents($file);
            if (!$content) {
                continue;
            }

            $data = json_decode($content, true);
            if (!$data || !isset($data['name'])) {
                continue;
            }

            $testRegion = $this->extractTestRegionGroup($data['name']);

            if ($testRegion === $targetRegion) {
                $filtered[] = $file;
            } else {
                ++$skipped;
                $this->logger->debug('Filtered out test - wrong region group', [
                    'testName' => $data['name'],
                    'testRegion' => $testRegion,
                    'targetRegion' => $targetRegion,
                ]);
            }
        }

        return ['files' => $filtered, 'skipped' => $skipped];
    }

    /**
     * Filter containers to only include those referencing filtered results.
     */
    private function filterContainersByResults(array $containerFiles, array $filteredResultUuids): array
    {
        $filtered = [];

        foreach ($containerFiles as $file) {
            $content = @file_get_contents($file);
            if (!$content) {
                continue;
            }

            $data = json_decode($content, true);
            if (!$data || !isset($data['children'])) {
                continue;
            }

            foreach ($data['children'] as $childUuid) {
                if (in_array($childUuid, $filteredResultUuids, true)) {
                    $filtered[] = $file;

                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Send results files to Allure service in batches to avoid memory exhaustion.
     *
     * @return bool True if result/container files were sent, false if only attachments or nothing
     */
    private function sendResultsToAllure(string $projectId, string $resultsPath, string $region): bool
    {
        // Collect all file paths (without loading content yet)
        $resultFiles = glob($resultsPath . '/*-result.json') ?: [];
        $containerFiles = glob($resultsPath . '/*-container.json') ?: [];
        $attachmentFiles = glob($resultsPath . '/*-attachment') ?: [];

        // Filter results by region group
        $filterResult = $this->filterResultsByRegionGroup($resultFiles, $region);
        $resultFiles = $filterResult['files'];

        $this->logger->info('Filtered results by region group', [
            'projectId' => $projectId,
            'region' => $region,
            'kept' => count($resultFiles),
            'skipped' => $filterResult['skipped'],
        ]);

        // Extract UUIDs from filtered results for container filtering
        $filteredUuids = array_map(
            fn ($file) => basename($file, '-result.json'),
            $resultFiles,
        );

        // Filter containers to match
        $containerFiles = $this->filterContainersByResults($containerFiles, $filteredUuids);

        $hasResultFiles = false;
        foreach (array_merge($resultFiles, $containerFiles) as $file) {
            $size = @filesize($file);
            if (false !== $size && $size > 0) {
                $hasResultFiles = true;

                break;
            }
        }

        if (!$hasResultFiles) {
            $this->logger->warning('No Allure result files found after region filtering', [
                'projectId' => $projectId,
                'region' => $region,
                'resultsPath' => $resultsPath,
            ]);

            return false;
        }

        // Batch size: ~20 files per batch to stay well under memory limit
        $batchSize = 20;
        $allFiles = array_merge($resultFiles, $containerFiles, $attachmentFiles);

        if (empty($allFiles)) {
            return false;
        }

        $this->logger->debug('Sending files to Allure in batches', [
            'projectId' => $projectId,
            'totalFiles' => count($allFiles),
            'batchSize' => $batchSize,
        ]);

        // Process files in batches
        $batches = array_chunk($allFiles, $batchSize);

        foreach ($batches as $batchIndex => $batchFiles) {
            $results = [];

            foreach ($batchFiles as $file) {
                $content = file_get_contents($file);
                if ($content) {
                    $results[] = [
                        'file_name' => basename($file),
                        'content_base64' => base64_encode($content),
                    ];
                }
                // Free memory immediately
                unset($content);
            }

            if (empty($results)) {
                continue;
            }

            $this->logger->debug('Sending batch to Allure', [
                'projectId' => $projectId,
                'batch' => $batchIndex + 1,
                'totalBatches' => count($batches),
                'filesInBatch' => count($results),
            ]);

            try {
                $this->executeWithRetry(
                    fn () => $this->httpClient->request(
                        'POST',
                        $this->allureUrl . '/allure-docker-service/send-results',
                        [
                            'query' => ['project_id' => $projectId],
                            'json' => ['results' => $results],
                        ],
                    ),
                    'send_allure_results',
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send batch to Allure', [
                    'projectId' => $projectId,
                    'batch' => $batchIndex + 1,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            // Free memory after each batch
            unset($results);
        }

        return $hasResultFiles;
    }
}
