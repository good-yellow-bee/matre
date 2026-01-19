<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
#[ORM\Table(name: 'matre_notification_templates')]
#[ORM\UniqueConstraint(name: 'UNIQ_CHANNEL_NAME', columns: ['channel', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['channel', 'name'], message: 'A template for this channel and event already exists.')]
class NotificationTemplate
{
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_EMAIL = 'email';

    public const NAME_COMPLETED_SUCCESS = 'test_run_completed_success';
    public const NAME_COMPLETED_FAILURES = 'test_run_completed_failures';
    public const NAME_FAILED = 'test_run_failed';
    public const NAME_CANCELLED = 'test_run_cancelled';

    public const CHANNELS = [
        self::CHANNEL_SLACK,
        self::CHANNEL_EMAIL,
    ];

    public const NAMES = [
        self::NAME_COMPLETED_SUCCESS,
        self::NAME_COMPLETED_FAILURES,
        self::NAME_FAILED,
        self::NAME_CANCELLED,
    ];

    public const NAME_LABELS = [
        self::NAME_COMPLETED_SUCCESS => 'Test Run Completed (Success)',
        self::NAME_COMPLETED_FAILURES => 'Test Run Completed (Failures)',
        self::NAME_FAILED => 'Test Run Failed',
        self::NAME_CANCELLED => 'Test Run Cancelled',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::CHANNELS)]
    private string $channel;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::NAMES)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Template body cannot be empty.')]
    private string $body;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', ucfirst($this->channel), self::NAME_LABELS[$this->name] ?? $this->name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameLabel(): string
    {
        return self::NAME_LABELS[$this->name] ?? $this->name;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
