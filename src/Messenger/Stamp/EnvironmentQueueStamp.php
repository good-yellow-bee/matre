<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp to route TestRunMessage to environment-specific queue.
 */
final readonly class EnvironmentQueueStamp implements StampInterface
{
    public function __construct(
        public int $environmentId,
    ) {
    }

    public function getQueueName(): string
    {
        return 'test_runner_env_' . $this->environmentId;
    }
}
