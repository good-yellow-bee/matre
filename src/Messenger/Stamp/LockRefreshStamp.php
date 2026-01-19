<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries lock refresh and heartbeat callbacks from receiver to handler.
 * Enables long-running handlers to refresh locks and extend redelivery window.
 */
final class LockRefreshStamp implements StampInterface
{
    public function __construct(
        private readonly \Closure $refreshCallback,
        private readonly ?\Closure $heartbeatCallback = null,
    ) {
    }

    public function refresh(): void
    {
        ($this->refreshCallback)();
    }

    /**
     * Update message heartbeat to extend redelivery window.
     */
    public function heartbeat(): void
    {
        if ($this->heartbeatCallback) {
            ($this->heartbeatCallback)();
        }
    }

    public function getHeartbeatCallback(): ?\Closure
    {
        return $this->heartbeatCallback;
    }
}
