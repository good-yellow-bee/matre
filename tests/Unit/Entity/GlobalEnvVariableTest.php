<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GlobalEnvVariable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GlobalEnvVariable entity.
 */
class GlobalEnvVariableTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertNull($var->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $var->getCreatedAt());
        $this->assertNull($var->getUpdatedAt());
        $this->assertNull($var->getEnvironments());
    }

    public function testNameGetterAndSetter(): void
    {
        $var = new GlobalEnvVariable();

        $result = $var->setName('SELENIUM_HOST');

        $this->assertEquals('SELENIUM_HOST', $var->getName());
        $this->assertSame($var, $result);
    }

    public function testValueGetterAndSetter(): void
    {
        $var = new GlobalEnvVariable();

        $var->setValue('http://selenium:4444');
        $this->assertEquals('http://selenium:4444', $var->getValue());
    }

    public function testUsedInTestsGetterAndSetter(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertNull($var->getUsedInTests());

        $var->setUsedInTests('MOEC1625, MOEC1626, MOEC1627');
        $this->assertEquals('MOEC1625, MOEC1626, MOEC1627', $var->getUsedInTests());
    }

    public function testGetUsedInTestsArray(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertEquals([], $var->getUsedInTestsArray());

        $var->setUsedInTests('MOEC1625, MOEC1626, MOEC1627');
        $array = $var->getUsedInTestsArray();

        $this->assertCount(3, $array);
        $this->assertEquals('MOEC1625', $array[0]);
        $this->assertEquals('MOEC1626', $array[1]);
        $this->assertEquals('MOEC1627', $array[2]);
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertNull($var->getDescription());

        $var->setDescription('Selenium grid host URL');
        $this->assertEquals('Selenium grid host URL', $var->getDescription());
    }

    public function testEnvironmentsGetterAndSetter(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertNull($var->getEnvironments());

        $var->setEnvironments(['stage-us', 'preprod-us']);
        $this->assertEquals(['stage-us', 'preprod-us'], $var->getEnvironments());

        // Empty array normalizes to null
        $var->setEnvironments([]);
        $this->assertNull($var->getEnvironments());
    }

    public function testEnvironmentsDeduplicates(): void
    {
        $var = new GlobalEnvVariable();

        $var->setEnvironments(['stage-us', 'stage-us', 'preprod-us']);
        $envs = $var->getEnvironments();

        $this->assertCount(2, $envs);
    }

    public function testAddEnvironment(): void
    {
        $var = new GlobalEnvVariable();

        $var->addEnvironment('stage-us');
        $this->assertEquals(['stage-us'], $var->getEnvironments());

        $var->addEnvironment('preprod-us');
        $this->assertContains('stage-us', $var->getEnvironments());
        $this->assertContains('preprod-us', $var->getEnvironments());

        // Adding duplicate should not add again
        $var->addEnvironment('stage-us');
        $this->assertCount(2, $var->getEnvironments());
    }

    public function testAppliesToEnvironment(): void
    {
        $var = new GlobalEnvVariable();

        // Global variable (no environments) applies to all
        $this->assertTrue($var->appliesToEnvironment('stage-us'));
        $this->assertTrue($var->appliesToEnvironment('preprod-us'));

        // Scoped variable only applies to specific environments
        $var->setEnvironments(['stage-us']);
        $this->assertTrue($var->appliesToEnvironment('stage-us'));
        $this->assertFalse($var->appliesToEnvironment('preprod-us'));
    }

    public function testIsGlobal(): void
    {
        $var = new GlobalEnvVariable();

        $this->assertTrue($var->isGlobal());

        $var->setEnvironments(['stage-us']);
        $this->assertFalse($var->isGlobal());

        $var->setEnvironments(null);
        $this->assertTrue($var->isGlobal());
    }

    public function testFluentInterface(): void
    {
        $var = new GlobalEnvVariable();

        $result = $var
            ->setName('TEST_VAR')
            ->setValue('test_value')
            ->setUsedInTests('TEST1, TEST2')
            ->setDescription('Test variable')
            ->setEnvironments(['stage-us']);

        $this->assertSame($var, $result);
    }

    public function testToString(): void
    {
        $var = new GlobalEnvVariable();
        $var->setName('MY_VARIABLE');

        $this->assertEquals('MY_VARIABLE', (string) $var);
    }
}
