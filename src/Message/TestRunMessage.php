<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for async test run execution.
 */
readonly class TestRunMessage
{
    public const PHASE_PREPARE = 'prepare';
    public const PHASE_EXECUTE = 'execute';
    public const PHASE_REPORT = 'report';
    public const PHASE_NOTIFY = 'notify';
    public const PHASE_CLEANUP = 'cleanup';

    public function __construct(
        public int $testRunId,
        public string $phase = self::PHASE_PREPARE,
    ) {
    }
}
