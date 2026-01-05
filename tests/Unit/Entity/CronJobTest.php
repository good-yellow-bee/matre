<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CronJob;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CronJob entity.
 */
class CronJobTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $job = new CronJob();

        $this->assertNull($job->getId());
        $this->assertTrue($job->getIsActive());
        $this->assertEquals('* * * * *', $job->getCronExpression());
        $this->assertInstanceOf(\DateTimeImmutable::class, $job->getCreatedAt());
        $this->assertNull($job->getUpdatedAt());
        $this->assertNull($job->getLastRunAt());
        $this->assertNull($job->getLastStatus());
        $this->assertNull($job->getLastOutput());
    }

    public function testNameGetterAndSetter(): void
    {
        $job = new CronJob();

        $result = $job->setName('cleanup-old-runs');

        $this->assertEquals('cleanup-old-runs', $job->getName());
        $this->assertSame($job, $result);
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $job = new CronJob();

        $this->assertNull($job->getDescription());

        $job->setDescription('Clean up old test runs');
        $this->assertEquals('Clean up old test runs', $job->getDescription());
    }

    public function testCommandGetterAndSetter(): void
    {
        $job = new CronJob();

        $job->setCommand('app:cleanup --days=30');
        $this->assertEquals('app:cleanup --days=30', $job->getCommand());
    }

    public function testGetCommandName(): void
    {
        $job = new CronJob();

        $job->setCommand('app:cleanup --days=30');
        $this->assertEquals('app:cleanup', $job->getCommandName());

        $job->setCommand('app:simple');
        $this->assertEquals('app:simple', $job->getCommandName());
    }

    public function testCronExpressionGetterAndSetter(): void
    {
        $job = new CronJob();

        $job->setCronExpression('0 2 * * *');
        $this->assertEquals('0 2 * * *', $job->getCronExpression());
    }

    public function testIsActiveGetterAndSetter(): void
    {
        $job = new CronJob();

        $this->assertTrue($job->getIsActive());

        $job->setIsActive(false);
        $this->assertFalse($job->getIsActive());
    }

    public function testLastRunAtGetterAndSetter(): void
    {
        $job = new CronJob();
        $date = new \DateTimeImmutable('2024-01-01 02:00:00');

        $job->setLastRunAt($date);
        $this->assertEquals($date, $job->getLastRunAt());
    }

    public function testLastStatusGetterAndSetter(): void
    {
        $job = new CronJob();

        $job->setLastStatus(CronJob::STATUS_SUCCESS);
        $this->assertEquals(CronJob::STATUS_SUCCESS, $job->getLastStatus());

        $job->setLastStatus(CronJob::STATUS_FAILED);
        $this->assertEquals(CronJob::STATUS_FAILED, $job->getLastStatus());
    }

    public function testLastOutputGetterAndSetterTruncates(): void
    {
        $job = new CronJob();

        $job->setLastOutput('Short output');
        $this->assertEquals('Short output', $job->getLastOutput());

        // Test truncation (over 10KB)
        $longOutput = str_repeat('a', 15000);
        $job->setLastOutput($longOutput);
        $this->assertStringContainsString('[truncated]', $job->getLastOutput());
        $this->assertLessThan(15000, strlen($job->getLastOutput()));
    }

    public function testIsRunning(): void
    {
        $job = new CronJob();

        $this->assertFalse($job->isRunning());

        $job->setLastStatus(CronJob::STATUS_RUNNING);
        $this->assertTrue($job->isRunning());

        $job->setLastStatus(CronJob::STATUS_SUCCESS);
        $this->assertFalse($job->isRunning());
    }

    public function testWasSuccessful(): void
    {
        $job = new CronJob();

        $this->assertFalse($job->wasSuccessful());

        $job->setLastStatus(CronJob::STATUS_SUCCESS);
        $this->assertTrue($job->wasSuccessful());

        $job->setLastStatus(CronJob::STATUS_FAILED);
        $this->assertFalse($job->wasSuccessful());
    }

    public function testFluentInterface(): void
    {
        $job = new CronJob();

        $result = $job
            ->setName('test-job')
            ->setDescription('A test job')
            ->setCommand('app:test')
            ->setCronExpression('0 * * * *')
            ->setIsActive(true);

        $this->assertSame($job, $result);
    }

    public function testToString(): void
    {
        $job = new CronJob();
        $job->setName('my-cron-job');

        $this->assertEquals('my-cron-job', (string) $job);
    }

    public function testStatusConstants(): void
    {
        $this->assertEquals('success', CronJob::STATUS_SUCCESS);
        $this->assertEquals('failed', CronJob::STATUS_FAILED);
        $this->assertEquals('running', CronJob::STATUS_RUNNING);
        $this->assertEquals('locked', CronJob::STATUS_LOCKED);
    }
}
