<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Stamp;

use App\Messenger\Stamp\DoctrineReceivedStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class DoctrineReceivedStampTest extends TestCase
{
    private static function create(?int $id = null): DoctrineReceivedStamp
    {
        return new DoctrineReceivedStamp($id ?? 1);
    }

    public function testGetIdReturnsConstructorValue(): void
    {
        $stamp = self::create(id: 99);

        $this->assertSame(99, $stamp->getId());
    }

    public function testImplementsStampInterface(): void
    {
        $this->assertInstanceOf(StampInterface::class, self::create());
    }
}
