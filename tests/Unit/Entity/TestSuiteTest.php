<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TestSuite entity.
 */
class TestSuiteTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $suite = new TestSuite();

        $this->assertNull($suite->getId());
        $this->assertTrue($suite->getIsActive());
        $this->assertTrue($suite->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $suite->getCreatedAt());
        $this->assertNull($suite->getUpdatedAt());
        $this->assertNull($suite->getCronExpression());
    }

    public function testNameGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $result = $suite->setName('Pricing Suite');

        $this->assertEquals('Pricing Suite', $suite->getName());
        $this->assertSame($suite, $result);
    }

    public function testTypeGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $suite->setType(TestSuite::TYPE_MFTF_GROUP);
        $this->assertEquals(TestSuite::TYPE_MFTF_GROUP, $suite->getType());
        $this->assertEquals('MFTF Group', $suite->getTypeLabel());

        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $this->assertEquals('MFTF Test', $suite->getTypeLabel());

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $this->assertEquals('Playwright Group', $suite->getTypeLabel());

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_TEST);
        $this->assertEquals('Playwright Test', $suite->getTypeLabel());
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $this->assertNull($suite->getDescription());

        $suite->setDescription('Tests for pricing functionality');
        $this->assertEquals('Tests for pricing functionality', $suite->getDescription());
    }

    public function testTestPatternGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $suite->setTestPattern('pricing');
        $this->assertEquals('pricing', $suite->getTestPattern());

        $suite->setTestPattern('MOEC1625');
        $this->assertEquals('MOEC1625', $suite->getTestPattern());
    }

    public function testExcludedTestsGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $this->assertNull($suite->getExcludedTests());
        $this->assertSame([], $suite->getExcludedTestsList());

        $suite->setExcludedTests('MOEC11676, MOEC2609ES');
        $this->assertEquals('MOEC11676, MOEC2609ES', $suite->getExcludedTests());
        $this->assertSame(['MOEC11676', 'MOEC2609ES'], $suite->getExcludedTestsList());
    }

    public function testExcludedTestsListParsesCommaAndNewlineSeparatedValues(): void
    {
        $suite = new TestSuite();
        $suite->setExcludedTests(" MOEC11676,\nMOEC2609ES\r\nMOEC11676 ,, ");

        $this->assertSame(['MOEC11676', 'MOEC2609ES'], $suite->getExcludedTestsList());
    }

    public function testExcludedTestsSetterNormalizesEmptyStringToNull(): void
    {
        $suite = new TestSuite();
        $suite->setExcludedTests('   ');

        $this->assertNull($suite->getExcludedTests());
        $this->assertSame([], $suite->getExcludedTestsList());
    }

    public function testExcludedTestsSetterAcceptsNull(): void
    {
        $suite = new TestSuite();
        $suite->setExcludedTests(null);

        $this->assertNull($suite->getExcludedTests());
        $this->assertSame([], $suite->getExcludedTestsList());
    }

    public function testCronExpressionGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $this->assertNull($suite->getCronExpression());

        $suite->setCronExpression('0 2 * * *');
        $this->assertEquals('0 2 * * *', $suite->getCronExpression());

        $suite->setCronExpression(null);
        $this->assertNull($suite->getCronExpression());
    }

    public function testIsActiveGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $this->assertTrue($suite->getIsActive());
        $this->assertTrue($suite->isActive());

        $suite->setIsActive(false);
        $this->assertFalse($suite->getIsActive());
        $this->assertFalse($suite->isActive());
    }

    public function testEstimatedDurationGetterAndSetter(): void
    {
        $suite = new TestSuite();

        $this->assertNull($suite->getEstimatedDuration());

        $suite->setEstimatedDuration(300);
        $this->assertEquals(300, $suite->getEstimatedDuration());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $suite = new TestSuite();
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');

        $suite->setCreatedAt($date);
        $this->assertEquals($date, $suite->getCreatedAt());
    }

    public function testIsMftf(): void
    {
        $suite = new TestSuite();

        $suite->setType(TestSuite::TYPE_MFTF_GROUP);
        $this->assertTrue($suite->isMftf());

        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $this->assertTrue($suite->isMftf());

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $this->assertFalse($suite->isMftf());

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_TEST);
        $this->assertFalse($suite->isMftf());
    }

    public function testIsPlaywright(): void
    {
        $suite = new TestSuite();

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $this->assertTrue($suite->isPlaywright());

        $suite->setType(TestSuite::TYPE_PLAYWRIGHT_TEST);
        $this->assertTrue($suite->isPlaywright());

        $suite->setType(TestSuite::TYPE_MFTF_GROUP);
        $this->assertFalse($suite->isPlaywright());

        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $this->assertFalse($suite->isPlaywright());
    }

    public function testIsScheduled(): void
    {
        $suite = new TestSuite();

        $this->assertFalse($suite->isScheduled());

        $suite->setCronExpression('0 * * * *');
        $this->assertTrue($suite->isScheduled());

        $suite->setCronExpression(null);
        $this->assertFalse($suite->isScheduled());
    }

    public function testFluentInterface(): void
    {
        $suite = new TestSuite();

        $result = $suite
            ->setName('Test Suite')
            ->setType(TestSuite::TYPE_MFTF_GROUP)
            ->setTestPattern('group1')
            ->setDescription('Description')
            ->setCronExpression('0 * * * *')
            ->setIsActive(true)
            ->setEstimatedDuration(600);

        $this->assertSame($suite, $result);
    }

    public function testToString(): void
    {
        $suite = new TestSuite();
        $suite->setName('My Test Suite');

        $this->assertEquals('My Test Suite', (string) $suite);
    }

    public function testTypeConstants(): void
    {
        $this->assertEquals('mftf_group', TestSuite::TYPE_MFTF_GROUP);
        $this->assertEquals('mftf_test', TestSuite::TYPE_MFTF_TEST);
        $this->assertEquals('playwright_group', TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $this->assertEquals('playwright_test', TestSuite::TYPE_PLAYWRIGHT_TEST);

        $this->assertArrayHasKey(TestSuite::TYPE_MFTF_GROUP, TestSuite::TYPES);
        $this->assertArrayHasKey(TestSuite::TYPE_MFTF_TEST, TestSuite::TYPES);
        $this->assertArrayHasKey(TestSuite::TYPE_PLAYWRIGHT_GROUP, TestSuite::TYPES);
        $this->assertArrayHasKey(TestSuite::TYPE_PLAYWRIGHT_TEST, TestSuite::TYPES);
    }
}
