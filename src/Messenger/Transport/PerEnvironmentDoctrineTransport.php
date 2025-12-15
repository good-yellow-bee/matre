<?php

declare(strict_types=1);

namespace App\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Transport that combines per-environment sender and receiver.
 */
final class PerEnvironmentDoctrineTransport implements TransportInterface
{
    public function __construct(
        private readonly PerEnvironmentDoctrineReceiver $receiver,
        private readonly PerEnvironmentDoctrineSender $sender,
    ) {
    }

    public function get(): iterable
    {
        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->sender->send($envelope);
    }
}
