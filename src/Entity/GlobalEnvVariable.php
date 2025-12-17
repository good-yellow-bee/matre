<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GlobalEnvVariableRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Global environment variables shared across all test environments.
 * These values are merged with environment-specific variables at runtime.
 */
#[ORM\Entity(repositoryClass: GlobalEnvVariableRepository::class)]
#[ORM\Table(name: 'matre_global_env_variables')]
#[ORM\Index(name: 'IDX_GLOBAL_ENV_VAR_NAME', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
class GlobalEnvVariable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Variable name cannot be blank.')]
    #[Assert\Length(
        min: 1,
        max: 100,
        maxMessage: 'Name cannot be longer than {{ limit }} characters.',
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z][A-Z0-9_]*$/',
        message: 'Name must be UPPERCASE with underscores (e.g., SELENIUM_HOST).',
    )]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Value cannot be blank.')]
    private string $value;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $usedInTests = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $description = null;

    /**
     * Target environments (e.g., ['stage-us', 'preprod-us']).
     * Null or empty array = applies to ALL environments (global).
     *
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $environments = null;

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
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getUsedInTests(): ?string
    {
        return $this->usedInTests;
    }

    public function setUsedInTests(?string $usedInTests): static
    {
        $this->usedInTests = $usedInTests;

        return $this;
    }

    /**
     * Get test IDs as array.
     *
     * @return string[]
     */
    public function getUsedInTestsArray(): array
    {
        if (empty($this->usedInTests)) {
            return [];
        }

        return array_map('trim', explode(',', $this->usedInTests));
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getEnvironments(): ?array
    {
        return $this->environments;
    }

    /**
     * @param string[]|null $environments
     */
    public function setEnvironments(?array $environments): static
    {
        // Normalize empty array to null (both mean global)
        $this->environments = empty($environments) ? null : array_values(array_unique($environments));

        return $this;
    }

    /**
     * Check if this variable applies to a specific environment.
     */
    public function appliesToEnvironment(string $environment): bool
    {
        // Null or empty = global, applies to all
        if (empty($this->environments)) {
            return true;
        }

        return in_array($environment, $this->environments, true);
    }

    /**
     * Check if this variable is global (applies to all environments).
     */
    public function isGlobal(): bool
    {
        return empty($this->environments);
    }

    /**
     * Add an environment to the list.
     */
    public function addEnvironment(string $environment): static
    {
        if ($this->environments === null) {
            $this->environments = [];
        }

        if (!in_array($environment, $this->environments, true)) {
            $this->environments[] = $environment;
        }

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
