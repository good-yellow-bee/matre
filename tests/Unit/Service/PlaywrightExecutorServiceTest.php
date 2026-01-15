<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\PlaywrightExecutorService;
use App\Service\Security\ShellEscapeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PlaywrightExecutorService.
 *
 * Tests command building, output parsing, and result extraction.
 */
class PlaywrightExecutorServiceTest extends TestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&GlobalEnvVariableRepository $envRepository;

    private MockObject&ShellEscapeService $shellEscapeService;

    private PlaywrightExecutorService $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->envRepository = $this->createMock(GlobalEnvVariableRepository::class);
        $this->shellEscapeService = $this->createMock(ShellEscapeService::class);

        $this->service = new PlaywrightExecutorService(
            $this->logger,
            $this->envRepository,
            $this->shellEscapeService,
            '/app',
        );
    }

    // =====================
    // getOutputFilePath() Tests
    // =====================

    public function testGetOutputFilePathReturnsCorrectPath(): void
    {
        $run = $this->createTestRun('test', 42);

        $result = $this->service->getOutputFilePath($run);

        $this->assertEquals('/app/var/test-output/playwright-run-42.log', $result);
    }

    // =====================
    // getOutputPath() Tests
    // =====================

    public function testGetOutputPathReturnsCorrectPath(): void
    {
        $result = $this->service->getOutputPath();

        $this->assertEquals('/app/var/playwright-results', $result);
    }

    // =====================
    // getAllureResultsPath() Tests
    // =====================

    public function testGetAllureResultsPathReturnsCorrectPath(): void
    {
        $result = $this->service->getAllureResultsPath();

        $this->assertEquals('/app/var/playwright-results/allure-results', $result);
    }

    // =====================
    // buildCommand() Tests
    // =====================

    public function testBuildCommandStartsWithCdToModules(): void
    {
        $run = $this->createTestRun();

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $this->shellEscapeService->expects($this->once())
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringStartsWith('cd /app/modules', $command);
    }

    public function testBuildCommandIncludesFilter(): void
    {
        $run = $this->createTestRun('login test');

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $this->shellEscapeService->expects($this->atLeastOnce())
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('--grep', $command);
        $this->assertStringContainsString('login test', $command);
    }

    public function testBuildCommandWithoutFilter(): void
    {
        $run = $this->createTestRun(null);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $this->shellEscapeService->expects($this->atLeastOnce())
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringNotContainsString('--grep', $command);
    }

    public function testBuildCommandIncludesAllureReporter(): void
    {
        $run = $this->createTestRun();

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $this->shellEscapeService->expects($this->atLeastOnce())
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('--reporter=allure-playwright', $command);
        $this->assertStringContainsString('--output=/app/results', $command);
    }

    public function testBuildCommandExportsBaseUrl(): void
    {
        $env = $this->createTestEnvironment('prod', 'https://prod.example.com');
        $run = $this->createTestRun('test', 1, $env);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        // Note: TestEnvironment adds trailing slash to baseUrl
        $this->shellEscapeService->expects($this->once())
            ->method('buildExportStatement')
            ->with('BASE_URL', $this->stringContains('prod.example.com'))
            ->willReturn('export BASE_URL="https://prod.example.com/"');

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('export BASE_URL=', $command);
    }

    public function testBuildCommandExportsAdminCredentials(): void
    {
        $env = $this->createTestEnvironment();
        $env->setAdminUsername('admin_user');
        $env->setAdminPassword('admin_pass');
        $run = $this->createTestRun('test', 1, $env);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $this->shellEscapeService->expects($this->exactly(3))
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('ADMIN_USERNAME', $command);
        $this->assertStringContainsString('ADMIN_PASSWORD', $command);
    }

    public function testBuildCommandIncludesGlobalEnvVariables(): void
    {
        $run = $this->createTestRun();

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->with('stage-us')
            ->willReturn([
                'TEST_VAR' => 'test_value',
                'ANOTHER_VAR' => 'another_value',
            ]);

        $this->shellEscapeService->expects($this->exactly(3))
            ->method('buildExportStatement')
            ->willReturnCallback(fn ($k, $v) => "export {$k}=\"{$v}\"");

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('export TEST_VAR', $command);
        $this->assertStringContainsString('export ANOTHER_VAR', $command);
    }

    public function testBuildCommandSkipsInvalidVariables(): void
    {
        $run = $this->createTestRun();

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([
                'VALID_VAR' => 'value',
                'INVALID_VAR' => 'bad',
            ]);

        $this->shellEscapeService->expects($this->atLeast(2))
            ->method('buildExportStatement')
            ->willReturnCallback(function ($key, $value) {
                if ('INVALID_VAR' === $key) {
                    throw new \InvalidArgumentException('Invalid variable');
                }

                return "export {$key}=\"{$value}\"";
            });

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Skipping invalid environment variable', $this->anything());

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('VALID_VAR', $command);
    }

    // =====================
    // parseResults() Tests
    // =====================

    public function testParseResultsExtractsPassedTest(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            Running tests...
              ✓ login test (1.5s)
              ✓ checkout test (2.0s)
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(2, $results);
        $this->assertEquals('login test', $results[0]->getTestName());
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
        $this->assertEqualsWithDelta(1.5, $results[0]->getDuration(), 0.01);
    }

    public function testParseResultsExtractsFailedTest(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
              ✘ broken test (500ms)
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals('broken test', $results[0]->getTestName());
        $this->assertEquals(TestResult::STATUS_FAILED, $results[0]->getStatus());
        $this->assertEqualsWithDelta(0.5, $results[0]->getDuration(), 0.01);
    }

    public function testParseResultsExtractsSkippedTest(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
              ○ skipped test (0ms)
              - another skipped (10ms)
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(2, $results);
        $this->assertEquals(TestResult::STATUS_SKIPPED, $results[0]->getStatus());
        $this->assertEquals(TestResult::STATUS_SKIPPED, $results[1]->getStatus());
    }

    public function testParseResultsHandlesMixedStatuses(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
              ✓ test 1 (1s)
              ✘ test 2 (2s)
              ○ test 3 (0ms)
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(3, $results);
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
        $this->assertEquals(TestResult::STATUS_FAILED, $results[1]->getStatus());
        $this->assertEquals(TestResult::STATUS_SKIPPED, $results[2]->getStatus());
    }

    public function testParseResultsHandlesMilliseconds(): void
    {
        $run = $this->createTestRun();

        $output = "  ✓ fast test (250ms)\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(0.25, $results[0]->getDuration(), 0.01);
    }

    public function testParseResultsHandlesSeconds(): void
    {
        $run = $this->createTestRun();

        $output = "  ✓ slow test (5.5s)\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(5.5, $results[0]->getDuration(), 0.01);
    }

    public function testParseResultsReturnsEmptyForNoMatches(): void
    {
        $run = $this->createTestRun();

        $output = "No tests found matching pattern.\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertEmpty($results);
    }

    public function testParseResultsHandlesComplexTestNames(): void
    {
        $run = $this->createTestRun();

        $output = "  ✓ should login with valid credentials and redirect to dashboard (3.2s)\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(
            'should login with valid credentials and redirect to dashboard',
            $results[0]->getTestName(),
        );
    }

    private function createTestEnvironment(
        string $name = 'stage-us',
        string $baseUrl = 'https://stage.example.com',
    ): TestEnvironment {
        $env = new TestEnvironment();
        $env->setName($name);
        $env->setBaseUrl($baseUrl);

        return $env;
    }

    private function createTestRun(
        ?string $filter = 'login test',
        int $id = 1,
        ?TestEnvironment $env = null,
    ): TestRun {
        $run = new TestRun();
        $run->setEnvironment($env ?? $this->createTestEnvironment());
        $run->setType(TestRun::TYPE_PLAYWRIGHT);
        $run->setTestFilter($filter);

        $reflection = new \ReflectionClass($run);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($run, $id);

        return $run;
    }
}
