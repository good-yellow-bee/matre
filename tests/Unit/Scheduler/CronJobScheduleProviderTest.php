<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Entity\CronJob;
use App\Repository\CronJobRepository;
use App\Scheduler\CronJobScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

class CronJobScheduleProviderTest extends TestCase
{
    public function testImplementsScheduleProviderInterface(): void
    {
        self::assertInstanceOf(ScheduleProviderInterface::class, $this->createProvider());
    }

    public function testGetScheduleReturnsEmptyScheduleWhenNoActiveJobs(): void
    {
        $repo = $this->createStub(CronJobRepository::class);
        $repo->method('findActive')->willReturn([]);

        $schedule = $this->createProvider($repo)->getSchedule();

        self::assertCount(0, $schedule->getRecurringMessages());
    }

    public function testGetScheduleAddsRecurringMessageForEachActiveJob(): void
    {
        $job1 = $this->createStub(CronJob::class);
        $job1->method('getId')->willReturn(1);
        $job1->method('getCronExpression')->willReturn('* * * * *');

        $job2 = $this->createStub(CronJob::class);
        $job2->method('getId')->willReturn(2);
        $job2->method('getCronExpression')->willReturn('0 * * * *');

        $repo = $this->createStub(CronJobRepository::class);
        $repo->method('findActive')->willReturn([$job1, $job2]);

        $schedule = $this->createProvider($repo)->getSchedule();

        self::assertCount(2, $schedule->getRecurringMessages());
    }

    private function createProvider(?CronJobRepository $cronJobRepository = null): CronJobScheduleProvider
    {
        return new CronJobScheduleProvider(
            $cronJobRepository ?? $this->createStub(CronJobRepository::class),
        );
    }
}
