<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TestReport Entity.
 *
 * Stores generated test reports (Allure, HTML, JSON)
 */
#[ORM\Entity(repositoryClass: TestReportRepository::class)]
#[ORM\Table(name: 'matre_test_reports')]
#[ORM\Index(name: 'IDX_TEST_REPORT_RUN', columns: ['test_run_id'])]
#[ORM\Index(name: 'IDX_TEST_REPORT_TYPE', columns: ['report_type'])]
#[ORM\Index(name: 'IDX_TEST_REPORT_EXPIRES', columns: ['expires_at'])]
class TestReport
{
    public const TYPE_ALLURE = 'allure';
    public const TYPE_HTML = 'html';
    public const TYPE_JSON = 'json';

    public const TYPES = [
        self::TYPE_ALLURE => 'Allure Report',
        self::TYPE_HTML => 'HTML Report',
        self::TYPE_JSON => 'JSON Report',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TestRun::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(name: 'test_run_id', nullable: false, onDelete: 'CASCADE')]
    private TestRun $testRun;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [self::TYPE_ALLURE, self::TYPE_HTML, self::TYPE_JSON])]
    private string $reportType;

    /**
     * Path to report file/directory.
     */
    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    private string $filePath;

    /**
     * Public URL to access the report.
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $publicUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $generatedAt;

    /**
     * When this report should be cleaned up.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s Report #%d', $this->getTypeLabel(), $this->id ?? 0);
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

    public function getReportType(): string
    {
        return $this->reportType;
    }

    public function setReportType(string $reportType): static
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->reportType] ?? $this->reportType;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    public function setPublicUrl(?string $publicUrl): static
    {
        $this->publicUrl = $publicUrl;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Set expiration from now.
     */
    public function setExpiresIn(int $days): static
    {
        $this->expiresAt = new \DateTimeImmutable("+{$days} days");

        return $this;
    }

    /**
     * Check if report is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Check if this is an Allure report.
     */
    public function isAllure(): bool
    {
        return $this->reportType === self::TYPE_ALLURE;
    }

    /**
     * Check if report has public URL.
     */
    public function hasPublicUrl(): bool
    {
        return $this->publicUrl !== null;
    }
}
