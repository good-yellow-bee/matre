<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TestEnvironment;
use App\Entity\TestReport;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TestRun entity.
 */
class TestRunTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getId());
        $this->assertEquals(TestRun::STATUS_PENDING, $run->getStatus());
        $this->assertEquals(TestRun::TRIGGERED_BY_MANUAL, $run->getTriggeredBy());
        $this->assertInstanceOf(\DateTimeImmutable::class, $run->getCreatedAt());
        $this->assertNull($run->getUpdatedAt());
        $this->assertNull($run->getStartedAt());
        $this->assertNull($run->getCompletedAt());
        $this->assertCount(0, $run->getResults());
        $this->assertCount(0, $run->getReports());
    }

    public function testEnvironmentGetterAndSetter(): void
    {
        $run = new TestRun();
        $env = $this->createMockEnvironment();

        $result = $run->setEnvironment($env);

        $this->assertSame($env, $run->getEnvironment());
        $this->assertSame($run, $result);
    }

    public function testSuiteGetterAndSetter(): void
    {
        $run = new TestRun();
        $suite = $this->createStub(TestSuite::class);

        $run->setSuite($suite);
        $this->assertSame($suite, $run->getSuite());

        $run->setSuite(null);
        $this->assertNull($run->getSuite());
    }

    public function testTypeGetterAndSetter(): void
    {
        $run = new TestRun();

        $run->setType(TestRun::TYPE_MFTF);
        $this->assertEquals(TestRun::TYPE_MFTF, $run->getType());
        $this->assertEquals('MFTF', $run->getTypeLabel());

        $run->setType(TestRun::TYPE_PLAYWRIGHT);
        $this->assertEquals('Playwright', $run->getTypeLabel());

        $run->setType(TestRun::TYPE_BOTH);
        $this->assertEquals('Both', $run->getTypeLabel());
    }

    public function testStatusGetterAndSetter(): void
    {
        $run = new TestRun();

        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->assertEquals(TestRun::STATUS_RUNNING, $run->getStatus());
        $this->assertEquals('Running Tests', $run->getStatusLabel());

        $run->setStatus(TestRun::STATUS_COMPLETED);
        $this->assertEquals('Completed', $run->getStatusLabel());
    }

    public function testTestFilterGetterAndSetter(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getTestFilter());

        $run->setTestFilter('MOEC1625');
        $this->assertEquals('MOEC1625', $run->getTestFilter());
    }

    public function testTriggeredByGetterAndSetter(): void
    {
        $run = new TestRun();

        $run->setTriggeredBy(TestRun::TRIGGERED_BY_SCHEDULER);
        $this->assertEquals(TestRun::TRIGGERED_BY_SCHEDULER, $run->getTriggeredBy());

        $run->setTriggeredBy(TestRun::TRIGGERED_BY_API);
        $this->assertEquals(TestRun::TRIGGERED_BY_API, $run->getTriggeredBy());
    }

    public function testErrorMessageGetterAndSetter(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getErrorMessage());

        $run->setErrorMessage('Connection failed');
        $this->assertEquals('Connection failed', $run->getErrorMessage());
    }

    public function testOutputGetterAndSetterTruncates(): void
    {
        $run = new TestRun();

        $run->setOutput('Short output');
        $this->assertEquals('Short output', $run->getOutput());

        // Test truncation (over 100KB)
        $longOutput = str_repeat('a', 110000);
        $run->setOutput($longOutput);
        $this->assertStringContainsString('[truncated]', $run->getOutput());
        $this->assertLessThan(110000, strlen($run->getOutput()));
    }

    public function testAppendOutput(): void
    {
        $run = new TestRun();

        $run->setOutput('Line 1');
        $run->appendOutput("\nLine 2");

        $this->assertEquals("Line 1\nLine 2", $run->getOutput());
    }

    public function testProcessPidGetterAndSetter(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getProcessPid());

        $run->setProcessPid(12345);
        $this->assertEquals(12345, $run->getProcessPid());
    }

    public function testOutputFilePathGetterAndSetter(): void
    {
        $run = new TestRun();

        $run->setOutputFilePath('/tmp/output.log');
        $this->assertEquals('/tmp/output.log', $run->getOutputFilePath());
    }

    public function testHasActiveProcess(): void
    {
        $run = new TestRun();

        $this->assertFalse($run->hasActiveProcess());

        $run->setProcessPid(12345);
        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->assertTrue($run->hasActiveProcess());

        $run->setStatus(TestRun::STATUS_COMPLETED);
        $this->assertFalse($run->hasActiveProcess());
    }

    public function testResultsCollection(): void
    {
        $run = new TestRun();
        $result = $this->createMock(TestResult::class);
        $result->expects($this->once())->method('setTestRun')->with($run);

        $run->addResult($result);
        $this->assertCount(1, $run->getResults());
        $this->assertTrue($run->getResults()->contains($result));

        // Adding same result again shouldn't duplicate
        $run->addResult($result);
        $this->assertCount(1, $run->getResults());

        $run->removeResult($result);
        $this->assertCount(0, $run->getResults());
    }

    public function testReportsCollection(): void
    {
        $run = new TestRun();
        $report = $this->createMock(TestReport::class);
        $report->expects($this->once())->method('setTestRun')->with($run);

        $run->addReport($report);
        $this->assertCount(1, $run->getReports());

        $run->removeReport($report);
        $this->assertCount(0, $run->getReports());
    }

    public function testIsFinished(): void
    {
        $run = new TestRun();

        $run->setStatus(TestRun::STATUS_PENDING);
        $this->assertFalse($run->isFinished());

        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->assertFalse($run->isFinished());

        $run->setStatus(TestRun::STATUS_COMPLETED);
        $this->assertTrue($run->isFinished());

        $run->setStatus(TestRun::STATUS_FAILED);
        $this->assertTrue($run->isFinished());

        $run->setStatus(TestRun::STATUS_CANCELLED);
        $this->assertTrue($run->isFinished());
    }

    public function testIsRunning(): void
    {
        $run = new TestRun();

        $run->setStatus(TestRun::STATUS_PENDING);
        $this->assertFalse($run->isRunning());

        $run->setStatus(TestRun::STATUS_PREPARING);
        $this->assertTrue($run->isRunning());

        $run->setStatus(TestRun::STATUS_CLONING);
        $this->assertTrue($run->isRunning());

        $run->setStatus(TestRun::STATUS_WAITING);
        $this->assertTrue($run->isRunning());

        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->assertTrue($run->isRunning());

        $run->setStatus(TestRun::STATUS_REPORTING);
        $this->assertTrue($run->isRunning());

        $run->setStatus(TestRun::STATUS_COMPLETED);
        $this->assertFalse($run->isRunning());
    }

    public function testCanBeCancelled(): void
    {
        $run = new TestRun();

        $run->setStatus(TestRun::STATUS_PENDING);
        $this->assertTrue($run->canBeCancelled());

        $run->setStatus(TestRun::STATUS_RUNNING);
        $this->assertTrue($run->canBeCancelled());

        $run->setStatus(TestRun::STATUS_COMPLETED);
        $this->assertFalse($run->canBeCancelled());
    }

    public function testGetDuration(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getDuration());

        $start = new \DateTimeImmutable('2024-01-01 10:00:00');
        $end = new \DateTimeImmutable('2024-01-01 10:05:30');

        $run->setStartedAt($start);
        $run->setCompletedAt($end);

        $this->assertEquals(330, $run->getDuration());
    }

    public function testGetDurationFormatted(): void
    {
        $run = new TestRun();

        $this->assertNull($run->getDurationFormatted());

        $run->setStartedAt(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $run->setCompletedAt(new \DateTimeImmutable('2024-01-01 10:00:45'));
        $this->assertEquals('45s', $run->getDurationFormatted());

        $run->setCompletedAt(new \DateTimeImmutable('2024-01-01 10:02:30'));
        $this->assertEquals('2m 30s', $run->getDurationFormatted());

        $run->setCompletedAt(new \DateTimeImmutable('2024-01-01 11:15:45'));
        $this->assertEquals('1h 15m 45s', $run->getDurationFormatted());
    }

    public function testGetResultCounts(): void
    {
        $run = new TestRun();

        $passedResult = $this->createStub(TestResult::class);
        $passedResult->method('getStatus')->willReturn('passed');

        $failedResult = $this->createStub(TestResult::class);
        $failedResult->method('getStatus')->willReturn('failed');

        $skippedResult = $this->createStub(TestResult::class);
        $skippedResult->method('getStatus')->willReturn('skipped');

        // Use reflection to add results without calling setTestRun
        $reflection = new \ReflectionClass($run);
        $property = $reflection->getProperty('results');
        $property->setValue($run, new \Doctrine\Common\Collections\ArrayCollection([
            $passedResult,
            $passedResult,
            $failedResult,
            $skippedResult,
        ]));

        $counts = $run->getResultCounts();

        $this->assertEquals(2, $counts['passed']);
        $this->assertEquals(1, $counts['failed']);
        $this->assertEquals(1, $counts['skipped']);
        $this->assertEquals(4, $counts['total']);
    }

    public function testMarkExecutionStarted(): void
    {
        $run = new TestRun();

        $run->markExecutionStarted();

        $this->assertEquals(TestRun::STATUS_RUNNING, $run->getStatus());
        $this->assertNotNull($run->getStartedAt());
    }

    public function testMarkCompleted(): void
    {
        $run = new TestRun();

        $run->markCompleted();

        $this->assertEquals(TestRun::STATUS_COMPLETED, $run->getStatus());
        $this->assertNotNull($run->getCompletedAt());
        $this->assertNull($run->getErrorMessage());
    }

    public function testMarkCompletedClearsStaleErrorMessage(): void
    {
        $run = new TestRun();
        $run->setErrorMessage('Run stalled - no progress for 30 minutes');

        $run->markCompleted();

        $this->assertEquals(TestRun::STATUS_COMPLETED, $run->getStatus());
        $this->assertNull($run->getErrorMessage());
    }

    public function testMarkFailed(): void
    {
        $run = new TestRun();

        $run->markFailed('Database error');

        $this->assertEquals(TestRun::STATUS_FAILED, $run->getStatus());
        $this->assertEquals('Database error', $run->getErrorMessage());
        $this->assertNotNull($run->getCompletedAt());
    }

    public function testMarkCancelled(): void
    {
        $run = new TestRun();

        $run->markCancelled();

        $this->assertEquals(TestRun::STATUS_CANCELLED, $run->getStatus());
        $this->assertNotNull($run->getCompletedAt());
    }

    public function testFluentInterface(): void
    {
        $run = new TestRun();
        $env = $this->createMockEnvironment();

        $result = $run
            ->setEnvironment($env)
            ->setType(TestRun::TYPE_MFTF)
            ->setStatus(TestRun::STATUS_PENDING)
            ->setTestFilter('test')
            ->setTriggeredBy(TestRun::TRIGGERED_BY_MANUAL);

        $this->assertSame($run, $result);
    }

    public function testToString(): void
    {
        $run = new TestRun();
        $env = $this->createMockEnvironment();
        $run->setEnvironment($env);

        $string = (string) $run;
        $this->assertStringContainsString('Run #', $string);
        $this->assertStringContainsString('TestEnv', $string);
    }

    private function createMockEnvironment(): TestEnvironment
    {
        $env = $this->createStub(TestEnvironment::class);
        $env->method('getName')->willReturn('TestEnv');

        return $env;
    }
}
