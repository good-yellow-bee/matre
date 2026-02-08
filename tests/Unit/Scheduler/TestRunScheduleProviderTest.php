<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Entity\TestSuite;
use App\Repository\TestSuiteRepository;
use App\Scheduler\TestRunScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

class TestRunScheduleProviderTest extends TestCase
{
    public function testImplementsScheduleProviderInterface(): void
    {
        self::assertInstanceOf(ScheduleProviderInterface::class, $this->createProvider());
    }

    public function testGetScheduleReturnsEmptyScheduleWhenNoScheduledSuites(): void
    {
        $repo = $this->createStub(TestSuiteRepository::class);
        $repo->method('findScheduled')->willReturn([]);

        $schedule = $this->createProvider($repo)->getSchedule();

        self::assertCount(0, $schedule->getRecurringMessages());
    }

    public function testGetScheduleSkipsSuitesWithNullCronExpression(): void
    {
        $suite = $this->createStub(TestSuite::class);
        $suite->method('getId')->willReturn(1);
        $suite->method('getCronExpression')->willReturn(null);

        $repo = $this->createStub(TestSuiteRepository::class);
        $repo->method('findScheduled')->willReturn([$suite]);

        $schedule = $this->createProvider($repo)->getSchedule();

        self::assertCount(0, $schedule->getRecurringMessages());
    }

    public function testGetScheduleAddsRecurringMessages(): void
    {
        $suite1 = $this->createStub(TestSuite::class);
        $suite1->method('getId')->willReturn(1);
        $suite1->method('getCronExpression')->willReturn('* * * * *');

        $suite2 = $this->createStub(TestSuite::class);
        $suite2->method('getId')->willReturn(2);
        $suite2->method('getCronExpression')->willReturn('0 * * * *');

        $repo = $this->createStub(TestSuiteRepository::class);
        $repo->method('findScheduled')->willReturn([$suite1, $suite2]);

        $schedule = $this->createProvider($repo)->getSchedule();

        self::assertCount(2, $schedule->getRecurringMessages());
    }

    private function createProvider(?TestSuiteRepository $testSuiteRepository = null): TestRunScheduleProvider
    {
        return new TestRunScheduleProvider(
            $testSuiteRepository ?? $this->createStub(TestSuiteRepository::class),
        );
    }
}
