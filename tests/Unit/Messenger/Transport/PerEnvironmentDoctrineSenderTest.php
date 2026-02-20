<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Messenger\Stamp\EnvironmentQueueStamp;
use App\Messenger\Transport\PerEnvironmentDoctrineSender;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class PerEnvironmentDoctrineSenderTest extends TestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->serializer->method('encode')->willReturn([
            'body' => '{"testRunId":1}',
            'headers' => ['type' => 'App\\Message\\TestRunMessage'],
        ]);
    }

    public function testImplementsSenderInterface(): void
    {
        $this->assertInstanceOf(SenderInterface::class, $this->createSender());
    }

    public function testSendInsertsWithEnvironmentQueueName(): void
    {
        $envelope = new Envelope(new \stdClass(), [new EnvironmentQueueStamp(5)]);
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'messenger_messages',
                $this->callback(fn (array $data): bool => 'test_runner_env_5' === $data['queue_name']
                    && '{"testRunId":1}' === $data['body']),
                $this->anything(),
            );

        $this->createSender(connection: $connection)->send($envelope);
    }

    public function testSendInsertsWithDefaultQueueName(): void
    {
        $envelope = new Envelope(new \stdClass());
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'messenger_messages',
                $this->callback(fn (array $data): bool => 'test_runner' === $data['queue_name']),
                $this->anything(),
            );

        $this->createSender(connection: $connection)->send($envelope);
    }

    public function testSendReturnsOriginalEnvelope(): void
    {
        $envelope = new Envelope(new \stdClass());

        $result = $this->createSender()->send($envelope);

        $this->assertSame($envelope, $result);
    }

    public function testSendUsesCustomTableName(): void
    {
        $envelope = new Envelope(new \stdClass());
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('custom_table', $this->anything(), $this->anything());

        $this->createSender(connection: $connection, tableName: 'custom_table')->send($envelope);
    }

    public function testSendEncodesHeadersAsJson(): void
    {
        $envelope = new Envelope(new \stdClass());
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->callback(fn (array $data): bool => $data['headers'] === json_encode(['type' => 'App\\Message\\TestRunMessage'])),
                $this->anything(),
            );

        $this->createSender(connection: $connection)->send($envelope);
    }

    public function testSendSetsDateTimeColumns(): void
    {
        $envelope = new Envelope(new \stdClass());
        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->callback(fn (array $data): bool => $data['created_at'] instanceof \DateTimeImmutable
                    && $data['available_at'] instanceof \DateTimeImmutable),
                $this->callback(fn (array $types): bool => 'datetime_immutable' === $types['created_at']
                    && 'datetime_immutable' === $types['available_at']),
            );

        $this->createSender(connection: $connection)->send($envelope);
    }

    private function createSender(
        ?Connection $connection = null,
        ?string $tableName = null,
    ): PerEnvironmentDoctrineSender {
        return new PerEnvironmentDoctrineSender(
            $connection ?? $this->createStub(Connection::class),
            $this->serializer,
            $tableName ?? 'messenger_messages',
        );
    }
}
