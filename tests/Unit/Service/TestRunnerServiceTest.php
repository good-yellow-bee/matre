<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestReport;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use App\Repository\TestRunRepository;
use App\Service\AllureReportService;
use App\Service\AllureStepParserService;
use App\Service\ArtifactCollectorService;
use App\Service\MftfExecutorService;
use App\Service\ModuleCloneService;
use App\Service\PlaywrightExecutorService;
use App\Service\TestDiscoveryService;
use App\Service\TestRunnerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Unit tests for TestRunnerService.
 *
 * Tests the 5-phase orchestration pipeline: create, prepare, execute, report, cleanup.
 */
class TestRunnerServiceTest extends TestCase
{
    private MockObject&EntityManagerInterface $entityManager;

    private MockObject&TestRunRepository $testRunRepository;

    private MockObject&ModuleCloneService $moduleCloneService;

    private MockObject&MftfExecutorService $mftfExecutor;

    private MockObject&PlaywrightExecutorService $playwrightExecutor;

    private MockObject&AllureReportService $allureReportService;

    private MockObject&ArtifactCollectorService $artifactCollector;

    private MockObject&AllureStepParserService $allureStepParser;

    private MockObject&LoggerInterface $logger;

    private MockObject&TestDiscoveryService $testDiscovery;

    private MockObject&LockFactory $lockFactory;

    private MockObject&SharedLockInterface $lock;

    private TestRunnerService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->testRunRepository = $this->createMock(TestRunRepository::class);
        $this->moduleCloneService = $this->createMock(ModuleCloneService::class);
        $this->mftfExecutor = $this->createMock(MftfExecutorService::class);
        $this->playwrightExecutor = $this->createMock(PlaywrightExecutorService::class);
        $this->allureReportService = $this->createMock(AllureReportService::class);
        $this->artifactCollector = $this->createMock(ArtifactCollectorService::class);
        $this->allureStepParser = $this->createMock(AllureStepParserService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->testDiscovery = $this->createMock(TestDiscoveryService::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(SharedLockInterface::class);

        $this->service = new TestRunnerService(
            $this->entityManager,
            $this->testRunRepository,
            $this->moduleCloneService,
            $this->mftfExecutor,
            $this->playwrightExecutor,
            $this->allureReportService,
            $this->artifactCollector,
            $this->allureStepParser,
            $this->logger,
            $this->testDiscovery,
            $this->lockFactory,
        );
    }

    // =====================
    // createRun() Tests
    // =====================

    public function testCreateRunCreatesNewTestRun(): void
    {
        $env = $this->createTestEnvironment();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(TestRun::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createRun($env, TestRun::TYPE_MFTF);

        $this->assertInstanceOf(TestRun::class, $result);
        $this->assertSame($env, $result->getEnvironment());
        $this->assertEquals(TestRun::TYPE_MFTF, $result->getType());
        $this->assertEquals(TestRun::STATUS_PENDING, $result->getStatus());
        $this->assertEquals(TestRun::TRIGGER_MANUAL, $result->getTriggeredBy());
    }

    public function testCreateRunWithTestFilter(): void
    {
        $env = $this->createTestEnvironment();

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createRun($env, TestRun::TYPE_MFTF, 'AdminLoginTest');

        $this->assertEquals('AdminLoginTest', $result->getTestFilter());
    }

    public function testCreateRunWithSuite(): void
    {
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuite('Smoke Tests');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createRun($env, TestRun::TYPE_MFTF, null, $suite);

        $this->assertSame($suite, $result->getSuite());
    }

    public function testCreateRunWithSchedulerTrigger(): void
    {
        $env = $this->createTestEnvironment();

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createRun(
            $env,
            TestRun::TYPE_BOTH,
            null,
            null,
            TestRun::TRIGGER_SCHEDULER,
        );

        $this->assertEquals(TestRun::TRIGGER_SCHEDULER, $result->getTriggeredBy());
    }

    public function testCreateRunLogsCreation(): void
    {
        $env = $this->createTestEnvironment();

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Test run created', $this->callback(function ($context) {
                return isset($context['type']) && $context['type'] === TestRun::TYPE_MFTF
                    && isset($context['environment']) && $context['environment'] === 'test-env';
            }));

