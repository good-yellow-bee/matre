<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TestResult Entity.
 *
 * Stores individual test case results from a test run
 */
#[ORM\Entity(repositoryClass: TestResultRepository::class)]
#[ORM\Table(name: 'matre_test_results')]
#[ORM\Index(name: 'IDX_TEST_RESULT_RUN', columns: ['test_run_id'])]
#[ORM\Index(name: 'IDX_TEST_RESULT_STATUS', columns: ['status'])]
class TestResult
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_BROKEN = 'broken';

    public const STATUSES = [
        self::STATUS_PASSED => 'Passed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_SKIPPED => 'Skipped',
        self::STATUS_BROKEN => 'Broken',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TestRun::class, inversedBy: 'results')]
    #[ORM\JoinColumn(name: 'test_run_id', nullable: false, onDelete: 'CASCADE')]
    private TestRun $testRun;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $testName;

    /**
     * Test identifier (e.g., MOEC-1625, MOEC-PW-001).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $testId = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [
        self::STATUS_PASSED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
        self::STATUS_BROKEN,
    ])]
    private string $status;

    /**
     * Test duration in seconds.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $duration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /**
     * Path to screenshot file if test failed.
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $screenshotPath = null;

    /**
     * Path to Allure result file.
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $allureResultPath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->testName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTestRun(): TestRun
    {
        return $this->testRun;
    }

    public function setTestRun(TestRun $testRun): static
    {
        $this->testRun = $testRun;

        return $this;
    }

    public function getTestName(): string
    {
        return $this->testName;
    }

    public function setTestName(string $testName): static
    {
        $this->testName = $testName;

        return $this;
    }

    public function getTestId(): ?string
    {
        return $this->testId;
    }

    public function setTestId(?string $testId): static
    {
        $this->testId = $testId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Get formatted duration string.
     */
    public function getDurationFormatted(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        if ($this->duration < 1) {
            return sprintf('%dms', (int) ($this->duration * 1000));
        }

        if ($this->duration < 60) {
            return sprintf('%.1fs', $this->duration);
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%dm %.0fs', $minutes, $seconds);
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getScreenshotPath(): ?string
    {
        return $this->screenshotPath;
    }

    public function setScreenshotPath(?string $screenshotPath): static
    {
        $this->screenshotPath = $screenshotPath;

        return $this;
    }

    public function getAllureResultPath(): ?string
    {
        return $this->allureResultPath;
    }

    public function setAllureResultPath(?string $allureResultPath): static
    {
        $this->allureResultPath = $allureResultPath;

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

    /**
     * Check if test passed.
     */
    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    /**
     * Check if test failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if test was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Check if test is broken.
     */
    public function isBroken(): bool
    {
        return $this->status === self::STATUS_BROKEN;
    }

    /**
     * Check if test has a screenshot.
     */
    public function hasScreenshot(): bool
    {
        return $this->screenshotPath !== null;
    }
}
