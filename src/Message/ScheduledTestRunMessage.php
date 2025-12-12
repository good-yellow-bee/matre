<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for scheduled test suite execution.
 */
readonly class ScheduledTestRunMessage
{
    public function __construct(
        public int $suiteId,
    ) {
    }
}
