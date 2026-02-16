<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Stamp;

use App\Messenger\Stamp\EnvironmentQueueStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class EnvironmentQueueStampTest extends TestCase
{
    private static function create(?int $environmentId = null): EnvironmentQueueStamp
    {
        return new EnvironmentQueueStamp($environmentId ?? 1);
    }

    public function testConstructorSetsEnvironmentId(): void
    {
        $stamp = self::create(environmentId: 42);

        $this->assertSame(42, $stamp->environmentId);
    }

    public function testGetQueueNameReturnsFormattedString(): void
    {
        $stamp = self::create(environmentId: 5);

        $this->assertSame('test_runner_env_5', $stamp->getQueueName());
    }

    public function testImplementsStampInterface(): void
    {
        $this->assertInstanceOf(StampInterface::class, self::create());
    }
}
