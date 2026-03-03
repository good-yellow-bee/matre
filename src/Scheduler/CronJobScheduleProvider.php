<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CronJobMessage;
use App\Repository\CronJobRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('cron')]
class CronJobScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CronJobRepository $cronJobRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);

        $activeJobs = $this->cronJobRepository->findActive();

        foreach ($activeJobs as $job) {
            $schedule->add(
                RecurringMessage::cron(
                    $job->getCronExpression(),
                    new CronJobMessage($job->getId()),
                ),
            );
        }

        return $schedule;
    }
}
