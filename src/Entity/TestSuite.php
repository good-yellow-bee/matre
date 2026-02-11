<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestSuiteRepository;
use App\Validator\ValidCronExpression;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TestSuite Entity.
 *
 * Defines a collection of tests that can be run together
 * Can be scheduled via cron expression or triggered manually
 */
#[ORM\Entity(repositoryClass: TestSuiteRepository::class)]
#[ORM\Table(name: 'matre_test_suites')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEST_SUITE_NAME', columns: ['name'])]
#[ORM\Index(name: 'IDX_TEST_SUITE_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_TEST_SUITE_TYPE', columns: ['type'])]
#[UniqueEntity(fields: ['name'], message: 'A test suite with this name already exists.')]
#[ORM\HasLifecycleCallbacks]
class TestSuite
{
    public const TYPE_MFTF_GROUP = 'mftf_group';
    public const TYPE_MFTF_TEST = 'mftf_test';
    public const TYPE_PLAYWRIGHT_GROUP = 'playwright_group';
    public const TYPE_PLAYWRIGHT_TEST = 'playwright_test';

    public const TYPES = [
        self::TYPE_MFTF_GROUP => 'MFTF Group',
        self::TYPE_MFTF_TEST => 'MFTF Test',
        self::TYPE_PLAYWRIGHT_GROUP => 'Playwright Group',
        self::TYPE_PLAYWRIGHT_TEST => 'Playwright Test',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Suite name cannot be blank.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Name must be at least {{ limit }} characters long.',
        maxMessage: 'Name cannot be longer than {{ limit }} characters.',
    )]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::TYPE_MFTF_GROUP,
        self::TYPE_MFTF_TEST,
        self::TYPE_PLAYWRIGHT_GROUP,
        self::TYPE_PLAYWRIGHT_TEST,
    ], message: 'Invalid test suite type.')]
    private string $type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Test pattern - group name or specific test name(s).
     * For MFTF: group name like "pricing" or test name like "MOEC1625"
     * For Playwright: grep pattern like "@smoke" or specific test name.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Test pattern cannot be blank.')]
    #[Assert\Length(max: 255)]
    private string $testPattern;

    /**
     * Comma/newline-separated list of exact MFTF test names to skip in group execution.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excludedTests = null;

    /**
     * Cron expression for scheduled execution.
     * Leave null for manual-only suites.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[ValidCronExpression]
    private ?string $cronExpression = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $estimatedDuration = null;

    /** @var Collection<int, TestEnvironment> */
    #[ORM\ManyToMany(targetEntity: TestEnvironment::class)]
    #[ORM\JoinTable(name: 'matre_test_suite_environments')]
    #[Assert\Count(min: 1, minMessage: 'Select at least one environment.')]
    private Collection $environments;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->environments = new ArrayCollection();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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

    public function getTestPattern(): string
    {
        return $this->testPattern;
    }

    public function setTestPattern(string $testPattern): static
    {
        $this->testPattern = $testPattern;

        return $this;
    }

    public function getExcludedTests(): ?string
    {
        return $this->excludedTests;
    }

    public function setExcludedTests(?string $excludedTests): static
    {
        $excludedTests = null !== $excludedTests ? trim($excludedTests) : null;
        $this->excludedTests = '' === $excludedTests ? null : $excludedTests;

        return $this;
    }

    /**
     * Parse excluded tests as trimmed exact test names.
     *
     * @return array<int, string>
     */
    public function getExcludedTestsList(): array
    {
        if (null === $this->excludedTests || '' === trim($this->excludedTests)) {
            return [];
        }

        $tokens = preg_split('/[\r\n,]+/', $this->excludedTests) ?: [];
        $tokens = array_map(static fn (string $value): string => trim($value), $tokens);
        $tokens = array_filter($tokens, static fn (string $value): bool => '' !== $value);

        return array_values(array_unique($tokens));
    }

    public function getCronExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(?string $cronExpression): static
    {
        $this->cronExpression = $cronExpression;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
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

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;

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

    /**
     * Check if this is an MFTF suite.
     */
    public function isMftf(): bool
    {
        return \in_array($this->type, [self::TYPE_MFTF_GROUP, self::TYPE_MFTF_TEST], true);
    }

    /**
     * Check if this is a Playwright suite.
     */
    public function isPlaywright(): bool
    {
        return \in_array($this->type, [self::TYPE_PLAYWRIGHT_GROUP, self::TYPE_PLAYWRIGHT_TEST], true);
    }

    /**
     * Check if this suite is scheduled.
     */
    public function isScheduled(): bool
    {
        return null !== $this->cronExpression;
    }

    /** @return Collection<int, TestEnvironment> */
    public function getEnvironments(): Collection
    {
        return $this->environments;
    }

    public function addEnvironment(TestEnvironment $environment): static
    {
        if (!$this->environments->contains($environment)) {
            $this->environments->add($environment);
        }

        return $this;
    }

    public function removeEnvironment(TestEnvironment $environment): static
    {
        $this->environments->removeElement($environment);

        return $this;
    }
}
