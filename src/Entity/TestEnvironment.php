<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestEnvironmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TestEnvironment Entity.
 *
 * Stores configuration for target Magento environments (dev, stage, preprod)
 * Each environment has base URL, credentials, and custom env variables
 */
#[ORM\Entity(repositoryClass: TestEnvironmentRepository::class)]
#[ORM\Table(name: 'matre_test_environments')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEST_ENV_NAME', columns: ['name'])]
#[ORM\Index(name: 'IDX_TEST_ENV_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_TEST_ENV_CODE_REGION', columns: ['code', 'region'])]
#[UniqueEntity(fields: ['name'], message: 'An environment with this name already exists.')]
#[ORM\HasLifecycleCallbacks]
class TestEnvironment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Environment name cannot be blank.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Name must be at least {{ limit }} characters long.',
        maxMessage: 'Name cannot be longer than {{ limit }} characters.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'Name must contain only lowercase letters, numbers, and dashes.',
    )]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'Code must contain only lowercase letters, numbers, and dashes.',
    )]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    private string $region;

    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank(message: 'Base URL cannot be blank.')]
    #[Assert\Url(message: 'Base URL must be a valid URL.')]
    private string $baseUrl;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $backendName = 'admin';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $adminUsername = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $adminPassword = null;

    /**
     * Additional environment variables as JSON.
     * Stores all variables from .env file that don't have dedicated fields.
     * Format: key => value (string) or key => {value: string, usedInTests: string|null}.
     *
     * @var array<string, string|array{value: string, usedInTests: string|null}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $envVariables = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN)]
    private bool $isActive = true;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';

        return $this;
    }

    public function getBackendName(): string
    {
        return $this->backendName;
    }

    public function setBackendName(string $backendName): static
    {
        $this->backendName = $backendName;

        return $this;
    }

    public function getAdminUsername(): ?string
    {
        return $this->adminUsername;
    }

    public function setAdminUsername(?string $adminUsername): static
    {
        $this->adminUsername = $adminUsername;

        return $this;
    }

    public function getAdminPassword(): ?string
    {
        return $this->adminPassword;
    }

    public function setAdminPassword(?string $adminPassword): static
    {
        $this->adminPassword = $adminPassword;

        return $this;
    }

    /**
     * Get env variables as simple key-value pairs.
     * Normalizes both old format (key => value) and new format (key => {value, usedInTests}).
     *
     * @return array<string, string>
     */
    public function getEnvVariables(): array
    {
        $result = [];

        foreach ($this->envVariables as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                // New format: {value: "...", usedInTests: "..."}
                $result[$key] = $value['value'];
            } else {
                // Old format: direct value
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Get env variables with full metadata (value + usedInTests).
     *
     * @return array<string, array{value: string, usedInTests: string|null}>
     */
    public function getEnvVariablesWithMetadata(): array
    {
        $result = [];

        foreach ($this->envVariables as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                // New format
                $result[$key] = [
                    'value' => $value['value'],
                    'usedInTests' => $value['usedInTests'] ?? null,
                ];
            } else {
                // Old format - convert to new
                $result[$key] = [
                    'value' => (string) $value,
                    'usedInTests' => null,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array<string, string|array> $envVariables
     */
    public function setEnvVariables(array $envVariables): static
    {
        $this->envVariables = $envVariables;

        return $this;
    }

    /**
     * Get single env variable value.
     */
    public function getEnvVariable(string $key): ?string
    {
        if (!isset($this->envVariables[$key])) {
            return null;
        }

        $value = $this->envVariables[$key];

        if (is_array($value) && isset($value['value'])) {
            return $value['value'];
        }

        return (string) $value;
    }

    /**
     * Set single env variable with optional metadata.
     */
    public function setEnvVariable(string $key, string $value, ?string $usedInTests = null): static
    {
        if (null !== $usedInTests) {
            $this->envVariables[$key] = [
                'value' => $value,
                'usedInTests' => $usedInTests,
            ];
        } else {
            // Check if existing entry has metadata, preserve it
            if (isset($this->envVariables[$key]) && is_array($this->envVariables[$key])) {
                $this->envVariables[$key]['value'] = $value;
            } else {
                $this->envVariables[$key] = $value;
            }
        }

        return $this;
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Alias for getIsActive() for cleaner API.
     */
    public function isActive(): bool
    {
        return $this->isActive;
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

    /**
     * Get full admin URL.
     */
    public function getAdminUrl(): string
    {
        return $this->baseUrl . $this->backendName;
    }

    /**
     * Build complete .env content for test execution.
     */
    public function buildEnvContent(): string
    {
        $lines = [
            "MAGENTO_BASE_URL={$this->baseUrl}",
            "MAGENTO_BACKEND_NAME={$this->backendName}",
        ];

        if ($this->adminUsername) {
            $lines[] = "MAGENTO_ADMIN_USERNAME={$this->adminUsername}";
        }

        if ($this->adminPassword) {
            $lines[] = "MAGENTO_ADMIN_PASSWORD={$this->adminPassword}";
        }

        // Use getEnvVariables() to normalize both old and new formats
        foreach ($this->getEnvVariables() as $key => $value) {
            $lines[] = "{$key}={$value}";
        }

        return implode("\n", $lines);
    }
}
