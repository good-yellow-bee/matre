<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Messenger\Transport\PerEnvironmentDoctrineTransportFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class PerEnvironmentDoctrineTransportFactoryTest extends TestCase
{
    private function createFactory(
        ?Connection $connection = null,
        ?LockFactory $lockFactory = null,
        ?LoggerInterface $logger = null,
    ): PerEnvironmentDoctrineTransportFactory {
        return new PerEnvironmentDoctrineTransportFactory(
            $connection ?? $this->createStub(Connection::class),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testImplementsTransportFactoryInterface(): void
    {
        $this->assertInstanceOf(TransportFactoryInterface::class, $this->createFactory());
    }

    public function testSupportsPerEnvDoctrineDsn(): void
    {
        $this->assertTrue($this->createFactory()->supports('per-env-doctrine://default', []));
    }

    public function testDoesNotSupportDoctrineDsn(): void
    {
        $this->assertFalse($this->createFactory()->supports('doctrine://default', []));
    }

    public function testDoesNotSupportAmqpDsn(): void
    {
        $this->assertFalse($this->createFactory()->supports('amqp://localhost', []));
    }

    public function testCreateTransportReturnsTransportInterface(): void
    {
        $serializer = $this->createStub(SerializerInterface::class);

        $transport = $this->createFactory()->createTransport('per-env-doctrine://default', [], $serializer);

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testCreateTransportWithCustomTableName(): void
    {
        $serializer = $this->createStub(SerializerInterface::class);

        $transport = $this->createFactory()->createTransport(
            'per-env-doctrine://default',
            ['table_name' => 'custom_messages'],
            $serializer,
        );

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
}
