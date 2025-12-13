<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ScheduledTestRunMessage;
use App\Repository\TestSuiteRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Provides schedule from database-configured test suites.
 *
 * Schedule is rebuilt on each worker restart (--time-limit=60).
 * Changes to suites take effect within ~1 minute.
 */
#[AsSchedule('test_runner')]
class TestRunScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly TestSuiteRepository $testSuiteRepository,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

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
