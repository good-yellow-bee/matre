<?php

declare(strict_types=1);

namespace App\Messenger\Transport;

use App\Messenger\Stamp\DoctrineReceivedStamp;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Receiver that processes one message per environment at a time.
 * Provides FIFO ordering within each environment and parallel processing across environments.
 */
final class PerEnvironmentDoctrineReceiver implements ReceiverInterface
{
    private const QUEUE_PREFIX = 'test_runner_env_';
    private const LOCK_TTL = 3600; // 1 hour

    /** @var array<int, LockInterface> */
    private array $activeLocks = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly SerializerInterface $serializer,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly string $tableName = 'messenger_messages',
    ) {
    }

    /**
     * Get messages - one per environment that isn't currently locked.
     *
     * @return iterable<Envelope>
     */
    public function get(): iterable
    {
        // Find all distinct queue names with pending messages
        $queues = $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT DISTINCT queue_name FROM %s
                 WHERE queue_name LIKE :prefix
                 AND delivered_at IS NULL
                 AND available_at <= :now',
                $this->tableName,
            ),
            [
                'prefix' => self::QUEUE_PREFIX . '%',
                'now' => new \DateTimeImmutable(),
            ],
            [
                'now' => 'datetime_immutable',
            ],
        );

        foreach ($queues as $queueName) {
            $envelope = $this->fetchFromQueue($queueName);
            if ($envelope !== null) {
                yield $envelope;
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = $envelope->last(DoctrineReceivedStamp::class);
        if (!$stamp) {
            return;
        }

        $id = $stamp->getId();

        // Delete message
        $this->connection->delete($this->tableName, ['id' => $id]);

        // Release env lock
        $this->releaseEnvLock($id);

        $this->logger->debug('Message acknowledged', ['id' => $id]);
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $envelope->last(DoctrineReceivedStamp::class);
        if (!$stamp) {
            return;
        }

        $id = $stamp->getId();

        // Delete message (will be handled by failure transport)
        $this->connection->delete($this->tableName, ['id' => $id]);

        // Release env lock
        $this->releaseEnvLock($id);

        $this->logger->debug('Message rejected', ['id' => $id]);
    }

    private function fetchFromQueue(string $queueName): ?Envelope
    {
        $envId = $this->extractEnvId($queueName);
        $lockKey = 'test_runner_env_processing_' . $envId;
        $lock = $this->lockFactory->createLock($lockKey, self::LOCK_TTL);

        // Try to acquire env lock (non-blocking)
        if (!$lock->acquire(false)) {
            $this->logger->debug('Environment locked, skipping', [
                'queueName' => $queueName,
                'envId' => $envId,
            ]);

            return null;
        }

        try {
            // Get oldest message for this queue (FIFO)
            $row = $this->connection->fetchAssociative(
                sprintf(
                    'SELECT * FROM %s
                     WHERE queue_name = :queue
                     AND delivered_at IS NULL
                     AND available_at <= :now
                     ORDER BY created_at ASC
                     LIMIT 1
                     FOR UPDATE SKIP LOCKED',
                    $this->tableName,
                ),
                [
                    'queue' => $queueName,
                    'now' => new \DateTimeImmutable(),
                ],
                [
                    'now' => 'datetime_immutable',
                ],
            );

            if (!$row) {
                $lock->release();

                return null;
            }

            // Mark as delivered
            $this->connection->update(
                $this->tableName,
                ['delivered_at' => new \DateTimeImmutable()],
                ['id' => $row['id']],
                ['delivered_at' => 'datetime_immutable'],
            );

            // Store lock reference for ack/reject
            $this->activeLocks[$row['id']] = $lock;

            $this->logger->debug('Message fetched', [
                'id' => $row['id'],
                'queueName' => $queueName,
                'envId' => $envId,
            ]);

            // Decode and return with stamp
            $envelope = $this->serializer->decode([
                'body' => $row['body'],
                'headers' => json_decode($row['headers'], true),
            ]);

            return $envelope->with(new DoctrineReceivedStamp($row['id']));
        } catch (\Throwable $e) {
            $lock->release();
            $this->logger->error('Error fetching message', [
                'queueName' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function releaseEnvLock(int $messageId): void
    {
        if (isset($this->activeLocks[$messageId])) {
            $this->activeLocks[$messageId]->release();
            unset($this->activeLocks[$messageId]);
        }
    }

    private function extractEnvId(string $queueName): int
    {
        return (int) str_replace(self::QUEUE_PREFIX, '', $queueName);
    }
}
