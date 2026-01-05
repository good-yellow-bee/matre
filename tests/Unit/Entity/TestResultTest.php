<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TestResult;
use App\Entity\TestRun;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TestResult entity.
 */
class TestResultTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getCreatedAt());
    }

    public function testTestRunGetterAndSetter(): void
    {
        $result = new TestResult();
        $run = $this->createMock(TestRun::class);

        $return = $result->setTestRun($run);

        $this->assertSame($run, $result->getTestRun());
        $this->assertSame($result, $return);
    }

    public function testTestNameGetterAndSetter(): void
    {
        $result = new TestResult();

        $result->setTestName('SomeTestCase');

        $this->assertEquals('SomeTestCase', $result->getTestName());
    }

    public function testTestIdGetterAndSetter(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getTestId());

        $result->setTestId('MOEC-1625');

        $this->assertEquals('MOEC-1625', $result->getTestId());
    }

    public function testStatusGetterAndSetter(): void
    {
        $result = new TestResult();

        $result->setStatus(TestResult::STATUS_PASSED);
        $this->assertEquals(TestResult::STATUS_PASSED, $result->getStatus());
        $this->assertEquals('Passed', $result->getStatusLabel());

        $result->setStatus(TestResult::STATUS_FAILED);
        $this->assertEquals('Failed', $result->getStatusLabel());

        $result->setStatus(TestResult::STATUS_SKIPPED);
        $this->assertEquals('Skipped', $result->getStatusLabel());

        $result->setStatus(TestResult::STATUS_BROKEN);
        $this->assertEquals('Broken', $result->getStatusLabel());
    }

    public function testDurationGetterAndSetter(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getDuration());

        $result->setDuration(45.5);
        $this->assertEquals(45.5, $result->getDuration());
    }

    public function testDurationFormattedMilliseconds(): void
    {
        $result = new TestResult();

        $result->setDuration(0.5);
        $this->assertEquals('500ms', $result->getDurationFormatted());

        $result->setDuration(0.123);
        $this->assertEquals('123ms', $result->getDurationFormatted());
    }

    public function testDurationFormattedSeconds(): void
    {
        $result = new TestResult();

        $result->setDuration(1.0);
        $this->assertEquals('1.0s', $result->getDurationFormatted());

        $result->setDuration(45.5);
        $this->assertEquals('45.5s', $result->getDurationFormatted());
    }

    public function testDurationFormattedMinutes(): void
    {
        $result = new TestResult();

        $result->setDuration(90.0);
        $this->assertEquals('1m 30s', $result->getDurationFormatted());

        $result->setDuration(125.0);
        $this->assertEquals('2m 5s', $result->getDurationFormatted());
    }

    public function testDurationFormattedNull(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getDurationFormatted());
    }

    public function testErrorMessageGetterAndSetter(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getErrorMessage());

        $result->setErrorMessage('Element not found');
        $this->assertEquals('Element not found', $result->getErrorMessage());
    }

    public function testScreenshotPathGetterAndSetter(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getScreenshotPath());

        $result->setScreenshotPath('/var/screenshots/test.png');
        $this->assertEquals('/var/screenshots/test.png', $result->getScreenshotPath());
    }

    public function testAllureResultPathGetterAndSetter(): void
    {
        $result = new TestResult();

        $this->assertNull($result->getAllureResultPath());

        $result->setAllureResultPath('/var/allure/result.json');
        $this->assertEquals('/var/allure/result.json', $result->getAllureResultPath());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $result = new TestResult();
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');

        $result->setCreatedAt($date);

        $this->assertEquals($date, $result->getCreatedAt());
    }

    public function testIsPassed(): void
    {
        $result = new TestResult();

        $result->setStatus(TestResult::STATUS_PASSED);
        $this->assertTrue($result->isPassed());

        $result->setStatus(TestResult::STATUS_FAILED);
        $this->assertFalse($result->isPassed());
    }

    public function testIsFailed(): void
    {
        $result = new TestResult();

        $result->setStatus(TestResult::STATUS_FAILED);
        $this->assertTrue($result->isFailed());

        $result->setStatus(TestResult::STATUS_PASSED);
        $this->assertFalse($result->isFailed());
    }

    public function testIsSkipped(): void
    {
        $result = new TestResult();

        $result->setStatus(TestResult::STATUS_SKIPPED);
        $this->assertTrue($result->isSkipped());

        $result->setStatus(TestResult::STATUS_PASSED);
        $this->assertFalse($result->isSkipped());
    }

    public function testIsBroken(): void
    {
        $result = new TestResult();

        $result->setStatus(TestResult::STATUS_BROKEN);
        $this->assertTrue($result->isBroken());

        $result->setStatus(TestResult::STATUS_PASSED);
        $this->assertFalse($result->isBroken());
    }

    public function testHasScreenshot(): void
    {
        $result = new TestResult();

        $this->assertFalse($result->hasScreenshot());

        $result->setScreenshotPath('/path/to/screenshot.png');
        $this->assertTrue($result->hasScreenshot());
    }

    public function testFluentInterface(): void
    {
        $result = new TestResult();
        $run = $this->createMock(TestRun::class);

        $return = $result
            ->setTestRun($run)
            ->setTestName('Test')
            ->setTestId('TEST-001')
            ->setStatus(TestResult::STATUS_PASSED)
            ->setDuration(10.0);

        $this->assertSame($result, $return);
    }

    public function testToString(): void
    {
        $result = new TestResult();
        $result->setTestName('MyTestCase');

        $this->assertEquals('MyTestCase', (string) $result);
    }
}
