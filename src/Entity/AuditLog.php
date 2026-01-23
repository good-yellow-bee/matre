<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Audit log for tracking admin changes.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'matre_audit_logs')]
#[ORM\Index(name: 'IDX_AUDIT_ENTITY', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'IDX_AUDIT_CREATED', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_AUDIT_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_AUDIT_ACTION', columns: ['action'])]
class AuditLog
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $entityId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $entityLabel = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $action;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changedFields = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): static
    {
        $this->entityLabel = $entityLabel;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getOldData(): ?array
    {
        return $this->oldData;
    }

    public function setOldData(?array $oldData): static
    {
        $this->oldData = $oldData;

        return $this;
    }

    public function getNewData(): ?array
    {
        return $this->newData;
    }

    public function setNewData(?array $newData): static
    {
        $this->newData = $newData;

        return $this;
    }

    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }

    public function setChangedFields(?array $changedFields): static
    {
        $this->changedFields = $changedFields;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCreate(): bool
    {
        return self::ACTION_CREATE === $this->action;
    }

    public function isUpdate(): bool
    {
        return self::ACTION_UPDATE === $this->action;
    }

    public function isDelete(): bool
    {
        return self::ACTION_DELETE === $this->action;
    }
}
