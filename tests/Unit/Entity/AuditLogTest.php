<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AuditLog;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AuditLogTest extends TestCase
{
    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $log = new AuditLog();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $log->getCreatedAt());
        $this->assertLessThanOrEqual($after, $log->getCreatedAt());
    }

    public function testIdIsNullByDefault(): void
    {
        $log = new AuditLog();

        $this->assertNull($log->getId());
    }

    public function testEntityTypeGetterAndSetter(): void
    {
        $log = new AuditLog();

        $result = $log->setEntityType('TestEnvironment');

        $this->assertEquals('TestEnvironment', $log->getEntityType());
        $this->assertSame($log, $result);
    }

    public function testEntityIdGetterAndSetter(): void
    {
        $log = new AuditLog();

        $result = $log->setEntityId(42);

        $this->assertEquals(42, $log->getEntityId());
        $this->assertSame($log, $result);
    }

    public function testEntityLabelGetterAndSetter(): void
    {
        $log = new AuditLog();

        $this->assertNull($log->getEntityLabel());

        $result = $log->setEntityLabel('My Environment');

        $this->assertEquals('My Environment', $log->getEntityLabel());
        $this->assertSame($log, $result);
    }

    public function testActionGetterAndSetter(): void
    {
        $log = new AuditLog();

        $result = $log->setAction(AuditLog::ACTION_CREATE);

        $this->assertEquals('create', $log->getAction());
        $this->assertSame($log, $result);
    }

    public function testOldDataAndNewDataGettersAndSetters(): void
    {
        $log = new AuditLog();

        $this->assertNull($log->getOldData());
        $this->assertNull($log->getNewData());

        $old = ['name' => 'Old'];
        $new = ['name' => 'New'];

        $log->setOldData($old);
        $log->setNewData($new);

        $this->assertEquals($old, $log->getOldData());
        $this->assertEquals($new, $log->getNewData());
    }

    public function testChangedFieldsGetterAndSetter(): void
    {
        $log = new AuditLog();

        $this->assertNull($log->getChangedFields());

        $result = $log->setChangedFields(['name', 'url']);

        $this->assertEquals(['name', 'url'], $log->getChangedFields());
        $this->assertSame($log, $result);
    }

    public function testUserGetterAndSetter(): void
    {
        $log = new AuditLog();
        $user = $this->createStub(User::class);

        $this->assertNull($log->getUser());

        $result = $log->setUser($user);

        $this->assertSame($user, $log->getUser());
        $this->assertSame($log, $result);
    }

    public function testIpAddressGetterAndSetter(): void
    {
        $log = new AuditLog();

        $this->assertNull($log->getIpAddress());

        $result = $log->setIpAddress('192.168.1.1');

        $this->assertEquals('192.168.1.1', $log->getIpAddress());
        $this->assertSame($log, $result);
    }

    public function testActionConstants(): void
    {
        $this->assertEquals('create', AuditLog::ACTION_CREATE);
        $this->assertEquals('update', AuditLog::ACTION_UPDATE);
        $this->assertEquals('delete', AuditLog::ACTION_DELETE);
    }

    public function testIsCreateIsUpdateIsDelete(): void
    {
        $log = new AuditLog();

        $log->setAction(AuditLog::ACTION_CREATE);
        $this->assertTrue($log->isCreate());
        $this->assertFalse($log->isUpdate());
        $this->assertFalse($log->isDelete());

        $log->setAction(AuditLog::ACTION_UPDATE);
        $this->assertFalse($log->isCreate());
        $this->assertTrue($log->isUpdate());
        $this->assertFalse($log->isDelete());

        $log->setAction(AuditLog::ACTION_DELETE);
        $this->assertFalse($log->isCreate());
        $this->assertFalse($log->isUpdate());
        $this->assertTrue($log->isDelete());
    }

    public function testFluentInterface(): void
    {
        $log = new AuditLog();

        $result = $log
            ->setEntityType('User')
            ->setEntityId(1)
            ->setEntityLabel('admin')
            ->setAction(AuditLog::ACTION_UPDATE)
            ->setOldData(['role' => 'user'])
            ->setNewData(['role' => 'admin'])
            ->setChangedFields(['role'])
            ->setIpAddress('10.0.0.1');

        $this->assertSame($log, $result);
    }
}
