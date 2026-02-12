<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\TestRunMessage;
use PHPUnit\Framework\TestCase;

class TestRunMessageTest extends TestCase
{
    public function testConstructorStoresValues(): void
    {
        $message = new TestRunMessage(10, 5, TestRunMessage::PHASE_EXECUTE);

        $this->assertEquals(10, $message->testRunId);
        $this->assertEquals(5, $message->environmentId);
        $this->assertEquals('execute', $message->phase);
    }

    public function testDefaultPhaseIsPrepare(): void
    {
        $message = new TestRunMessage(1, 2);

        $this->assertEquals(TestRunMessage::PHASE_PREPARE, $message->phase);
    }

    public function testPhaseConstants(): void
    {
        $this->assertEquals('prepare', TestRunMessage::PHASE_PREPARE);
        $this->assertEquals('execute', TestRunMessage::PHASE_EXECUTE);
        $this->assertEquals('report', TestRunMessage::PHASE_REPORT);
        $this->assertEquals('notify', TestRunMessage::PHASE_NOTIFY);
        $this->assertEquals('cleanup', TestRunMessage::PHASE_CLEANUP);
    }
}
