<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\TestEnvironment;
use App\Entity\User;
use App\EventListener\CredentialEncryptionListener;
use App\Service\Security\CredentialEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

class CredentialEncryptionListenerTest extends TestCase
{
    public function testPrePersistEncryptsTestEnvironmentPassword(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('encryptIfNeeded')->willReturn('encrypted');

        $entity = new TestEnvironment();
        $entity->setAdminPassword('plain');

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));

        $this->assertSame('encrypted', $entity->getAdminPassword());
    }

    public function testPrePersistEncryptsUserTotpSecret(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('encryptIfNeeded')->willReturn('encrypted');

        $entity = new User();
        $entity->setTotpSecret('secret');

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));

        $this->assertSame('encrypted', $entity->getTotpSecret());
    }

    public function testPrePersistSkipsNullPassword(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $entity = new TestEnvironment();

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));
    }

    public function testPrePersistSkipsEmptyPassword(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $entity = new TestEnvironment();
        $entity->setAdminPassword('');

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));
    }

    public function testPreUpdateEncryptsChangedPassword(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('encryptIfNeeded')->willReturn('encrypted');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturnCallback(
            fn (string $field) => 'adminPassword' === $field,
        );
        $args->method('getNewValue')->willReturn('newpass');

        $entity = new TestEnvironment();

        $this->createListener($encryptionService)->preUpdate($entity, $args);

        $this->assertSame('encrypted', $entity->getAdminPassword());
    }

    public function testPreUpdateSkipsUnchangedPassword(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(false);

        $entity = new TestEnvironment();

        $this->createListener($encryptionService)->preUpdate($entity, $args);
    }

    public function testPreUpdateEncryptsChangedTotpSecret(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('encryptIfNeeded')->willReturn('encrypted');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturnCallback(
            fn (string $field) => 'totpSecret' === $field,
        );
        $args->method('getNewValue')->willReturn('newsecret');

        $entity = new User();

        $this->createListener($encryptionService)->preUpdate($entity, $args);

        $this->assertSame('encrypted', $entity->getTotpSecret());
    }

    public function testPreUpdateSkipsNullNewValue(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(true);
        $args->method('getNewValue')->willReturn(null);

        $entity = new TestEnvironment();

        $this->createListener($encryptionService)->preUpdate($entity, $args);
    }

    public function testPostLoadDecryptsTestEnvironmentPassword(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('decryptSafe')->willReturn('decrypted');

        $entity = new TestEnvironment();
        $entity->setAdminPassword('encrypted');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->willReturn(['adminPassword' => 'encrypted']);

        $args = $this->createPostLoadArgs($entity, $uow);

        $this->createListener($encryptionService)->postLoad($entity, $args);

        $this->assertSame('decrypted', $entity->getAdminPassword());
    }

    public function testPostLoadDecryptsUserTotpSecret(): void
    {
        $encryptionService = $this->createStub(CredentialEncryptionService::class);
        $encryptionService->method('decryptSafe')->willReturn('decrypted');

        $entity = new User();
        $entity->setTotpSecret('encrypted');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->willReturn(['totpSecret' => 'encrypted']);

        $args = $this->createPostLoadArgs($entity, $uow);

        $this->createListener($encryptionService)->postLoad($entity, $args);

        $this->assertSame('decrypted', $entity->getTotpSecret());
    }

    public function testPostLoadSkipsNullPassword(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('decryptSafe');

        $entity = new TestEnvironment();

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->willReturn([]);

        $args = $this->createPostLoadArgs($entity, $uow);

        $this->createListener($encryptionService)->postLoad($entity, $args);
    }

    public function testPostLoadRethrowsWhenEnvironmentPasswordDecryptionFails(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->once())
            ->method('decryptSafe')
            ->with('encrypted')
            ->willThrowException(new \RuntimeException('Decryption failed'));

        $entity = new TestEnvironment();
        $entity->setAdminPassword('encrypted');

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->willReturn(['adminPassword' => 'encrypted']);

        $args = $this->createPostLoadArgs($entity, $uow);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->createListener($encryptionService)->postLoad($entity, $args);
    }

    private function createListener(
        ?CredentialEncryptionService $encryptionService = null,
    ): CredentialEncryptionListener {
        return new CredentialEncryptionListener(
            $encryptionService ?? $this->createStub(CredentialEncryptionService::class),
        );
    }

    private function createPrePersistArgs(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function createPostLoadArgs(object $entity, UnitOfWork $uow): PostLoadEventArgs
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        return new PostLoadEventArgs($entity, $em);
    }
}
