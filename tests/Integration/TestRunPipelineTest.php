<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\TestEnvironment;
use App\Entity\TestReport;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use App\Message\TestRunMessage;
use App\Service\AllureReportService;
use App\Service\ArtifactCollectorService;
use App\Service\MftfExecutorService;
use App\Service\ModuleCloneService;
use App\Service\PlaywrightExecutorService;
use App\Service\TestRunnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for the 5-phase test run pipeline.
 */
class TestRunPipelineTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private TestRunnerService $testRunnerService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    // =====================
    // Phase 1: Create Run
    // =====================

    public function testCreateRunPersistsEntityWithPendingStatus(): void
    {
        $env = $this->createTestEnvironment();
        $service = $this->buildTestRunnerService();

        $run = $service->createRun($env, TestRun::TYPE_MFTF, 'MOEC2609');

        $this->assertNotNull($run->getId());
        $this->assertSame(TestRun::STATUS_PENDING, $run->getStatus());
        $this->assertSame(TestRun::TYPE_MFTF, $run->getType());
        $this->assertSame('MOEC2609', $run->getTestFilter());
        $this->assertSame($env->getId(), $run->getEnvironment()->getId());
        $this->assertSame(TestRun::TRIGGER_MANUAL, $run->getTriggeredBy());
        $this->assertTrue($run->isSendNotifications());

        // Verify persisted in DB
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(TestRun::class, $run->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(TestRun::STATUS_PENDING, $reloaded->getStatus());
    }

    public function testCreateRunWithSuiteAndSchedulerTrigger(): void
    {
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuite($env);
        $service = $this->buildTestRunnerService();

        $run = $service->createRun(
            $env,
            TestRun::TYPE_MFTF,
            $suite->getTestPattern(),
            $suite,
            TestRun::TRIGGER_SCHEDULER,
            false,
        );

        $this->assertSame($suite->getId(), $run->getSuite()->getId());
        $this->assertSame(TestRun::TRIGGER_SCHEDULER, $run->getTriggeredBy());
        $this->assertFalse($run->isSendNotifications());
    }

    // =====================
    // Phase 2: Prepare Run
    // =====================

    public function testPrepareRunSetsCloneStatus(): void
    {
        $env = $this->createTestEnvironment();
        $service = $this->buildTestRunnerService(mockModuleSuccess: true);

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $service->prepareRun($run);

        // After successful prepare, status should be CLONING (set during prepare)
        $this->assertSame(TestRun::STATUS_CLONING, $run->getStatus());
    }

    public function testPrepareRunMarksFailedOnCloneError(): void
    {
        $env = $this->createTestEnvironment();
        $moduleClone = $this->createMock(ModuleCloneService::class);
        $moduleClone->method('prepareModule')
            ->willThrowException(new \RuntimeException('Git clone failed'));

        $service = $this->buildTestRunnerService(moduleCloneService: $moduleClone);

        $run = $service->createRun($env, TestRun::TYPE_MFTF);

        $this->expectException(\RuntimeException::class);
        $service->prepareRun($run);

        // Should be marked failed
        $this->assertSame(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertStringContainsString('Git clone failed', $run->getErrorMessage());
    }

    // =====================
    // Phase 3: Execute Run
    // =====================

    public function testExecuteRunWithMftfResults(): void
    {
        $env = $this->createTestEnvironment();

        $mftfExecutor = $this->createMock(MftfExecutorService::class);
        $mftfExecutor->method('execute')->willReturn([
            'output' => 'MFTF output',
            'exitCode' => 0,
        ]);
        $mftfExecutor->method('getOutputFilePath')->willReturn('/tmp/output.txt');
        $mftfExecutor->method('parseResults')->willReturnCallback(function (TestRun $run) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName('TestName');
            $result->setTestId('MOEC2609');
            $result->setStatus(TestResult::STATUS_PASSED);
            $result->setDuration(12.5);

            return [$result];
        });
        $mftfExecutor->method('getAllureResultsPath')->willReturn('/tmp/allure');

        $artifactCollector = $this->createMock(ArtifactCollectorService::class);
        $artifactCollector->method('collectArtifacts')->willReturn(['screenshots' => [], 'html' => []]);
        $artifactCollector->method('clearRootLevelArtifacts');

        $service = $this->buildTestRunnerService(
            mftfExecutor: $mftfExecutor,
            artifactCollectorService: $artifactCollector,
            mockModuleSuccess: true,
        );

        $run = $service->createRun($env, TestRun::TYPE_MFTF, 'MOEC2609');
        $service->executeRun($run);

        $this->assertSame(TestRun::STATUS_COMPLETED, $run->getStatus());
        $this->assertCount(1, $run->getResults());
        $this->assertSame(TestResult::STATUS_PASSED, $run->getResults()->first()->getStatus());
        $this->assertNotNull($run->getStartedAt());
        $this->assertNotNull($run->getCompletedAt());
    }

    public function testExecuteRunMarksFailedWhenAllTestsFail(): void
    {
        $env = $this->createTestEnvironment();

        $mftfExecutor = $this->createMock(MftfExecutorService::class);
        $mftfExecutor->method('execute')->willReturn([
            'output' => 'MFTF output',
            'exitCode' => 1,
        ]);
        $mftfExecutor->method('getOutputFilePath')->willReturn('/tmp/output.txt');
        $mftfExecutor->method('parseResults')->willReturnCallback(function (TestRun $run) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName('FailedTest');
            $result->setTestId('MOEC2610');
            $result->setStatus(TestResult::STATUS_FAILED);
            $result->setErrorMessage('Assertion failed');

            return [$result];
        });
        $mftfExecutor->method('getAllureResultsPath')->willReturn('/tmp/allure');

        $artifactCollector = $this->createMock(ArtifactCollectorService::class);
        $artifactCollector->method('collectArtifacts')->willReturn(['screenshots' => [], 'html' => []]);
        $artifactCollector->method('clearRootLevelArtifacts');

        $service = $this->buildTestRunnerService(
            mftfExecutor: $mftfExecutor,
            artifactCollectorService: $artifactCollector,
            mockModuleSuccess: true,
        );

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $service->executeRun($run);

        $this->assertSame(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertStringContainsString('failed', $run->getErrorMessage());
    }

    // =====================
    // Phase 4: Generate Reports
    // =====================

    public function testGenerateReportsCreatesReportEntity(): void
    {
        $env = $this->createTestEnvironment();

        $allureReport = $this->createMock(AllureReportService::class);
        $allureReport->method('generateReport')->willReturnCallback(function (TestRun $run) {
            $report = new TestReport();
            $report->setTestRun($run);
            $report->setReportType(TestReport::TYPE_ALLURE);
            $report->setFilePath('/var/allure/run-' . $run->getId());
            $report->setPublicUrl('https://allure.example.com/report');
            $report->setGeneratedAt(new \DateTimeImmutable());

            return $report;
        });

        $mftfExecutor = $this->createMock(MftfExecutorService::class);
        $mftfExecutor->method('getAllureResultsPath')->willReturn('/tmp/allure');

        $service = $this->buildTestRunnerService(
            allureReportService: $allureReport,
            mftfExecutor: $mftfExecutor,
            mockModuleSuccess: true,
        );

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $run->markFailed('Test failed');
        $this->entityManager->flush();

        $report = $service->generateReports($run);

        $this->assertNotNull($report);
        $this->assertNotNull($report->getId());
        $this->assertSame(TestReport::TYPE_ALLURE, $report->getReportType());
        $this->assertSame('https://allure.example.com/report', $report->getPublicUrl());

        // Failed runs should preserve failure status
        $this->assertSame(TestRun::STATUS_FAILED, $run->getStatus());
    }

    public function testGenerateReportsMarksCompletedIfNotFailed(): void
    {
        $env = $this->createTestEnvironment();

        $allureReport = $this->createMock(AllureReportService::class);
        $allureReport->method('generateReport')->willReturnCallback(function (TestRun $run) {
            $report = new TestReport();
            $report->setTestRun($run);
            $report->setReportType(TestReport::TYPE_ALLURE);
            $report->setFilePath('/var/allure/run');
            $report->setPublicUrl('https://allure.example.com/report');
            $report->setGeneratedAt(new \DateTimeImmutable());

            return $report;
        });

        $mftfExecutor = $this->createMock(MftfExecutorService::class);
        $mftfExecutor->method('getAllureResultsPath')->willReturn('/tmp/allure');

        $service = $this->buildTestRunnerService(
            allureReportService: $allureReport,
            mftfExecutor: $mftfExecutor,
            mockModuleSuccess: true,
        );

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $run->markCompleted();
        $this->entityManager->flush();

        $service->generateReports($run);

        $this->assertSame(TestRun::STATUS_COMPLETED, $run->getStatus());
    }

    // =====================
    // Phase 5: Cancel + Retry
    // =====================

    public function testCancelRunMarksStatus(): void
    {
        $env = $this->createTestEnvironment();

        $mftfExecutor = $this->createMock(MftfExecutorService::class);
        $mftfExecutor->method('stopRun');

        $service = $this->buildTestRunnerService(mftfExecutor: $mftfExecutor, mockModuleSuccess: true);

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->entityManager->flush();

        $service->cancelRun($run);

        $this->assertSame(TestRun::STATUS_CANCELLED, $run->getStatus());
        $this->assertNotNull($run->getCompletedAt());
    }

    public function testRetryRunCreatesNewRun(): void
    {
        $env = $this->createTestEnvironment();
        $service = $this->buildTestRunnerService();

        $original = $service->createRun($env, TestRun::TYPE_MFTF, 'MOEC2609');
        $original->markFailed('Error');
        $this->entityManager->flush();

        $retry = $service->retryRun($original);

        $this->assertNotSame($original->getId(), $retry->getId());
        $this->assertSame(TestRun::STATUS_PENDING, $retry->getStatus());
        $this->assertSame($original->getType(), $retry->getType());
        $this->assertSame($original->getTestFilter(), $retry->getTestFilter());
        $this->assertSame($original->getEnvironment()->getId(), $retry->getEnvironment()->getId());
    }

    // =====================
    // Pipeline Message Flow
    // =====================

    public function testTestRunMessagePhasesAreValid(): void
    {
        $message = new TestRunMessage(1, 1, TestRunMessage::PHASE_PREPARE);
        $this->assertSame(1, $message->testRunId);
        $this->assertSame(1, $message->environmentId);
        $this->assertSame('prepare', $message->phase);

        $phases = [
            TestRunMessage::PHASE_PREPARE,
            TestRunMessage::PHASE_EXECUTE,
            TestRunMessage::PHASE_REPORT,
            TestRunMessage::PHASE_NOTIFY,
            TestRunMessage::PHASE_CLEANUP,
        ];
        $this->assertCount(5, $phases);
    }

    public function testHasRunningForEnvironment(): void
    {
        $env = $this->createTestEnvironment();
        $service = $this->buildTestRunnerService();

        $this->assertFalse($service->hasRunningForEnvironment($env));

        $run = $service->createRun($env, TestRun::TYPE_MFTF);
        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->entityManager->flush();

        $this->assertTrue($service->hasRunningForEnvironment($env));
    }

    // =====================
    // Helpers
    // =====================

    private function createTestEnvironment(): TestEnvironment
    {
        $env = new TestEnvironment();
        $env->setName('Test Env ' . uniqid());
        $env->setCode('test-' . uniqid());
        $env->setRegion('us-east-1');
        $env->setBaseUrl('https://test.example.com');
        $env->setIsActive(true);
        $this->entityManager->persist($env);
        $this->entityManager->flush();

        return $env;
    }

    private function createTestSuite(TestEnvironment $env): TestSuite
    {
        $suite = new TestSuite();
        $suite->setName('Test Suite ' . uniqid());
        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $suite->setTestPattern('MOEC2609');
        $suite->setIsActive(true);
        $suite->addEnvironment($env);
        $this->entityManager->persist($suite);
        $this->entityManager->flush();

        return $suite;
    }

    private function buildTestRunnerService(
        ?ModuleCloneService $moduleCloneService = null,
        ?MftfExecutorService $mftfExecutor = null,
        ?PlaywrightExecutorService $playwrightExecutor = null,
        ?AllureReportService $allureReportService = null,
        ?ArtifactCollectorService $artifactCollectorService = null,
        bool $mockModuleSuccess = false,
    ): TestRunnerService {
        $container = static::getContainer();

        if (null === $moduleCloneService) {
            $moduleCloneService = $this->createMock(ModuleCloneService::class);
            if ($mockModuleSuccess) {
                $moduleCloneService->method('prepareModule')->willReturn('/tmp/test-module');
                $moduleCloneService->method('getDefaultTargetPath')->willReturn('/tmp/test-module');
            }
        }

        return new TestRunnerService(
            $this->entityManager,
            $container->get('App\Repository\TestRunRepository'),
            $moduleCloneService,
            $mftfExecutor ?? $this->createMock(MftfExecutorService::class),
            $playwrightExecutor ?? $this->createMock(PlaywrightExecutorService::class),
            $allureReportService ?? $this->createMock(AllureReportService::class),
            $artifactCollectorService ?? $this->createMock(ArtifactCollectorService::class),
            $container->get('App\Service\AllureStepParserService'),
            $container->get('logger'),
            $container->get('App\Service\TestDiscoveryService'),
            $container->get('lock.factory'),
        );
    }
}
