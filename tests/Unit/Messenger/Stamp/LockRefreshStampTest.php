<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Stamp;

use App\Messenger\Stamp\LockRefreshStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class LockRefreshStampTest extends TestCase
{
    private static function create(?\Closure $refreshCallback = null, ?\Closure $heartbeatCallback = null): LockRefreshStamp
    {
        return new LockRefreshStamp($refreshCallback ?? static function (): void {}, $heartbeatCallback);
    }

    public function testRefreshInvokesCallback(): void
    {
        $called = false;
        $stamp = self::create(refreshCallback: static function () use (&$called): void { $called = true; });

        $stamp->refresh();

        $this->assertTrue($called);
    }

    public function testHeartbeatInvokesCallback(): void
    {
        $called = false;
        $stamp = self::create(heartbeatCallback: static function () use (&$called): void { $called = true; });

        $stamp->heartbeat();

        $this->assertTrue($called);
    }

    public function testHeartbeatNoopWhenNoCallback(): void
    {
        $stamp = self::create();

        $stamp->heartbeat();

        $this->addToAssertionCount(1);
    }

    public function testGetHeartbeatCallbackReturnsNull(): void
    {
        $stamp = self::create();

        $this->assertNull($stamp->getHeartbeatCallback());
    }

    public function testGetHeartbeatCallbackReturnsClosure(): void
    {
        $stamp = self::create(heartbeatCallback: static function (): void {});

        $this->assertInstanceOf(\Closure::class, $stamp->getHeartbeatCallback());
    }

    public function testImplementsStampInterface(): void
    {
        $this->assertInstanceOf(StampInterface::class, self::create());
    }
}
