<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Middleware;

use App\Message\TestRunMessage;
use App\Messenger\Middleware\EnvironmentQueueMiddleware;
use App\Messenger\Stamp\EnvironmentQueueStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class EnvironmentQueueMiddlewareTest extends TestCase
{
    public function testAddsStampToTestRunMessage(): void
    {
        $message = new TestRunMessage(testRunId: 1, environmentId: 5);
        $envelope = new Envelope($message);
        $capturedEnvelope = null;

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Envelope $envelope) use (&$capturedEnvelope): bool {
                $capturedEnvelope = $envelope;

                return true;
            }))
            ->willReturnCallback(fn (Envelope $e) => $e);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        $this->createMiddleware()->handle($envelope, $stack);

        $stamp = $capturedEnvelope->last(EnvironmentQueueStamp::class);
        $this->assertNotNull($stamp);
        $this->assertSame(5, $stamp->environmentId);
    }

    public function testDoesNotAddStampToOtherMessages(): void
    {
        $envelope = new Envelope(new \stdClass());
        $capturedEnvelope = null;

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Envelope $envelope) use (&$capturedEnvelope): bool {
                $capturedEnvelope = $envelope;

                return true;
            }))
            ->willReturnCallback(fn (Envelope $e) => $e);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        $this->createMiddleware()->handle($envelope, $stack);

        $this->assertNull($capturedEnvelope->last(EnvironmentQueueStamp::class));
    }

    public function testCallsNextMiddleware(): void
    {
        $envelope = new Envelope(new \stdClass());

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(fn (Envelope $e) => $e);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        $this->createMiddleware()->handle($envelope, $stack);
    }

    private function createMiddleware(): EnvironmentQueueMiddleware
    {
        return new EnvironmentQueueMiddleware();
    }
}
