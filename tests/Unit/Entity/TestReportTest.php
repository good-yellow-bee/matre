<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TestReport;
use App\Entity\TestRun;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TestReport entity.
 */
class TestReportTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $report = new TestReport();

        $this->assertNull($report->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $report->getGeneratedAt());
        $this->assertNull($report->getExpiresAt());
        $this->assertNull($report->getPublicUrl());
    }

    public function testTestRunGetterAndSetter(): void
    {
        $report = new TestReport();
        $run = $this->createMock(TestRun::class);

        $result = $report->setTestRun($run);

        $this->assertSame($run, $report->getTestRun());
        $this->assertSame($report, $result);
    }

    public function testReportTypeGetterAndSetter(): void
    {
        $report = new TestReport();

        $report->setReportType(TestReport::TYPE_ALLURE);
        $this->assertEquals(TestReport::TYPE_ALLURE, $report->getReportType());
        $this->assertEquals('Allure Report', $report->getTypeLabel());

        $report->setReportType(TestReport::TYPE_HTML);
        $this->assertEquals('HTML Report', $report->getTypeLabel());

        $report->setReportType(TestReport::TYPE_JSON);
        $this->assertEquals('JSON Report', $report->getTypeLabel());
    }

    public function testFilePathGetterAndSetter(): void
    {
        $report = new TestReport();

        $report->setFilePath('/var/reports/allure/run-123');
        $this->assertEquals('/var/reports/allure/run-123', $report->getFilePath());
    }

    public function testPublicUrlGetterAndSetter(): void
    {
        $report = new TestReport();

        $this->assertNull($report->getPublicUrl());
        $this->assertFalse($report->hasPublicUrl());

        $report->setPublicUrl('https://reports.example.com/run-123');
        $this->assertEquals('https://reports.example.com/run-123', $report->getPublicUrl());
        $this->assertTrue($report->hasPublicUrl());
    }

    public function testGeneratedAtGetterAndSetter(): void
    {
        $report = new TestReport();
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');

        $report->setGeneratedAt($date);
        $this->assertEquals($date, $report->getGeneratedAt());
    }

    public function testExpiresAtGetterAndSetter(): void
    {
        $report = new TestReport();

        $this->assertNull($report->getExpiresAt());

        $date = new \DateTimeImmutable('2024-02-01 12:00:00');
        $report->setExpiresAt($date);
        $this->assertEquals($date, $report->getExpiresAt());
    }

    public function testSetExpiresIn(): void
    {
        $report = new TestReport();

        $before = new \DateTimeImmutable('+6 days');
        $report->setExpiresIn(7);
        $after = new \DateTimeImmutable('+8 days');

        $this->assertNotNull($report->getExpiresAt());
        $this->assertGreaterThan($before, $report->getExpiresAt());
        $this->assertLessThan($after, $report->getExpiresAt());
    }

    public function testIsExpired(): void
    {
        $report = new TestReport();

        // No expiration = not expired
        $this->assertFalse($report->isExpired());

        // Future expiration = not expired
        $report->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->assertFalse($report->isExpired());

        // Past expiration = expired
        $report->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->assertTrue($report->isExpired());
    }

    public function testIsAllure(): void
    {
        $report = new TestReport();

        $report->setReportType(TestReport::TYPE_ALLURE);
        $this->assertTrue($report->isAllure());

        $report->setReportType(TestReport::TYPE_HTML);
        $this->assertFalse($report->isAllure());
    }

    public function testFluentInterface(): void
    {
        $report = new TestReport();
        $run = $this->createMock(TestRun::class);

        $result = $report
            ->setTestRun($run)
            ->setReportType(TestReport::TYPE_ALLURE)
            ->setFilePath('/path/to/report')
            ->setPublicUrl('https://example.com/report')
            ->setExpiresIn(30);

        $this->assertSame($report, $result);
    }

    public function testToString(): void
    {
        $report = new TestReport();
        $report->setReportType(TestReport::TYPE_ALLURE);

        $string = (string) $report;
        $this->assertStringContainsString('Allure Report', $string);
    }

    public function testTypeConstants(): void
    {
        $this->assertEquals('allure', TestReport::TYPE_ALLURE);
        $this->assertEquals('html', TestReport::TYPE_HTML);
        $this->assertEquals('json', TestReport::TYPE_JSON);

        $this->assertArrayHasKey(TestReport::TYPE_ALLURE, TestReport::TYPES);
        $this->assertArrayHasKey(TestReport::TYPE_HTML, TestReport::TYPES);
        $this->assertArrayHasKey(TestReport::TYPE_JSON, TestReport::TYPES);
    }
}
