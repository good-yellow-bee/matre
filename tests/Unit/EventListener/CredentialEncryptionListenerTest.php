<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

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

    public function testPrePersistSkipsNullTotpSecret(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $entity = new User();

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));
    }

    public function testPrePersistSkipsEmptyTotpSecret(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $entity = new User();
        $entity->setTotpSecret('');

        $this->createListener($encryptionService)
            ->prePersist($entity, $this->createPrePersistArgs($entity));
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

    public function testPreUpdateSkipsUnchangedTotpSecret(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(false);

        $entity = new User();

        $this->createListener($encryptionService)->preUpdate($entity, $args);
    }

    public function testPreUpdateSkipsNullNewValue(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('encryptIfNeeded');

        $args = $this->createStub(PreUpdateEventArgs::class);
        $args->method('hasChangedField')->willReturn(true);
        $args->method('getNewValue')->willReturn(null);

        $entity = new User();

        $this->createListener($encryptionService)->preUpdate($entity, $args);
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

    public function testPostLoadSkipsNullTotpSecret(): void
    {
        $encryptionService = $this->createMock(CredentialEncryptionService::class);
        $encryptionService->expects($this->never())->method('decryptSafe');

        $entity = new User();

        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->willReturn([]);

        $args = $this->createPostLoadArgs($entity, $uow);

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
