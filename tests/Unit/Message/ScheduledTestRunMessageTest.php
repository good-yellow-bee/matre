<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\ScheduledTestRunMessage;
use PHPUnit\Framework\TestCase;

class ScheduledTestRunMessageTest extends TestCase
{
    public function testConstructorStoresSuiteId(): void
    {
        $message = new ScheduledTestRunMessage(7);

        $this->assertEquals(7, $message->suiteId);
    }
}
