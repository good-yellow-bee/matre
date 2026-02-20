<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\TestEnvironment;
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
 * - TestEnvironment.adminPassword
 * - User.totpSecret
 */
#[AsEntityListener(event: Events::prePersist, entity: TestEnvironment::class)]
#[AsEntityListener(event: Events::preUpdate, entity: TestEnvironment::class)]
#[AsEntityListener(event: Events::postLoad, entity: TestEnvironment::class)]
#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
#[AsEntityListener(event: Events::postLoad, entity: User::class)]
class CredentialEncryptionListener
{
    public function __construct(
        private readonly CredentialEncryptionService $encryptionService,
    ) {
    }

    /**
     * Encrypt credentials before persisting a new entity.
     */
    public function prePersist(TestEnvironment|User $entity, PrePersistEventArgs $args): void
    {
        $this->encryptEntity($entity);
    }

    /**
     * Encrypt credentials before updating an entity.
     */
    public function preUpdate(TestEnvironment|User $entity, PreUpdateEventArgs $args): void
    {
        // Only encrypt if the field was actually changed
        if ($entity instanceof TestEnvironment) {
            if ($args->hasChangedField('adminPassword')) {
                $value = $args->getNewValue('adminPassword');
                if (null !== $value && '' !== $value) {
                    $encrypted = $this->encryptionService->encryptIfNeeded($value);
                    $args->setNewValue('adminPassword', $encrypted);
                    $entity->setAdminPassword($encrypted);
                }
            }
        }

        if ($entity instanceof User) {
            if ($args->hasChangedField('totpSecret')) {
                $value = $args->getNewValue('totpSecret');
                if (null !== $value && '' !== $value) {
                    $encrypted = $this->encryptionService->encryptIfNeeded($value);
                    $args->setNewValue('totpSecret', $encrypted);
                    $entity->setTotpSecret($encrypted);
                }
            }
        }
    }

    /**
     * Decrypt credentials after loading an entity.
     */
    public function postLoad(TestEnvironment|User $entity, PostLoadEventArgs $args): void
    {
        $this->decryptEntity($entity);

        // Sync Doctrine's original data with decrypted values
        // to prevent phantom dirty detection
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($entity);

        if ($entity instanceof TestEnvironment) {
            $originalData['adminPassword'] = $entity->getAdminPassword();
        }
        if ($entity instanceof User) {
            $originalData['totpSecret'] = $entity->getTotpSecret();
        }

        $uow->setOriginalEntityData($entity, $originalData);
    }

    /**
     * Encrypt sensitive fields on an entity.
     */
    private function encryptEntity(TestEnvironment|User $entity): void
    {
        if ($entity instanceof TestEnvironment) {
            $password = $entity->getAdminPassword();
            if (null !== $password && '' !== $password) {
                $entity->setAdminPassword(
                    $this->encryptionService->encryptIfNeeded($password),
                );
            }
        }

        if ($entity instanceof User) {
            $totpSecret = $entity->getTotpSecret();
            if (null !== $totpSecret && '' !== $totpSecret) {
                $entity->setTotpSecret(
                    $this->encryptionService->encryptIfNeeded($totpSecret),
                );
            }
        }
    }

    /**
     * Decrypt sensitive fields on an entity.
     */
    private function decryptEntity(TestEnvironment|User $entity): void
    {
        if ($entity instanceof TestEnvironment) {
            $password = $entity->getAdminPassword();
            if (null !== $password && '' !== $password) {
                $entity->setAdminPassword(
                    $this->encryptionService->decryptSafe($password),
                );
            }
        }

        if ($entity instanceof User) {
            $totpSecret = $entity->getTotpSecret();
            if (null !== $totpSecret && '' !== $totpSecret) {
                $entity->setTotpSecret(
                    $this->encryptionService->decryptSafe($totpSecret),
                );
            }
        }
    }
}
