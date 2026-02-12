<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\AuditLog;
use App\Entity\TestEnvironment;
use App\Entity\User;
use App\EventListener\AuditLogEventListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogEventListenerTest extends TestCase
{
    public function testOnFlushSkipsWhenNoUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getUnitOfWork');

        $this->createListener($security)->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushLogsAuditedEntityInsertion(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldNames')->willReturn(['name', 'code']);
        $classMetadata->method('getFieldValue')->willReturnCallback(
            fn (object $entity, string $field) => match ($field) {
                'name' => 'staging',
                'code' => 'stg',
                default => null,
            },
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(function (AuditLog $log): bool {
                return 'TestEnvironment' === $log->getEntityType()
                    && AuditLog::ACTION_CREATE === $log->getAction()
                    && 'staging' === $log->getEntityLabel()
                    && $log->getNewData() === ['name' => 'staging', 'code' => 'stg'];
            }));

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSkipsNonAuditedEntities(): void
    {
        $nonAudited = new \stdClass();

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([$nonAudited]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects($this->never())->method('persist');

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSkipsAuditLogEntity(): void
    {
        $auditLog = new AuditLog();

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([$auditLog]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects($this->never())->method('persist');

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushLogsEntityUpdate(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);
        $uow->method('getEntityChangeSet')->willReturn([
            'name' => ['old-name', 'new-name'],
        ]);

        $classMetadata = $this->createStub(ClassMetadata::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(function (AuditLog $log): bool {
                return AuditLog::ACTION_UPDATE === $log->getAction()
                    && $log->getOldData() === ['name' => 'old-name']
                    && $log->getNewData() === ['name' => 'new-name']
                    && $log->getChangedFields() === ['name'];
            }));

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushLogsEntityDeletion(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([$testEnv]);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldNames')->willReturn(['name']);
        $classMetadata->method('getFieldValue')->willReturn('staging');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(function (AuditLog $log): bool {
                return AuditLog::ACTION_DELETE === $log->getAction()
                    && $log->getOldData() === ['name' => 'staging'];
            }));

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushRedactsSensitiveFields(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldNames')->willReturn(['name', 'adminPassword', 'apiKey']);
        $classMetadata->method('getFieldValue')->willReturnCallback(
            fn (object $entity, string $field) => match ($field) {
                'name' => 'staging',
                'adminPassword' => 'secret123',
                'apiKey' => 'key-abc',
                default => null,
            },
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(function (AuditLog $log): bool {
                $data = $log->getNewData();

                return 'staging' === $data['name']
                    && '[REDACTED]' === $data['adminPassword']
                    && '[REDACTED]' === $data['apiKey'];
            }));

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSetsIpAddressFromRequest(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldNames')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(fn (AuditLog $log): bool => '10.0.0.1' === $log->getIpAddress()));

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('10.0.0.1');
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $this->createListener($this->createAuthenticatedSecurity(), $requestStack)
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSkipsUpdateWithEmptyChangeSet(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);
        $uow->method('getEntityChangeSet')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects($this->never())->method('persist');

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    public function testPostFlushDoesNothingWithNoPendingCreates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->createListener()->postFlush(new PostFlushEventArgs($em));
    }

    public function testOnFlushRedactsSensitiveFieldsInUpdate(): void
    {
        $testEnv = new TestEnvironment();
        $testEnv->setName('staging');
        $testEnv->setCode('stg');
        $testEnv->setRegion('us');
        $testEnv->setBaseUrl('https://staging.example.com');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([$testEnv]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);
        $uow->method('getEntityChangeSet')->willReturn([
            'adminPassword' => ['old-pass', 'new-pass'],
            'name' => ['old', 'new'],
        ]);

        $classMetadata = $this->createStub(ClassMetadata::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $em->expects($this->once())->method('persist')
            ->with($this->callback(function (AuditLog $log): bool {
                return $log->getOldData() === ['adminPassword' => '[REDACTED]', 'name' => 'old']
                    && $log->getNewData() === ['adminPassword' => '[REDACTED]', 'name' => 'new'];
            }));

        $this->createListener($this->createAuthenticatedSecurity())
            ->onFlush(new OnFlushEventArgs($em));
    }

    private function createListener(
        ?Security $security = null,
        ?RequestStack $requestStack = null,
    ): AuditLogEventListener {
        return new AuditLogEventListener(
            $security ?? $this->createStub(Security::class),
            $requestStack ?? $this->createStub(RequestStack::class),
        );
    }

    private function createAuthenticatedSecurity(): Security
    {
        $user = $this->createStub(User::class);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return $security;
    }
}
