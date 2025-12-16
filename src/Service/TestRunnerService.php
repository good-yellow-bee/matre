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

        $allResults = [];
        $allurePaths = [];
        $output = '';

        try {
            $type = $run->getType();

            // Execute MFTF tests
            if ($type === TestRun::TYPE_MFTF || $type === TestRun::TYPE_BOTH) {
                $mftfResult = $this->mftfExecutor->execute($run);
                $output .= "=== MFTF Output ===\n" . $mftfResult['output'] . "\n\n";

                // Check for execution failure
                if ($mftfResult['exitCode'] !== 0) {
                    $run->setOutput($output);

                    // Determine specific error message
                    if (preg_match('/ERROR: \d+ Test\(s\) failed to generate/i', $mftfResult['output'])) {
                        $run->markFailed('MFTF test generation failed - see output log');
                    } elseif (preg_match('/is not available under/i', $mftfResult['output'])) {
                        $run->markFailed('Generated test file not found - see output log');
                    } else {
                        $run->markFailed('MFTF execution failed with exit code ' . $mftfResult['exitCode']);
                    }
                    $this->entityManager->flush();

                    return;
                }

                $mftfResults = $this->mftfExecutor->parseResults($run, $mftfResult['output']);
                foreach ($mftfResults as $result) {
                    $run->addResult($result);
                    $this->entityManager->persist($result);
                    $allResults[] = $result;
                }

                $allurePaths[] = $this->mftfExecutor->getAllureResultsPath();
            }

            // Execute Playwright tests
            if ($type === TestRun::TYPE_PLAYWRIGHT || $type === TestRun::TYPE_BOTH) {
                $playwrightResult = $this->playwrightExecutor->execute($run);
                $output .= "=== Playwright Output ===\n" . $playwrightResult['output'] . "\n\n";

                // Check for execution failure
                if ($playwrightResult['exitCode'] !== 0) {
                    $run->setOutput($output);
                    $run->markFailed('Playwright execution failed with exit code ' . $playwrightResult['exitCode']);
                    $this->entityManager->flush();

                    return;
                }

                $playwrightResults = $this->playwrightExecutor->parseResults($run, $playwrightResult['output']);
                foreach ($playwrightResults as $result) {
                    $run->addResult($result);
                    $this->entityManager->persist($result);
                    $allResults[] = $result;
                }

                $allurePaths[] = $this->playwrightExecutor->getAllureResultsPath();
            }

            $run->setOutput($output);

            // Collect artifacts (screenshots, HTML) and associate with results
            $artifacts = $this->artifactCollector->collectArtifacts($run);
            if (!empty($artifacts['screenshots'])) {
                $this->artifactCollector->associateScreenshotsWithResults($allResults, $artifacts['screenshots']);
            }

            // Clear source directories AFTER collection to prevent data loss in concurrent runs
            $this->artifactCollector->clearSourceDirectories();

            $this->entityManager->flush();

            $this->logger->info('Test execution completed', [
                'id' => $run->getId(),
                'resultCount' => count($allResults),
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

            $report = $this->allureReportService->generateReport($run, $allurePaths);
            $this->entityManager->persist($report);

            // Mark run as completed
            $run->markCompleted();
            $this->entityManager->flush();

            $this->logger->info('Reports generated', [
                'id' => $run->getId(),
                'reportUrl' => $report->getPublicUrl(),
            ]);

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
