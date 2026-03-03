<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ScheduledTestRunMessage;
use App\Repository\TestSuiteRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('test_runner')]
class TestRunScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);

        $scheduledSuites = $this->testSuiteRepository->findScheduled();

        foreach ($scheduledSuites as $suite) {
            $cronExpression = $suite->getCronExpression();
            if (!$cronExpression) {
                continue;
            }

            $schedule->add(
                RecurringMessage::cron(
                    $cronExpression,
                    new ScheduledTestRunMessage($suite->getId()),
                ),
            );
        }

        return $schedule;
    }
}
