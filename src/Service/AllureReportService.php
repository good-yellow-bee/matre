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
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
        private readonly string $allureUrl,
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

        // Merge results from different sources if multiple paths provided
        if (count($resultPaths) > 1) {
            $this->mergeResults($resultPaths, $allureResultsPath);
        } elseif (count($resultPaths) === 1) {
            $this->filesystem->mirror($resultPaths[0], $allureResultsPath);
        }

        // Generate report via Allure service
        $reportId = $this->triggerReportGeneration($runId);

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

        foreach ($sourcePaths as $sourcePath) {
            if (!$this->filesystem->exists($sourcePath)) {
                continue;
            }

            // Copy all files from source to target
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
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
     * Trigger report generation via Allure Docker service.
     */
    private function triggerReportGeneration(int $runId): string
    {
        $projectId = 'run-' . $runId;

        try {
            // Create project if not exists
            $this->httpClient->request('POST', $this->allureUrl . '/allure-docker-service/projects', [
                'json' => ['id' => $projectId],
            ]);
        } catch (\Throwable $e) {
            // Project might already exist, that's ok
            $this->logger->debug('Project creation response', ['error' => $e->getMessage()]);
        }

        // Send results to Allure service
        $resultsPath = $this->getAllureResultsPath($runId);
        if ($this->filesystem->exists($resultsPath)) {
            $this->sendResultsToAllure($projectId, $resultsPath);
        }

        // Generate report
        try {
            $this->httpClient->request(
                'GET',
                $this->allureUrl . '/allure-docker-service/generate-report',
                ['query' => ['project_id' => $projectId]]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to generate Allure report', [
                'error' => $e->getMessage(),
            ]);
        }

        return $projectId;
    }

    /**
     * Send results files to Allure service.
     */
    private function sendResultsToAllure(string $projectId, string $resultsPath): void
    {
        $files = glob($resultsPath . '/*-result.json');
        if (empty($files)) {
            return;
        }

        $results = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $results[] = [
                    'file_name' => basename($file),
                    'content_base64' => base64_encode($content),
                ];
            }
        }

        if (!empty($results)) {
            $this->httpClient->request(
                'POST',
                $this->allureUrl . '/allure-docker-service/send-results',
                [
                    'query' => ['project_id' => $projectId],
                    'json' => ['results' => $results],
                ]
            );
        }
    }

    /**
     * Get public URL for a report.
     */
    public function getReportUrl(string $projectId): string
    {
        return $this->allureUrl . '/allure-docker-service/projects/' . $projectId . '/reports/latest';
    }

    /**
     * Get path to Allure results for a run.
     */
    public function getAllureResultsPath(int $runId): string
    {
        return $this->projectDir . '/var/allure-results/run-' . $runId;
    }

    /**
     * Clean up old reports.
     */
    public function cleanupExpired(): int
    {
        $cleaned = 0;
        $basePath = $this->projectDir . '/var/allure-results';

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
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
