<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Messenger\Transport\PerEnvironmentDoctrineReceiver;
use App\Messenger\Transport\PerEnvironmentDoctrineSender;
use App\Messenger\Transport\PerEnvironmentDoctrineTransport;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class PerEnvironmentDoctrineTransportTest extends TestCase
{
    private Connection $connection;

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->serializer = $this->createStub(SerializerInterface::class);
    }

    public function testImplementsTransportInterface(): void
    {
        $this->assertInstanceOf(TransportInterface::class, $this->createTransport());
    }

    public function testGetReturnsEmptyIterableWhenNoMessages(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([]);

        $result = iterator_to_array($this->createTransport()->get());

        $this->assertSame([], $result);
    }

    public function testSendDelegatesToSender(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->serializer->method('encode')->willReturn([
            'body' => '{}',
            'headers' => [],
        ]);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('messenger_messages', $this->anything(), $this->anything());

        $envelope = new Envelope(new \stdClass());
        $result = $this->createTransport(connection: $connection)->send($envelope);

        $this->assertSame($envelope, $result);
    }

    public function testAckWithoutStampDoesNotThrow(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->createTransport()->ack($envelope);

        $this->addToAssertionCount(1);
    }

    public function testRejectWithoutStampDoesNotThrow(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->createTransport()->reject($envelope);

        $this->addToAssertionCount(1);
    }

    private function createTransport(
        ?Connection $connection = null,
        ?SerializerInterface $serializer = null,
    ): PerEnvironmentDoctrineTransport {
        $conn = $connection ?? $this->connection;
        $ser = $serializer ?? $this->serializer;

        return new PerEnvironmentDoctrineTransport(
            new PerEnvironmentDoctrineReceiver(
                $conn,
                $ser,
                new LockFactory(new InMemoryStore()),
                $this->createStub(LoggerInterface::class),
            ),
            new PerEnvironmentDoctrineSender($conn, $ser),
        );
    }
}
