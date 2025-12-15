<?php

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp to track message ID for ack/reject operations.
 */
final readonly class DoctrineReceivedStamp implements StampInterface
{
    public function __construct(
        private int $id,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
