<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\CronJobMessage;
use PHPUnit\Framework\TestCase;

class CronJobMessageTest extends TestCase
{
    public function testConstructorStoresCronJobId(): void
    {
        $message = new CronJobMessage(42);

        $this->assertEquals(42, $message->cronJobId);
    }
}
