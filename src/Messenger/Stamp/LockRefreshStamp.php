<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries a lock refresh callback from receiver to handler.
 * Enables long-running handlers to refresh their environment lock during execution.
 */
final class LockRefreshStamp implements StampInterface
{
    public function __construct(
        private readonly \Closure $refreshCallback,
    ) {
    }

    public function refresh(): void
    {
        ($this->refreshCallback)();
    }
}
