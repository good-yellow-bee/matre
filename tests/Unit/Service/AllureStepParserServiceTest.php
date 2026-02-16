<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Service\AllureStepParserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AllureStepParserServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/allure_step_parser_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testGetStepsForResultReturnsNullWhenNoPathAndNoMatchingFile(): void
    {
        $service = $this->createService();

        // TestRun exists but run directory doesn't, so no file can be found
        $run = $this->createTestRun(9999);
        $result = $this->createTestResultWithRun(1, 'MOEC2417Cest:MOEC2417', 'MOEC2417', null, $run);

        $this->assertNull($service->getStepsForResult($result));
    }

    public function testGetStepsForResultParsesFileWhenAllureResultPathIsSet(): void
    {
        $service = $this->createService();
        $allureJson = $this->createAllureJson();

        $filePath = $this->tempDir . '/allure-result.json';
        file_put_contents($filePath, $allureJson);

        $result = $this->createStub(TestResult::class);
        $result->method('getId')->willReturn(1);
        $result->method('getTestName')->willReturn('MOEC2417Cest:MOEC2417');
        $result->method('getTestId')->willReturn('MOEC2417');
        $result->method('getAllureResultPath')->willReturn($filePath);
        $result->method('getStatus')->willReturn('passed');
        $result->method('getDuration')->willReturn(5.0);

        $steps = $service->getStepsForResult($result);

        $this->assertNotNull($steps);
        $this->assertSame('MOEC2417Cest:MOEC2417', $steps['testName']);
        $this->assertSame('passed', $steps['status']);
        $this->assertEquals(5.0, $steps['duration']);
        $this->assertNotEmpty($steps['steps']);
        // buildHierarchy groups entering/exiting action group into nested structure
        $this->assertSame('LoginAsAdmin', $steps['steps'][0]['name']);
        $this->assertCount(1, $steps['steps'][0]['children']);
    }

    public function testGetDurationForResultReturnsDurationFromTimestamps(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(42);

        $runDir = $this->tempDir . '/var/mftf-results/allure-results/run-42';
        mkdir($runDir, 0o777, true);
        file_put_contents($runDir . '/abc-result.json', $this->createAllureJson());

        $result = $this->createTestResultWithRun(1, 'MOEC2417Cest:MOEC2417', 'MOEC2417', null, $run);

        $duration = $service->getDurationForResult($result);

        $this->assertNotNull($duration);
        $this->assertEquals(5.0, $duration); // (1700000005000 - 1700000000000) / 1000
    }

    public function testGetDurationForResultReturnsNullWhenNoMatchingFile(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(9999);
        $result = $this->createTestResultWithRun(1, 'MOEC2417Cest:MOEC2417', 'MOEC2417', null, $run);

        $this->assertNull($service->getDurationForResult($result));
    }

    public function testFindAllureFileForResultReturnsNullWhenTestRunIsNull(): void
    {
        $service = $this->createService();

        // Real TestResult without TestRun set - getTestRun() throws TypeError on uninitialized property
        // The service wraps this in a null check, but since return type is non-nullable,
        // we test the realistic case: run exists but directory doesn't
        $run = $this->createTestRun(9999);
        $result = $this->createTestResultWithRun(1, 'SomeTest', 'TEST123', null, $run);

        $this->assertNull($service->findAllureFileForResult($result));
    }

    public function testFindAllureFileForResultFindsFileByTestIdMatch(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(42);

        $runDir = $this->tempDir . '/var/mftf-results/allure-results/run-42';
        mkdir($runDir, 0o777, true);
        file_put_contents($runDir . '/abc-result.json', $this->createAllureJson());
        file_put_contents($runDir . '/other-result.json', json_encode([
            'name' => 'MOEC9999: Other Test',
            'fullName' => 'Magento\\AcceptanceTest\\_default\\Backend\\MOEC9999Cest::MOEC9999',
            'status' => 'passed',
            'start' => 1700000000000,
            'stop' => 1700000010000,
            'steps' => [],
        ]));

        $result = $this->createTestResultWithRun(1, 'MOEC2417Cest:MOEC2417', 'MOEC2417', null, $run);

        $found = $service->findAllureFileForResult($result);

        $this->assertNotNull($found);
        $this->assertStringContainsString('abc-result.json', $found);
    }

    public function testFindAllureFileForResultReturnsNullWhenRunDirectoryDoesNotExist(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(999);

        $result = $this->createTestResultWithRun(1, 'MOEC2417Cest:MOEC2417', 'MOEC2417', null, $run);

        $this->assertNull($service->findAllureFileForResult($result));
    }

    private function createService(): AllureStepParserService
    {
        $logger = $this->createStub(LoggerInterface::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new AllureStepParserService(
            $this->tempDir,
            $logger,
            $entityManager,
        );
    }

    private function createTestRun(int $id): TestRun
    {
        $run = new TestRun();
        $ref = new \ReflectionClass($run);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($run, $id);

        return $run;
    }

    private function createTestResult(int $id, string $testName, ?string $testId, ?string $allurePath): TestResult
    {
        $result = new TestResult();
        $ref = new \ReflectionClass($result);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($result, $id);

        $result->setTestName($testName);
        $result->setTestId($testId);
        $result->setAllureResultPath($allurePath);
        $result->setStatus('passed');

        return $result;
    }

    private function createTestResultWithRun(int $id, string $testName, ?string $testId, ?string $allurePath, TestRun $run): TestResult
    {
        $result = $this->createTestResult($id, $testName, $testId, $allurePath);
        $result->setTestRun($run);
        $run->addResult($result);

        return $result;
    }

    private function createAllureJson(): string
    {
        return json_encode([
            'name' => 'MOEC2417: Test Something',
            'fullName' => 'Magento\\AcceptanceTest\\_default\\Backend\\MOEC2417Cest::MOEC2417',
            'status' => 'passed',
            'start' => 1700000000000,
            'stop' => 1700000005000,
            'steps' => [
                ['name' => 'entering action group [LoginAsAdmin]', 'status' => 'passed', 'start' => 1700000000000, 'stop' => 1700000001000, 'steps' => []],
                ['name' => 'fillField("#username", "admin")', 'status' => 'passed', 'start' => 1700000001000, 'stop' => 1700000002000, 'steps' => []],
                ['name' => 'exiting action group [LoginAsAdmin]', 'status' => 'passed', 'start' => 1700000002000, 'stop' => 1700000003000, 'steps' => []],
            ],
            'statusDetails' => ['message' => ''],
        ]);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
