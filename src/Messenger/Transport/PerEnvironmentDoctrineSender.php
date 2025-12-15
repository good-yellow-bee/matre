<?php

declare(strict_types=1);

namespace App\Messenger\Transport;

use App\Messenger\Stamp\EnvironmentQueueStamp;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Sender that inserts messages with dynamic queue_name based on environment.
 */
final class PerEnvironmentDoctrineSender implements SenderInterface
{
    private const DEFAULT_QUEUE_NAME = 'test_runner';

    public function __construct(
        private readonly Connection $connection,
        private readonly SerializerInterface $serializer,
        private readonly string $tableName = 'messenger_messages',
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $stamp = $envelope->last(EnvironmentQueueStamp::class);
        $queueName = $stamp?->getQueueName() ?? self::DEFAULT_QUEUE_NAME;

        $encodedMessage = $this->serializer->encode($envelope);

        $now = new \DateTimeImmutable();

        $this->connection->insert($this->tableName, [
            'body' => $encodedMessage['body'],
            'headers' => json_encode($encodedMessage['headers'] ?? []),
            'queue_name' => $queueName,
            'created_at' => $now,
            'available_at' => $now,
        ], [
            'created_at' => 'datetime_immutable',
            'available_at' => 'datetime_immutable',
        ]);

        return $envelope;
    }
}
