<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Messenger\Stamp\DoctrineReceivedStamp;
use App\Messenger\Transport\PerEnvironmentDoctrineReceiver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class PerEnvironmentDoctrineReceiverTest extends TestCase
{
    private function createReceiver(
        ?Connection $connection = null,
        ?SerializerInterface $serializer = null,
        ?LockFactory $lockFactory = null,
        ?LoggerInterface $logger = null,
        string $tableName = 'messenger_messages',
    ): PerEnvironmentDoctrineReceiver {
        return new PerEnvironmentDoctrineReceiver(
            $connection ?? $this->createStub(Connection::class),
            $serializer ?? $this->createStub(SerializerInterface::class),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
            $logger ?? $this->createStub(LoggerInterface::class),
            $tableName,
        );
    }

    private function setActiveLocks(PerEnvironmentDoctrineReceiver $receiver, array $locks): void
    {
        $ref = new \ReflectionClass($receiver);
        $prop = $ref->getProperty('activeLocks');
        $prop->setValue($receiver, $locks);
    }

    public function testGetLockKeyForEnvReturnsCorrectFormat(): void
    {
        $this->assertSame('test_runner_env_processing_42', PerEnvironmentDoctrineReceiver::getLockKeyForEnv(42));
    }

    public function testAckDeletesMessageAndReleasesLock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('messenger_messages', ['id' => 99]);

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('release');

        $receiver = $this->createReceiver(connection: $connection);
        $this->setActiveLocks($receiver, [99 => $lock]);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp(99)]);
        $receiver->ack($envelope);
    }

    public function testAckWithoutStampDoesNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('delete');

        $receiver = $this->createReceiver(connection: $connection);
        $receiver->ack(new Envelope(new \stdClass()));
    }

    public function testRejectDeletesMessageAndReleasesLock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('messenger_messages', ['id' => 99]);

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('release');

        $receiver = $this->createReceiver(connection: $connection);
        $this->setActiveLocks($receiver, [99 => $lock]);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp(99)]);
        $receiver->reject($envelope);
    }

    public function testRejectWithoutStampDoesNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('delete');

        $receiver = $this->createReceiver(connection: $connection);
        $receiver->reject(new Envelope(new \stdClass()));
    }

    public function testRefreshLockRefreshesExistingLock(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('refresh');

        $receiver = $this->createReceiver();
        $this->setActiveLocks($receiver, [5 => $lock]);

        $receiver->refreshLock(5);
    }

    public function testRefreshLockNoopForUnknownMessageId(): void
    {
        $receiver = $this->createReceiver();
        $receiver->refreshLock(999);

        $this->addToAssertionCount(1);
    }

    public function testUpdateHeartbeatUpdatesDeliveredAt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'messenger_messages',
                $this->callback(fn (array $data): bool => $data['delivered_at'] instanceof \DateTimeImmutable),
                ['id' => 10],
                ['delivered_at' => 'datetime_immutable'],
            )
            ->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug')->with('Message heartbeat updated', ['messageId' => 10]);

        $receiver = $this->createReceiver(connection: $connection, logger: $logger);
        $receiver->updateHeartbeat(10);
    }

    public function testUpdateHeartbeatWarnsWhenNoRowsAffected(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('update')->willReturn(0);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Heartbeat update affected no rows - message may have been deleted', ['messageId' => 10]);

        $receiver = $this->createReceiver(connection: $connection, logger: $logger);
        $receiver->updateHeartbeat(10);
    }

    public function testUpdateHeartbeatRethrowsException(): void
    {
        $exception = new \RuntimeException('DB error');

        $connection = $this->createStub(Connection::class);
        $connection->method('update')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to update message heartbeat', ['messageId' => 10, 'error' => 'DB error']);

        $receiver = $this->createReceiver(connection: $connection, logger: $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');
        $receiver->updateHeartbeat(10);
    }

    public function testGetReturnsEmptyWhenNoQueues(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $receiver = $this->createReceiver(connection: $connection);

        $this->assertSame([], iterator_to_array($receiver->get()));
    }

    public function testGetFetchesMessageFromQueue(): void
    {
        $row = [
            'id' => 42,
            'body' => '{"testRunId":1}',
            'headers' => '{"type":"App\\\\Message\\\\TestRunMessage"}',
            'queue_name' => 'test_runner_env_5',
            'delivered_at' => null,
        ];

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['test_runner_env_5']);
        $connection->method('fetchAssociative')->willReturn($row);

        $decodedEnvelope = new Envelope(new \stdClass());
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn($decodedEnvelope);

        $receiver = $this->createReceiver(connection: $connection, serializer: $serializer);
        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);
        $this->assertNotNull($envelopes[0]->last(DoctrineReceivedStamp::class));
        $this->assertSame(42, $envelopes[0]->last(DoctrineReceivedStamp::class)->getId());
        $handlerArgsStamp = $envelopes[0]->last(HandlerArgumentsStamp::class);
        $this->assertNotNull($handlerArgsStamp);
        $args = $handlerArgsStamp->getAdditionalArguments();
        $this->assertCount(2, $args);
        $this->assertIsCallable($args[0]);
        $this->assertIsCallable($args[1]);
    }

    public function testGetFetchesMessageWithinTransaction(): void
    {
        $row = [
            'id' => 51,
            'body' => '{"testRunId":1}',
            'headers' => '{"type":"App\\\\Message\\\\TestRunMessage"}',
            'queue_name' => 'test_runner_env_3',
            'delivered_at' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn(['test_runner_env_3']);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($row);
        $connection->expects($this->once())
            ->method('update')
            ->with(
                'messenger_messages',
                $this->callback(fn (array $data): bool => $data['delivered_at'] instanceof \DateTimeImmutable),
                ['id' => 51],
                ['delivered_at' => 'datetime_immutable'],
            );
        $connection->expects($this->never())->method('rollBack');

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new \stdClass()));

        $receiver = $this->createReceiver(connection: $connection, serializer: $serializer);
        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);
        $this->assertSame(51, $envelopes[0]->last(DoctrineReceivedStamp::class)?->getId());
    }

    public function testGetSkipsLockedEnvironment(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['test_runner_env_5']);

        $lockFactory = new LockFactory(new InMemoryStore());
        $existingLock = $lockFactory->createLock('test_runner_env_processing_5', 1800);
        $existingLock->acquire();

        $receiver = $this->createReceiver(connection: $connection, lockFactory: $lockFactory);

        $this->assertSame([], iterator_to_array($receiver->get()));
    }
}
