<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TestEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TestEnvironment entity.
 */
class TestEnvironmentTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $env = new TestEnvironment();

        $this->assertNull($env->getId());
        $this->assertTrue($env->getIsActive());
        $this->assertEquals('admin', $env->getBackendName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $env->getCreatedAt());
        $this->assertNull($env->getUpdatedAt());
        $this->assertEquals([], $env->getEnvVariables());
    }

    public function testNameGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $result = $env->setName('preprod-us');

        $this->assertEquals('preprod-us', $env->getName());
        $this->assertSame($env, $result);
    }

    public function testCodeGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $env->setCode('preprod');
        $this->assertEquals('preprod', $env->getCode());
    }

    public function testRegionGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $env->setRegion('us');
        $this->assertEquals('us', $env->getRegion());
    }

    public function testBaseUrlGetterAndSetterEnsuresTrailingSlash(): void
    {
        $env = new TestEnvironment();

        $env->setBaseUrl('https://example.com');
        $this->assertEquals('https://example.com/', $env->getBaseUrl());

        $env->setBaseUrl('https://example.com/');
        $this->assertEquals('https://example.com/', $env->getBaseUrl());
    }

    public function testBackendNameGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $env->setBackendName('backend');
        $this->assertEquals('backend', $env->getBackendName());
    }

    public function testAdminCredentials(): void
    {
        $env = new TestEnvironment();

        $this->assertNull($env->getAdminUsername());
        $this->assertNull($env->getAdminPassword());

        $env->setAdminUsername('admin');
        $env->setAdminPassword('secret123');

        $this->assertEquals('admin', $env->getAdminUsername());
        $this->assertEquals('secret123', $env->getAdminPassword());
    }

    public function testEnvVariablesOldFormat(): void
    {
        $env = new TestEnvironment();

        $env->setEnvVariables([
            'API_KEY' => 'secret123',
            'DEBUG' => 'true',
        ]);

        $vars = $env->getEnvVariables();
        $this->assertEquals('secret123', $vars['API_KEY']);
        $this->assertEquals('true', $vars['DEBUG']);
    }

    public function testEnvVariablesNewFormatWithMetadata(): void
    {
        $env = new TestEnvironment();

        $env->setEnvVariables([
            'API_KEY' => [
                'value' => 'secret123',
                'usedInTests' => 'TestCase1, TestCase2',
            ],
        ]);

        $vars = $env->getEnvVariables();
        $this->assertEquals('secret123', $vars['API_KEY']);

        $varsWithMeta = $env->getEnvVariablesWithMetadata();
        $this->assertEquals('secret123', $varsWithMeta['API_KEY']['value']);
        $this->assertEquals('TestCase1, TestCase2', $varsWithMeta['API_KEY']['usedInTests']);
    }

    public function testGetEnvVariable(): void
    {
        $env = new TestEnvironment();
        $env->setEnvVariables(['KEY' => 'value']);

        $this->assertEquals('value', $env->getEnvVariable('KEY'));
        $this->assertNull($env->getEnvVariable('NONEXISTENT'));
    }

    public function testSetEnvVariable(): void
    {
        $env = new TestEnvironment();

        $env->setEnvVariable('KEY', 'value');
        $this->assertEquals('value', $env->getEnvVariable('KEY'));

        $env->setEnvVariable('KEY2', 'value2', 'TestCase');
        $varsWithMeta = $env->getEnvVariablesWithMetadata();
        $this->assertEquals('value2', $varsWithMeta['KEY2']['value']);
        $this->assertEquals('TestCase', $varsWithMeta['KEY2']['usedInTests']);
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $this->assertNull($env->getDescription());

        $env->setDescription('US preprod environment');
        $this->assertEquals('US preprod environment', $env->getDescription());
    }

    public function testIsActiveGetterAndSetter(): void
    {
        $env = new TestEnvironment();

        $this->assertTrue($env->getIsActive());
        $this->assertTrue($env->isActive());

        $env->setIsActive(false);
        $this->assertFalse($env->getIsActive());
        $this->assertFalse($env->isActive());
    }

    public function testGetAdminUrl(): void
    {
        $env = new TestEnvironment();
        $env->setBaseUrl('https://example.com');
        $env->setBackendName('admin');

        $this->assertEquals('https://example.com/admin', $env->getAdminUrl());
    }

    public function testBuildEnvContent(): void
    {
        $env = new TestEnvironment();
        $env->setBaseUrl('https://example.com');
        $env->setBackendName('admin');
        $env->setAdminUsername('user');
        $env->setAdminPassword('pass');
        $env->setEnvVariables(['CUSTOM_VAR' => 'custom_value']);

        $content = $env->buildEnvContent();

        $this->assertStringContainsString('MAGENTO_BASE_URL=https://example.com/', $content);
        $this->assertStringContainsString('MAGENTO_BACKEND_NAME=admin', $content);
        $this->assertStringContainsString('MAGENTO_ADMIN_USERNAME=user', $content);
        $this->assertStringContainsString('MAGENTO_ADMIN_PASSWORD=pass', $content);
        $this->assertStringContainsString('CUSTOM_VAR=custom_value', $content);
    }

    public function testFluentInterface(): void
    {
        $env = new TestEnvironment();

        $result = $env
            ->setName('test-env')
            ->setCode('dev')
            ->setRegion('us')
            ->setBaseUrl('https://example.com')
            ->setBackendName('admin')
            ->setIsActive(true)
            ->setDescription('Test');

        $this->assertSame($env, $result);
    }

    public function testToString(): void
    {
        $env = new TestEnvironment();
        $env->setName('my-environment');

        $this->assertEquals('my-environment', (string) $env);
    }
}
