<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestReport;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use App\Repository\TestRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates test execution workflow.
 */
class TestRunnerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestRunRepository $testRunRepository,
        private readonly ModuleCloneService $moduleCloneService,
        private readonly MftfExecutorService $mftfExecutor,
        private readonly PlaywrightExecutorService $playwrightExecutor,
        private readonly AllureReportService $allureReportService,
        private readonly ArtifactCollectorService $artifactCollector,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new test run.
     */
    public function createRun(
        TestEnvironment $environment,
        string $type,
        ?string $testFilter = null,
        ?TestSuite $suite = null,
        string $triggeredBy = TestRun::TRIGGER_MANUAL,
    ): TestRun {
        $run = new TestRun();
        $run->setEnvironment($environment);
        $run->setType($type);
        $run->setTestFilter($testFilter);
        $run->setSuite($suite);
        $run->setTriggeredBy($triggeredBy);
        $run->setStatus(TestRun::STATUS_PENDING);

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        $this->logger->info('Test run created', [
            'id' => $run->getId(),
            'type' => $type,
            'environment' => $environment->getName(),
        ]);

        return $run;
    }

    /**
     * Prepare the test run (clone module, set up environment).
     */
    public function prepareRun(TestRun $run): void
    {
        $this->logger->info('Preparing test run', ['id' => $run->getId()]);

        $run->setStatus(TestRun::STATUS_PREPARING);
        $this->entityManager->flush();

        // Clear artifact source directories to prevent contamination from previous runs
        $this->artifactCollector->clearSourceDirectories();

        try {
            // Clone module to run-specific directory
            $run->setStatus(TestRun::STATUS_CLONING);
            $this->entityManager->flush();

            $targetPath = $this->moduleCloneService->getRunTargetPath($run->getId());
            $this->moduleCloneService->cloneModule($targetPath);

            $this->logger->info('Test run prepared', ['id' => $run->getId()]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to prepare test run', [
                'id' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            $run->markFailed($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Execute the test run.
     */
    public function executeRun(TestRun $run): void
    {
        $this->logger->info('Executing test run', ['id' => $run->getId()]);

        $run->markStarted();
        $this->entityManager->flush();

        // Set status to RUNNING and output file path before test execution
        $run->setStatus(TestRun::STATUS_RUNNING);
        $type = $run->getType();

        // Set output file path so live streaming can find it
        if ($type === TestRun::TYPE_MFTF || $type === TestRun::TYPE_BOTH) {
            $run->setOutputFilePath($this->mftfExecutor->getOutputFilePath($run));
        } elseif ($type === TestRun::TYPE_PLAYWRIGHT) {
            $run->setOutputFilePath($this->playwrightExecutor->getOutputFilePath($run));
        }
        $this->entityManager->flush();

        $allResults = [];
        $allurePaths = [];
        $output = '';
        $executionFailed = false;
        $failureReason = '';

        try {
            // Execute MFTF tests
            if ($type === TestRun::TYPE_MFTF || $type === TestRun::TYPE_BOTH) {
                $mftfResult = $this->mftfExecutor->execute($run);
                $output .= "=== MFTF Output ===\n" . $mftfResult['output'] . "\n\n";

                // Check for fatal errors that prevent test execution
                $isFatalError = preg_match('/ERROR: \d+ Test\(s\) failed to generate/i', $mftfResult['output'])
                    || preg_match('/is not available under/i', $mftfResult['output']);

                // ALWAYS parse results (even on failure) to capture partial test data
                $mftfResults = $this->mftfExecutor->parseResults($run, $mftfResult['output']);
                foreach ($mftfResults as $result) {
                    $run->addResult($result);
                    $this->entityManager->persist($result);
                    $allResults[] = $result;
                }

                $allurePaths[] = $this->mftfExecutor->getAllureResultsPath();

                // Track failure but don't exit early - continue to collect artifacts
                if ($mftfResult['exitCode'] !== 0) {
                    $executionFailed = true;
                    if ($isFatalError) {
                        if (preg_match('/ERROR: \d+ Test\(s\) failed to generate/i', $mftfResult['output'])) {
                            $failureReason = 'MFTF test generation failed - see output log';
                        } else {
                            $failureReason = 'Generated test file not found - see output log';
                        }
                    } else {
                        // Normal test failure (tests ran but some failed)
                        $failedCount = count(array_filter($mftfResults, fn ($r) => $r->isFailed()));
                        $failureReason = $failedCount > 0
                            ? sprintf('%d test(s) failed', $failedCount)
                            : 'MFTF execution failed with exit code ' . $mftfResult['exitCode'];
                    }
                }
            }

            // Execute Playwright tests
            if ($type === TestRun::TYPE_PLAYWRIGHT || $type === TestRun::TYPE_BOTH) {
                $playwrightResult = $this->playwrightExecutor->execute($run);
                $output .= "=== Playwright Output ===\n" . $playwrightResult['output'] . "\n\n";

                // ALWAYS parse results (even on failure) to capture partial test data
                $playwrightResults = $this->playwrightExecutor->parseResults($run, $playwrightResult['output']);
                foreach ($playwrightResults as $result) {
                    $run->addResult($result);
                    $this->entityManager->persist($result);
                    $allResults[] = $result;
                }

                $allurePaths[] = $this->playwrightExecutor->getAllureResultsPath();

                // Track failure but don't exit early
                if ($playwrightResult['exitCode'] !== 0) {
                    $executionFailed = true;
                    $failedCount = count(array_filter($playwrightResults, fn ($r) => $r->isFailed()));
                    $pwReason = $failedCount > 0
                        ? sprintf('%d test(s) failed', $failedCount)
                        : 'Playwright execution failed with exit code ' . $playwrightResult['exitCode'];
                    $failureReason = $failureReason ? $failureReason . '; ' . $pwReason : $pwReason;
                }
            }

            $run->setOutput($output);

            // ALWAYS collect artifacts (even on failure) - screenshots/HTML are valuable for debugging
            $artifacts = $this->artifactCollector->collectArtifacts($run);
            if (!empty($artifacts['screenshots'])) {
                $this->artifactCollector->associateScreenshotsWithResults($allResults, $artifacts['screenshots']);
            }

            $this->entityManager->flush();

            // Mark failed AFTER collecting all data
            if ($executionFailed) {
                $run->markFailed($failureReason);
                $this->entityManager->flush();
            }

            $this->logger->info('Test execution completed', [
                'id' => $run->getId(),
                'resultCount' => count($allResults),
                'failed' => $executionFailed,
                'artifacts' => [
                    'screenshots' => count($artifacts['screenshots']),
                    'html' => count($artifacts['html']),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Test execution failed', [
                'id' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            $run->setOutput($output . "\n\nERROR: " . $e->getMessage());
            $run->markFailed($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Generate reports for the test run.
     */
    public function generateReports(TestRun $run): TestReport
    {
        $this->logger->info('Generating reports', ['id' => $run->getId()]);

        $run->setStatus(TestRun::STATUS_REPORTING);
        $this->entityManager->flush();

        try {
            $allurePaths = [];
            $type = $run->getType();

            if ($type === TestRun::TYPE_MFTF || $type === TestRun::TYPE_BOTH) {
                $allurePaths[] = $this->mftfExecutor->getAllureResultsPath();
            }

            if ($type === TestRun::TYPE_PLAYWRIGHT || $type === TestRun::TYPE_BOTH) {
                $allurePaths[] = $this->playwrightExecutor->getAllureResultsPath();
            }

            try {
                $report = $this->allureReportService->generateReport($run, $allurePaths);
            } catch (\Throwable $e) {
                $this->logger->warning('Allure report generation failed, creating placeholder', [
                    'id' => $run->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Create placeholder report - run still completes
                $report = new TestReport();
                $report->setTestRun($run);
                $report->setReportType(TestReport::TYPE_ALLURE);
                $report->setFilePath('');
                $report->setPublicUrl('');
                $report->setGeneratedAt(new \DateTimeImmutable());
            }
            $this->entityManager->persist($report);

            // Mark run as completed
            $run->markCompleted();
            $this->entityManager->flush();

            $this->logger->info('Reports generated', [
                'id' => $run->getId(),
                'reportUrl' => $report->getPublicUrl(),
            ]);

            // Clear source directories after report generation
            $this->artifactCollector->clearSourceDirectories();

            return $report;
        } catch (\Throwable $e) {
            $this->logger->error('Report generation failed', [
                'id' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            $run->markFailed('Report generation failed: ' . $e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Cancel a running or pending test run.
     */
    public function cancelRun(TestRun $run): void
    {
        if (!$run->canBeCancelled()) {
            throw new \RuntimeException('Test run cannot be cancelled in current state');
        }

        $this->logger->info('Cancelling test run', ['id' => $run->getId()]);

        $run->setStatus(TestRun::STATUS_CANCELLED);
        $this->entityManager->flush();

        // Clean up module directory
        $targetPath = $this->moduleCloneService->getRunTargetPath($run->getId());
        $this->moduleCloneService->cleanup($targetPath);
    }

    /**
     * Retry a failed test run.
     */
    public function retryRun(TestRun $originalRun): TestRun
    {
        return $this->createRun(
            $originalRun->getEnvironment(),
            $originalRun->getType(),
            $originalRun->getTestFilter(),
            $originalRun->getSuite(),
            TestRun::TRIGGER_MANUAL,
        );
    }

    /**
     * Clean up resources for a completed test run.
     */
    public function cleanupRun(TestRun $run): void
    {
        $targetPath = $this->moduleCloneService->getRunTargetPath($run->getId());
        $this->moduleCloneService->cleanup($targetPath);

        $this->logger->info('Test run cleaned up', ['id' => $run->getId()]);
    }

    /**
     * Get test runs currently running for an environment.
     */
    public function hasRunningForEnvironment(TestEnvironment $environment): bool
    {
        return $this->testRunRepository->hasRunningForEnvironment($environment);
    }
}
