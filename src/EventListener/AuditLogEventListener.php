<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\CronJob;
use App\Entity\GlobalEnvVariable;
use App\Entity\NotificationTemplate;
use App\Entity\Settings;
use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Doctrine listener that captures entity changes for audit logging.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class AuditLogEventListener
{
    private const AUDITED_ENTITIES = [
        User::class,
        TestEnvironment::class,
        TestSuite::class,
        CronJob::class,
        Settings::class,
        NotificationTemplate::class,
        GlobalEnvVariable::class,
    ];

    private const SENSITIVE_FIELDS = [
        'password',
        'plainPassword',
        'adminPassword',
        'totpSecret',
        'apiKey',
        'secretKey',
        'token',
        'accessToken',
        'refreshToken',
        'secret',
        'credentials',
        'authToken',
        'privateKey',
    ];

    /** @var array<string, array{log: AuditLog, entity: object}> */
    private array $pendingCreates = [];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->pendingCreates = [];
        if (!$this->shouldLogForCurrentUser()) {
            return;
        }
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->shouldAudit($entity)) {
                $this->logCreate($em, $entity, $uow);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->shouldAudit($entity)) {
                $this->logUpdate($em, $entity, $uow);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->shouldAudit($entity)) {
                $this->logDelete($em, $entity, $uow);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingCreates)) {
            return;
        }

        $em = $args->getObjectManager();
        $updates = [];

        foreach ($this->pendingCreates as $key => $item) {
            $entity = $item['entity'];
            $log = $item['log'];

            $entityId = $entity->getId();
            if (null !== $entityId && $log->getEntityId() !== $entityId) {
                $log->setEntityId($entityId);
                $updates[] = $log;
            }
        }

        $this->pendingCreates = [];

        if (!empty($updates)) {
            foreach ($updates as $log) {
                $em->persist($log);
            }
            $em->flush();
        }
    }

    private function shouldAudit(object $entity): bool
    {
        if ($entity instanceof AuditLog) {
            return false;
        }

        foreach (self::AUDITED_ENTITIES as $class) {
            if ($entity instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function logCreate(EntityManagerInterface $em, object $entity, $uow): void
    {
        $log = $this->createAuditLog($entity, AuditLog::ACTION_CREATE);
        $log->setNewData($this->extractData($entity, $em));

        $this->persistLog($em, $log, $uow);

        // Store for postFlush ID update
        $this->pendingCreates[spl_object_id($entity)] = [
            'log' => $log,
            'entity' => $entity,
        ];
    }

    private function logUpdate(EntityManagerInterface $em, object $entity, $uow): void
    {
        $changeSet = $uow->getEntityChangeSet($entity);
        if (empty($changeSet)) {
            return;
        }

        $oldData = [];
        $newData = [];
        $changedFields = [];

        foreach ($changeSet as $field => [$oldValue, $newValue]) {
            $changedFields[] = $field;
            $oldData[$field] = $this->redactIfSensitive($field, $this->normalizeValue($oldValue));
            $newData[$field] = $this->redactIfSensitive($field, $this->normalizeValue($newValue));
        }

        $log = $this->createAuditLog($entity, AuditLog::ACTION_UPDATE);
        $log->setOldData($oldData);
        $log->setNewData($newData);
        $log->setChangedFields($changedFields);

        $this->persistLog($em, $log, $uow);
    }

    private function logDelete(EntityManagerInterface $em, object $entity, $uow): void
    {
        $log = $this->createAuditLog($entity, AuditLog::ACTION_DELETE);
        $log->setOldData($this->extractData($entity, $em));

        $this->persistLog($em, $log, $uow);
    }

    private function createAuditLog(object $entity, string $action): AuditLog
    {
        $log = new AuditLog();
        $log->setEntityType($this->getShortClassName($entity));
        $log->setEntityId($entity->getId() ?? 0);
        $log->setEntityLabel($this->getEntityLabel($entity));
        $log->setAction($action);
        $log->setUser($this->getCurrentUser());
        $log->setIpAddress($this->getClientIp());

        return $log;
    }

    private function persistLog(EntityManagerInterface $em, AuditLog $log, $uow): void
    {
        $em->persist($log);
        $classMetadata = $em->getClassMetadata(AuditLog::class);
        $uow->computeChangeSet($classMetadata, $log);
    }

    private function extractData(object $entity, EntityManagerInterface $em): array
    {
        $data = [];
        $metadata = $em->getClassMetadata($entity::class);

        foreach ($metadata->getFieldNames() as $field) {
            $value = $metadata->getFieldValue($entity, $field);
            $data[$field] = $this->redactIfSensitive($field, $this->normalizeValue($value));
        }

        return $data;
    }

    private function redactIfSensitive(string $field, mixed $value): mixed
    {
        $fieldLower = strtolower($field);
        foreach (self::SENSITIVE_FIELDS as $sensitive) {
            if (str_contains($fieldLower, strtolower($sensitive))) {
                return '[REDACTED]';
            }
        }

        return $value;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Collection) {
            return sprintf('[Collection: %d items]', $value->count());
        }

        if (is_object($value)) {
            if (method_exists($value, 'getId')) {
                return [
                    'id' => $value->getId(),
                    'type' => $this->getShortClassName($value),
                ];
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return $this->getShortClassName($value);
        }

        if (is_array($value)) {
            return $value;
        }

        return $value;
    }

    private function getShortClassName(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private function getEntityLabel(object $entity): ?string
    {
        if (method_exists($entity, '__toString')) {
            try {
                return (string) $entity;
            } catch (\Throwable $e) {
                error_log(sprintf('AuditLog: __toString() failed for %s: %s', $entity::class, $e->getMessage()));

                return null;
            }
        }

        if (method_exists($entity, 'getName')) {
            return $entity->getName();
        }

        return null;
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function shouldLogForCurrentUser(): bool
    {
        return null !== $this->getCurrentUser();
    }

    private function getClientIp(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }
}