        $this->service->createRun($env, TestRun::TYPE_MFTF);
    }

    // =====================
    // prepareRun() Tests
    // =====================

    public function testPrepareRunClonesModule(): void
    {
        $run = $this->createTestRun();

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->with(1)
            ->willReturn('/var/test-modules/run-1');

        $this->moduleCloneService->expects($this->once())
            ->method('cloneModule')
            ->with('/var/test-modules/run-1');

        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->service->prepareRun($run);

        $this->assertEquals(TestRun::STATUS_CLONING, $run->getStatus());
    }

    public function testPrepareRunTransitionsThroughStatuses(): void
    {
        $run = $this->createTestRun();
        $statusTransitions = [];

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush')
            ->willReturnCallback(function () use ($run, &$statusTransitions) {
                $statusTransitions[] = $run->getStatus();
            });

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->with(1)
            ->willReturn('/var/test-modules/run-1');
        $this->moduleCloneService->expects($this->once())
            ->method('cloneModule');

        $this->service->prepareRun($run);

        $this->assertContains(TestRun::STATUS_PREPARING, $statusTransitions);
        $this->assertContains(TestRun::STATUS_CLONING, $statusTransitions);
    }

    public function testPrepareRunMarksFailedOnException(): void
    {
        $run = $this->createTestRun();

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->with(1)
            ->willReturn('/var/test-modules/run-1');

        $this->moduleCloneService->expects($this->once())
            ->method('cloneModule')
            ->willThrowException(new \RuntimeException('Git clone failed'));

        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Git clone failed');

        try {
            $this->service->prepareRun($run);
        } catch (\RuntimeException $e) {
            $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
            $this->assertEquals('Git clone failed', $run->getErrorMessage());

            throw $e;
        }
    }

    public function testPrepareRunLogsError(): void
    {
        $run = $this->createTestRun();

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->with(1)
            ->willReturn('/var/test-modules/run-1');

        $this->moduleCloneService->expects($this->once())
            ->method('cloneModule')
            ->willThrowException(new \RuntimeException('Clone failed'));

        $this->logger->expects($this->atLeastOnce())
            ->method($this->logicalOr($this->equalTo('info'), $this->equalTo('error')));

        $this->expectException(\RuntimeException::class);
        $this->service->prepareRun($run);
    }

    // =====================
    // executeRun() Tests - MFTF Only
    // =====================

    public function testExecuteRunMftfOnlySuccess(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->with($run)
            ->willReturn('/var/test-output/run-1.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->with($run, null)
            ->willReturn(['output' => 'MFTF test output', 'exitCode' => 0]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->with($run, 'MFTF test output')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-results');

        $this->playwrightExecutor->expects($this->never())->method('execute');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->with($run)
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->entityManager->expects($this->atLeast(2))->method('flush');

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_RUNNING, $run->getStatus());
        $this->assertStringContainsString('MFTF test output', $run->getOutput());
        $this->assertEquals('/var/test-output/run-1.txt', $run->getOutputFilePath());
    }

    public function testExecuteRunMftfWithResults(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $result1 = new TestResult();
        $result1->setTestName('Test1');
        $result1->setStatus(TestResult::STATUS_PASSED);

        $result2 = new TestResult();
        $result2->setTestName('Test2');
        $result2->setStatus(TestResult::STATUS_PASSED);

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'test output', 'exitCode' => 0]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$result1, $result2]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(TestResult::class));

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertCount(2, $run->getResults());
    }

    public function testExecuteRunMftfWithFailedTests(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $passedResult = new TestResult();
        $passedResult->setTestName('PassedTest');
        $passedResult->setStatus(TestResult::STATUS_PASSED);

        $failedResult = new TestResult();
        $failedResult->setTestName('FailedTest');
        $failedResult->setStatus(TestResult::STATUS_FAILED);

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'test output', 'exitCode' => 1]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$passedResult, $failedResult]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertStringContainsString('1 test(s) failed', $run->getErrorMessage());
    }

    public function testExecuteRunMftfGenerationFailure(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'ERROR: 2 Test(s) failed to generate',
                'exitCode' => 1,
            ]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertEquals('MFTF test generation failed - see output log', $run->getErrorMessage());
    }

    public function testExecuteRunMftfModuleNotFound(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Module_Something is not available under Magento/FunctionalTest',
                'exitCode' => 1,
            ]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertEquals('Generated test file not found - see output log', $run->getErrorMessage());
    }

    // =====================
    // executeRun() Tests - Playwright Only
    // =====================

    public function testExecuteRunPlaywrightOnlySuccess(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_PLAYWRIGHT);
        $this->setupLockMock();

        $this->playwrightExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->with($run)
            ->willReturn('/var/playwright-output.txt');

        $this->playwrightExecutor->expects($this->once())
            ->method('execute')
            ->with($run, null)
            ->willReturn(['output' => 'Playwright test output', 'exitCode' => 0]);

        $this->playwrightExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-pw');

        $this->mftfExecutor->expects($this->never())->method('execute');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertStringContainsString('Playwright test output', $run->getOutput());
        $this->assertEquals('/var/playwright-output.txt', $run->getOutputFilePath());
    }

    public function testExecuteRunPlaywrightWithFailedTests(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_PLAYWRIGHT);
        $this->setupLockMock();

        $failedResult = new TestResult();
        $failedResult->setTestName('FailedPWTest');
        $failedResult->setStatus(TestResult::STATUS_FAILED);

        $this->playwrightExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->playwrightExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'PW output', 'exitCode' => 1]);

        $this->playwrightExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$failedResult]);

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertStringContainsString('1 test(s) failed', $run->getErrorMessage());
    }

    // =====================
    // executeRun() Tests - Both Types
    // =====================

    public function testExecuteRunBothTypesSuccess(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_BOTH);
        $this->setupLockMock();

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/mftf-output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'MFTF output', 'exitCode' => 0]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-mftf');

        $this->playwrightExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'Playwright output', 'exitCode' => 0]);

        $this->playwrightExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-pw');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertStringContainsString('=== MFTF Output ===', $run->getOutput());
        $this->assertStringContainsString('=== Playwright Output ===', $run->getOutput());
    }

    public function testExecuteRunBothTypesMftfFailsPlaywrightSucceeds(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_BOTH);
        $this->setupLockMock();

        $mftfFailed = new TestResult();
        $mftfFailed->setTestName('MftfFailed');
        $mftfFailed->setStatus(TestResult::STATUS_FAILED);

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'MFTF output', 'exitCode' => 1]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$mftfFailed]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $pwPassed = new TestResult();
        $pwPassed->setTestName('PwPassed');
        $pwPassed->setStatus(TestResult::STATUS_PASSED);

        $this->playwrightExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'PW output', 'exitCode' => 0]);

        $this->playwrightExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$pwPassed]);

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertStringContainsString('1 test(s) failed', $run->getErrorMessage());
    }

    public function testExecuteRunBothTypesBothFail(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_BOTH);
        $this->setupLockMock();

        $mftfFailed = new TestResult();
        $mftfFailed->setTestName('MftfFailed');
        $mftfFailed->setStatus(TestResult::STATUS_FAILED);

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'MFTF output', 'exitCode' => 1]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$mftfFailed]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $pwFailed = new TestResult();
        $pwFailed->setTestName('PwFailed');
        $pwFailed->setStatus(TestResult::STATUS_FAILED);

        $this->playwrightExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'PW output', 'exitCode' => 1]);

        $this->playwrightExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$pwFailed]);

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        // Both failure reasons should be concatenated
        $this->assertStringContainsString('1 test(s) failed', $run->getErrorMessage());
        $this->assertStringContainsString('; ', $run->getErrorMessage());
    }

    // =====================
    // executeRun() Tests - Lock Management
    // =====================

    public function testExecuteRunAcquiresLockWithCorrectKey(): void
    {
        $env = $this->createTestEnvironment(42, 'test-env');
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_PENDING, $env);

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with('mftf_execution_env_42', 3600)
            ->willReturn($this->lock);

        $this->lock->expects($this->once())
            ->method('acquire')
            ->with(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'test', 'exitCode' => 0]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run);
    }

    public function testExecuteRunReleasesLockOnException(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($this->lock);

        $this->lock->expects($this->once())->method('acquire');
        $this->lock->expects($this->once())->method('release');

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('Execution failed'));

        $this->expectException(\RuntimeException::class);
        $this->service->executeRun($run);
    }

    // =====================
    // executeRun() Tests - Output Callback
    // =====================

    public function testExecuteRunPassesOutputCallback(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $callback = function (string $output): void {};

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->with($run, $callback)
            ->willReturn(['output' => 'test', 'exitCode' => 0]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->willReturn(['screenshots' => [], 'html' => []]);

        $this->service->executeRun($run, $callback);
    }

    // =====================
    // executeRun() Tests - Artifact Collection
    // =====================

    public function testExecuteRunCollectsArtifactsEvenOnFailure(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $failedResult = new TestResult();
        $failedResult->setTestName('FailedTest');
        $failedResult->setStatus(TestResult::STATUS_FAILED);

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(['output' => 'failed', 'exitCode' => 1]);

        $this->mftfExecutor->expects($this->once())
            ->method('parseResults')
            ->willReturn([$failedResult]);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->artifactCollector->expects($this->once())
            ->method('collectArtifacts')
            ->with($run)
            ->willReturn([
                'screenshots' => ['/var/screenshots/1.png'],
                'html' => ['/var/html/1.html'],
            ]);

        $this->artifactCollector->expects($this->once())
            ->method('associateScreenshotsWithResults')
            ->with([$failedResult], ['/var/screenshots/1.png']);

        $this->service->executeRun($run);

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
    }

    public function testExecuteRunExceptionMarksFailedWithMessage(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF);
        $this->setupLockMock();

        $this->mftfExecutor->expects($this->once())
            ->method('getOutputFilePath')
            ->willReturn('/var/output.txt');

        $this->mftfExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('Docker connection failed'));

        $this->expectException(\RuntimeException::class);

        try {
            $this->service->executeRun($run);
        } catch (\RuntimeException $e) {
            $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
            $this->assertStringContainsString('Docker connection failed', $run->getOutput());
            $this->assertEquals('Docker connection failed', $run->getErrorMessage());

            throw $e;
        }
    }

    // =====================
    // generateReports() Tests
    // =====================

    public function testGenerateReportsCreatesReport(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_RUNNING);

        $report = new TestReport();
        $report->setTestRun($run);
        $report->setReportType(TestReport::TYPE_ALLURE);
        $report->setFilePath('/var/reports/run-1');
        $report->setPublicUrl('https://example.com/reports/run-1');

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-results');

        $this->allureReportService->expects($this->once())
            ->method('generateReport')
            ->with($run, ['/var/allure-results'])
            ->willReturn($report);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($report);

        $this->entityManager->expects($this->exactly(2))->method('flush');

        $result = $this->service->generateReports($run);

        $this->assertSame($report, $result);
        $this->assertEquals(TestRun::STATUS_COMPLETED, $run->getStatus());
    }

    public function testGenerateReportsSetsStatusReporting(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_RUNNING);
        $statusDuringGeneration = null;

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->allureReportService->expects($this->once())
            ->method('generateReport')
            ->willReturnCallback(function () use ($run, &$statusDuringGeneration) {
                $statusDuringGeneration = $run->getStatus();

                $report = new TestReport();
                $report->setTestRun($run);
                $report->setReportType(TestReport::TYPE_ALLURE);

                return $report;
            });

        $this->service->generateReports($run);

        $this->assertEquals(TestRun::STATUS_REPORTING, $statusDuringGeneration);
    }

    public function testGenerateReportsCreatesPlaceholderOnAllureFailure(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_RUNNING);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure');

        $this->allureReportService->expects($this->once())
            ->method('generateReport')
            ->willThrowException(new \RuntimeException('Allure service unavailable'));

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TestReport $report) {
                return $report->getFilePath() === ''
                    && $report->getPublicUrl() === ''
                    && $report->getReportType() === TestReport::TYPE_ALLURE;
            }));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Allure report generation failed, creating placeholder', $this->anything());

        $result = $this->service->generateReports($run);

        $this->assertEquals('', $result->getFilePath());
        $this->assertEquals(TestRun::STATUS_COMPLETED, $run->getStatus());
    }

    public function testGenerateReportsBothTypesCollectsAllPaths(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_BOTH, TestRun::STATUS_RUNNING);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-mftf');

        $this->playwrightExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willReturn('/var/allure-pw');

        $this->allureReportService->expects($this->once())
            ->method('generateReport')
            ->with($run, ['/var/allure-mftf', '/var/allure-pw'])
            ->willReturn((new TestReport())->setTestRun($run)->setReportType(TestReport::TYPE_ALLURE));

        $this->service->generateReports($run);
    }

    public function testGenerateReportsMarksFailedOnException(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_RUNNING);

        $this->mftfExecutor->expects($this->once())
            ->method('getAllureResultsPath')
            ->willThrowException(new \RuntimeException('Fatal error'));

        $this->expectException(\RuntimeException::class);

        try {
            $this->service->generateReports($run);
        } catch (\RuntimeException $e) {
            $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
            $this->assertStringContainsString('Report generation failed', $run->getErrorMessage());

            throw $e;
        }
    }

    // =====================
    // cancelRun() Tests
    // =====================

    public function testCancelRunCancelsAndCleansUp(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_PENDING);

        // Make it cancellable
        $reflection = new \ReflectionClass($run);
        $method = $reflection->getMethod('canBeCancelled');

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->willReturn('/var/test-modules/run-1');

        $this->moduleCloneService->expects($this->once())
            ->method('cleanup')
            ->with('/var/test-modules/run-1');

        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Cancelling test run', $this->anything());

        $this->service->cancelRun($run);

        $this->assertEquals(TestRun::STATUS_CANCELLED, $run->getStatus());
    }

    public function testCancelRunThrowsIfNotCancellable(): void
    {
        $run = $this->createTestRun(TestRun::TYPE_MFTF, TestRun::STATUS_COMPLETED);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test run cannot be cancelled in current state');

        $this->service->cancelRun($run);
    }

    // =====================
    // retryRun() Tests
    // =====================

    public function testRetryRunCreatesNewRun(): void
    {
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuite();

        $originalRun = new TestRun();
        $originalRun->setEnvironment($env);
        $originalRun->setType(TestRun::TYPE_BOTH);
        $originalRun->setTestFilter('SomeTest');
        $originalRun->setSuite($suite);
        $originalRun->setTriggeredBy(TestRun::TRIGGER_SCHEDULER);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $newRun = $this->service->retryRun($originalRun);

        $this->assertSame($env, $newRun->getEnvironment());
        $this->assertEquals(TestRun::TYPE_BOTH, $newRun->getType());
        $this->assertEquals('SomeTest', $newRun->getTestFilter());
        $this->assertSame($suite, $newRun->getSuite());
        $this->assertEquals(TestRun::TRIGGER_MANUAL, $newRun->getTriggeredBy());
    }

    // =====================
    // cleanupRun() Tests
    // =====================

    public function testCleanupRunRemovesDirectory(): void
    {
        $run = $this->createTestRun();

        $this->moduleCloneService->expects($this->once())
            ->method('getRunTargetPath')
            ->willReturn('/var/test-modules/run-1');

        $this->moduleCloneService->expects($this->once())
            ->method('cleanup')
            ->with('/var/test-modules/run-1');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Test run cleaned up', $this->anything());

        $this->service->cleanupRun($run);
    }

    // =====================
    // hasRunningForEnvironment() Tests
    // =====================

    public function testHasRunningForEnvironmentDelegatesToRepository(): void
    {
        $env = $this->createTestEnvironment();

        $this->testRunRepository->expects($this->once())
            ->method('hasRunningForEnvironment')
            ->with($env)
            ->willReturn(true);

        $result = $this->service->hasRunningForEnvironment($env);

        $this->assertTrue($result);
    }

    public function testHasRunningForEnvironmentReturnsFalse(): void
    {
        $env = $this->createTestEnvironment();

        $this->testRunRepository->expects($this->once())
            ->method('hasRunningForEnvironment')
            ->with($env)
            ->willReturn(false);

        $result = $this->service->hasRunningForEnvironment($env);

        $this->assertFalse($result);
    }

    private function createTestEnvironment(int $id = 1, string $name = 'test-env'): TestEnvironment
    {
        $env = new TestEnvironment();
        $reflection = new \ReflectionClass($env);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($env, $id);
        $env->setName($name);

        return $env;
    }

    private function createTestSuite(string $name = 'test-suite'): TestSuite
    {
        $suite = new TestSuite();
        $suite->setName($name);

        return $suite;
    }

    private function createTestRun(
        string $type = TestRun::TYPE_MFTF,
        string $status = TestRun::STATUS_PENDING,
        ?TestEnvironment $env = null,
        int $id = 1,
    ): TestRun {
        $run = new TestRun();
        $run->setEnvironment($env ?? $this->createTestEnvironment());
        $run->setType($type);
        $run->setStatus($status);

        // Set ID via reflection (normally set by Doctrine)
        $reflection = new \ReflectionClass($run);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($run, $id);

        return $run;
    }

    private function setupLockMock(): void
    {
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($this->lock);

        $this->lock->expects($this->once())
            ->method('acquire')
            ->with(true);

        $this->lock->expects($this->once())
            ->method('release');
    }
}
