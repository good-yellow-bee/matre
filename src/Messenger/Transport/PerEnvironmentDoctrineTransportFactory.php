<?php

declare(strict_types=1);

namespace App\Messenger\Transport;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Factory for per-environment Doctrine transport.
 *
 * Usage: dsn: 'per-env-doctrine://default'
 */
final class PerEnvironmentDoctrineTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $tableName = $options['table_name'] ?? 'messenger_messages';

        return new PerEnvironmentDoctrineTransport(
            new PerEnvironmentDoctrineReceiver(
                $this->connection,
                $serializer,
                $this->lockFactory,
                $this->logger,
                $tableName,
            ),
            new PerEnvironmentDoctrineSender(
                $this->connection,
                $serializer,
                $tableName,
            ),
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'per-env-doctrine://');
    }
}
