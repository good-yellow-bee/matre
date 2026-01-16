<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\MagentoContainerPoolService;
use App\Service\MftfExecutorService;
use App\Service\Security\ShellEscapeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for MftfExecutorService.
 *
 * Tests command building, output parsing, and result extraction.
 */
class MftfExecutorServiceTest extends TestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&GlobalEnvVariableRepository $envRepository;

    private MockObject&ShellEscapeService $shellEscapeService;

    private MockObject&MagentoContainerPoolService $containerPool;

    private MftfExecutorService $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->envRepository = $this->createMock(GlobalEnvVariableRepository::class);
        $this->shellEscapeService = $this->createMock(ShellEscapeService::class);
        $this->containerPool = $this->createMock(MagentoContainerPoolService::class);

        // Mock container pool to return a fixed container name
        $this->containerPool->method('getContainerForEnvironment')
            ->willReturn('matre_magento_env_1');

        $this->service = new MftfExecutorService(
            $this->logger,
            $this->envRepository,
            $this->shellEscapeService,
            $this->containerPool,
            '/app',
            'selenium-hub',
            4444,
            '/var/www/html',
            'app/code/SiiPoland/Catalog',
        );
    }

    // =====================
    // getOutputFilePath() Tests
    // =====================

    public function testGetOutputFilePathReturnsCorrectPath(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 42);

        $result = $this->service->getOutputFilePath($run);

        $this->assertEquals('/app/var/test-output/mftf-run-42.log', $result);
    }

    // =====================
    // getOutputPath() Tests
    // =====================

    public function testGetOutputPathReturnsCorrectPath(): void
    {
        $result = $this->service->getOutputPath();

        $this->assertEquals('/app/var/mftf-results', $result);
    }

    // =====================
    // getAllureResultsPath() Tests
    // =====================

    public function testGetAllureResultsPathReturnsCorrectPathWithRunId(): void
    {
        $result = $this->service->getAllureResultsPath(42);

        $this->assertEquals('/app/var/mftf-results/allure-results/run-42', $result);
    }

    // =====================
    // buildCommand() Tests
    // =====================

    public function testBuildCommandThrowsWhenFilterEmpty(): void
    {
        $run = $this->createTestRun('', 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MFTF requires a test filter');

        $this->service->buildCommand($run);
    }

    public function testBuildCommandIncludesSymlinkCreation(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('ln -sf', $command);
        $this->assertStringContainsString('/var/www/html/app/code/TestModule', $command);
        $this->assertStringContainsString('/var/www/html/app/code/SiiPoland/Catalog', $command);
    }

    public function testBuildCommandIncludesFilterInMftfRun(): void
    {
        $run = $this->createTestRun('AdminLoginTest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString("run:test 'AdminLoginTest'", $command);
    }

    public function testBuildCommandIncludesSeleniumConfiguration(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        // 2 calls: SELENIUM_HOST and SELENIUM_PORT
        $this->shellEscapeService->expects($this->exactly(2))
            ->method('buildEnvFileLine')
            ->willReturnCallback(function ($key, $value) {
                return "{$key}={$value}";
            });

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('SELENIUM_HOST', $command);
        $this->assertStringContainsString('SELENIUM_PORT', $command);
    }

    public function testBuildCommandWithGlobalEnvVariables(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->with('stage-us')
            ->willReturn([
                'MAGENTO_BASE_URL' => 'https://stage.example.com',
                'MAGENTO_ADMIN_USERNAME' => 'admin',
            ]);

        $this->shellEscapeService->expects($this->atLeastOnce())
            ->method('buildEnvFileLine')
            ->willReturnCallback(function ($key, $value) {
                return "{$key}=\"{$value}\"";
            });

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('Global variables', $command);
    }

    public function testBuildCommandSkipsInvalidEnvVariables(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([
                'VALID_VAR' => 'value',
                'INVALID_VAR' => 'bad value',
            ]);

        $this->shellEscapeService->expects($this->atLeast(3))
            ->method('buildEnvFileLine')
            ->willReturnCallback(function ($key, $value) {
                if ('INVALID_VAR' === $key) {
                    throw new \InvalidArgumentException('Invalid variable name');
                }

                return "{$key}=\"{$value}\"";
            });

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Skipping invalid environment variable', $this->anything());

        $command = $this->service->buildCommand($run);

        $this->assertStringContainsString('VALID_VAR', $command);
    }

    public function testBuildCommandIncludesArtifactMoveCommand(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 42);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $command = $this->service->buildCommand($run);

        // Should have artifact move command at the end with ; separator
        $this->assertStringContainsString('; mkdir -p', $command);
        $this->assertStringContainsString('tests/_output/run-42', $command);
        $this->assertStringContainsString('-name "*.png"', $command);
    }

    public function testBuildCommandIncludesAllureExtensionFix(): void
    {
        $run = $this->createTestRun('MOEC5157Cest', 1);

        $this->envRepository->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $command = $this->service->buildCommand($run);

        // Should include command to ensure AllureCodeception is enabled
        $this->assertStringContainsString('grep -q', $command);
        $this->assertStringContainsString('sed -i', $command);
        $this->assertStringContainsString('AllureCodeception', $command);

        // Should set per-run outputDirectory for Allure result isolation
        $this->assertStringContainsString("sed -i 's|outputDirectory: allure-results\$|outputDirectory: allure-results/run-1|'", $command);
    }

    // =====================
    // parseResults() Tests
    // =====================

    public function testParseResultsExtractsPassedTest(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            Running MFTF tests...
            MOEC5157Cest: Moec5157
            I am on page "/admin/admin"
            I fill field "#username" "admin"
            I fill field "#password" "password123"
            I click "button.action-primary"
            PASSED

            Time: 01:23.456
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals('MOEC5157Cest:Moec5157', $results[0]->getTestName());
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
        $this->assertEquals('MOEC5157', $results[0]->getTestId());
    }

    public function testParseResultsExtractsFailedTest(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC2417Cest: Moec2417
            I am on page "/checkout"
            I see element ".totals"
            Element ".discount" was not found
            FAIL

            Time: 02:15.789
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals('MOEC2417Cest:Moec2417', $results[0]->getTestName());
        $this->assertEquals(TestResult::STATUS_FAILED, $results[0]->getStatus());
    }

    public function testParseResultsExtractsMultipleTests(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC5157Cest: Moec5157
            I am on page "/admin"
            PASSED

            MOEC2417Cest: Moec2417
            I am on page "/checkout"
            FAIL

            MOEC3333Cest: Moec3333
            I am on page "/cart"
            PASSED

            Time: 05:30.000
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(3, $results);
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
        $this->assertEquals(TestResult::STATUS_FAILED, $results[1]->getStatus());
        $this->assertEquals(TestResult::STATUS_PASSED, $results[2]->getStatus());
    }

    public function testParseResultsHandlesAnsiColorCodes(): void
    {
        $run = $this->createTestRun();

        $output = "\033[32mMOEC5157Cest:\033[0m Moec5157\n" .
                  "I am on page \"/admin\"\n" .
                  "\033[32mPASSED\033[0m\n\n" .
                  'Time: 01:00.000';

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
    }

    public function testParseResultsHandlesErrorStatus(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC9999Cest: Moec9999
            I am on page "/admin"
            [Exception] Something went wrong
            ERROR

            Time: 00:30.000
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(TestResult::STATUS_BROKEN, $results[0]->getStatus());
    }

    public function testParseResultsHandlesSkipStatus(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC7777Cest: Moec7777
            Test skipped due to precondition
            SKIP

            Time: 00:05.000
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(TestResult::STATUS_SKIPPED, $results[0]->getStatus());
    }

    public function testParseResultsFallbackUsesSignatureLines(): void
    {
        $run = $this->createTestRun();

        // Output format where block matching fails but Signature lines exist
        $output = <<<'OUTPUT'
            Codeception PHP Testing Framework v5.0.0

            Tests\Functional\FooTest
            ...some steps...
            Signature: App\Tests\Functional\MOEC5157Cest:Moec5157

            OK (1 test, 7 assertions)
            Time: 01:00.000
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals('MOEC5157Cest:Moec5157', $results[0]->getTestName());
        $this->assertEquals(TestResult::STATUS_PASSED, $results[0]->getStatus());
    }

    public function testParseResultsCreatesBrokenResultOnError(): void
    {
        $run = $this->createTestRun('MOEC2417Cest');

        // Output with error but no parseable test results
        $output = <<<'OUTPUT'
            [Exception]
            Something went terribly wrong in MOEC2417Cest

            Fatal error: Class not found
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(TestResult::STATUS_BROKEN, $results[0]->getStatus());
        $this->assertEquals('MOEC2417', $results[0]->getTestId());
    }

    public function testParseResultsExtractsDuration(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC5157Cest: Moec5157
            PASSED

            Time: 01:30.500
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(90.5, $results[0]->getDuration(), 0.01);
    }

    public function testParseResultsDistributesDurationEvenly(): void
    {
        $run = $this->createTestRun();

        $output = <<<'OUTPUT'
            MOEC1111Cest: Moec1111
            PASSED

            MOEC2222Cest: Moec2222
            PASSED

            Time: 02:00.000
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(2, $results);
        // 120 seconds / 2 tests = 60 seconds each
        $this->assertEqualsWithDelta(60.0, $results[0]->getDuration(), 0.01);
        $this->assertEqualsWithDelta(60.0, $results[1]->getDuration(), 0.01);
    }

    public function testParseResultsReturnsEmptyArrayForNoMatches(): void
    {
        $run = $this->createTestRun();

        $output = "No tests were run.\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertEmpty($results);
    }

    public function testParseResultsDetectsGenerationFailure(): void
    {
        $run = $this->createTestRun('MOEC5157Cest');

        $output = <<<'OUTPUT'
            Generating tests...
            ERROR: 2 Test(s) failed to generate

            Module_Something is not available under Magento/FunctionalTest
            OUTPUT;

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals(TestResult::STATUS_BROKEN, $results[0]->getStatus());
    }

    public function testParseResultsDoesNotUseNonMatchingFilterAsTestId(): void
    {
        // Filter "CustomTestName" doesn't match pattern [A-Z]+\d+ so should NOT be used as testId
        // This prevents group names like "us" from appearing as test names
        $run = $this->createTestRun('CustomTestName');

        $output = "[Exception]\nUnknown error occurred\n";

        $results = $this->service->parseResults($run, $output);

        $this->assertCount(1, $results);
        $this->assertEquals('Unknown', $results[0]->getTestName());
    }

    private function createTestEnvironment(string $name = 'stage-us'): TestEnvironment
    {
        $env = new TestEnvironment();
        $env->setName($name);

        return $env;
    }

    private function createTestRun(
        string $filter = 'MOEC5157Cest',
        int $id = 1,
        ?TestEnvironment $env = null,
    ): TestRun {
        $run = new TestRun();
        $run->setEnvironment($env ?? $this->createTestEnvironment());
        $run->setType(TestRun::TYPE_MFTF);
        $run->setTestFilter($filter);

        $reflection = new \ReflectionClass($run);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($run, $id);

        return $run;
    }
}
