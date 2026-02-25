<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\Security\CredentialEncryptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Doctrine listener that automatically encrypts sensitive fields on persist/update
 * and decrypts them on load.
 *
 * Handles:
 * - User.totpSecret
 */
#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
#[AsEntityListener(event: Events::postLoad, entity: User::class)]
class CredentialEncryptionListener
{
    public function __construct(
        private readonly CredentialEncryptionService $encryptionService,
    ) {
    }

    public function prePersist(User $entity, PrePersistEventArgs $args): void
    {
        $this->encryptEntity($entity);
    }

    public function preUpdate(User $entity, PreUpdateEventArgs $args): void
    {
        if ($args->hasChangedField('totpSecret')) {
            $value = $args->getNewValue('totpSecret');
            if (null !== $value && '' !== $value) {
                $encrypted = $this->encryptionService->encryptIfNeeded($value);
                $args->setNewValue('totpSecret', $encrypted);
                $entity->setTotpSecret($encrypted);
            }
        }
    }

    public function postLoad(User $entity, PostLoadEventArgs $args): void
    {
        $this->decryptEntity($entity);

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($entity);

        $originalData['totpSecret'] = $entity->getTotpSecret();

        $uow->setOriginalEntityData($entity, $originalData);
    }

    private function encryptEntity(User $entity): void
    {
        $totpSecret = $entity->getTotpSecret();
        if (null !== $totpSecret && '' !== $totpSecret) {
            $entity->setTotpSecret(
                $this->encryptionService->encryptIfNeeded($totpSecret),
            );
        }
    }

    private function decryptEntity(User $entity): void
    {
        $totpSecret = $entity->getTotpSecret();
        if (null !== $totpSecret && '' !== $totpSecret) {
            $entity->setTotpSecret(
                $this->encryptionService->decryptSafe($totpSecret),
            );
        }
    }
}
