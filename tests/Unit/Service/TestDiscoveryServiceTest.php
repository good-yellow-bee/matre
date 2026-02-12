<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TestDiscoveryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class TestDiscoveryServiceTest extends TestCase
{
    private string $tmpDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/matre_test_discovery_' . uniqid();
        $this->filesystem->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    public function testIsCacheAvailableReturnsFalseWhenNoCacheAndNoDevMode(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->isCacheAvailable());
    }

    public function testIsCacheAvailableReturnsTrueWhenDevModulePathExists(): void
    {
        $devPath = $this->tmpDir . '/dev-module';
        $this->filesystem->mkdir($devPath);

        $service = $this->createService(devModulePath: $devPath);

        $this->assertTrue($service->isCacheAvailable());
    }

    public function testGetCachePathReturnsNullWhenNoCacheExists(): void
    {
        $service = $this->createService();

        $this->assertNull($service->getCachePath());
    }

    public function testGetCachePathReturnsDevPathForRelativePath(): void
    {
        $this->filesystem->mkdir($this->tmpDir . '/my-module');

        $service = $this->createService(
            projectDir: $this->tmpDir,
            devModulePath: 'my-module',
        );

        $this->assertSame($this->tmpDir . '/my-module', $service->getCachePath());
    }

    public function testGetCachePathReturnsDevPathForAbsolutePath(): void
    {
        $devPath = $this->tmpDir . '/absolute-module';
        $this->filesystem->mkdir($devPath);

        $service = $this->createService(devModulePath: $devPath);

        $this->assertSame($devPath, $service->getCachePath());
    }

    public function testGetCachePathReturnsCacheDirWhenGitExists(): void
    {
        $this->filesystem->mkdir($this->tmpDir . '/var/test-module-cache/.git');

        $service = $this->createService(projectDir: $this->tmpDir);

        $this->assertSame($this->tmpDir . '/var/test-module-cache', $service->getCachePath());
    }

    public function testGetMftfTestsReturnsEmptyWhenNoCache(): void
    {
        $service = $this->createService();

        $this->assertSame([], $service->getMftfTests());
    }

    public function testGetMftfTestsParsesTestNamesFromXml(): void
    {
        $testDir = $this->tmpDir . '/module/Test/Mftf/Test';
        $this->filesystem->mkdir($testDir);

        $this->createTestXml($testDir . '/CheckoutTest.xml', 'MOEC1234Test', 'checkout');
        $this->createTestXml($testDir . '/PricingTest.xml', 'MOEC5678Test', 'pricing');

        $service = $this->createService(devModulePath: $this->tmpDir . '/module');

        $this->assertSame(['MOEC1234Test', 'MOEC5678Test'], $service->getMftfTests());
    }

    public function testGetMftfGroupsReturnsEmptyWhenNoCache(): void
    {
        $service = $this->createService();

        $this->assertSame([], $service->getMftfGroups());
    }

    public function testGetMftfGroupsParsesGroupNamesFromXml(): void
    {
        $testDir = $this->tmpDir . '/module/Test/Mftf/Test';
        $this->filesystem->mkdir($testDir);

        $this->createTestXml($testDir . '/CheckoutTest.xml', 'MOEC1234Test', 'checkout');
        $this->createTestXml($testDir . '/PricingTest.xml', 'MOEC5678Test', 'pricing');

        $service = $this->createService(devModulePath: $this->tmpDir . '/module');

        $this->assertSame(['checkout', 'pricing'], $service->getMftfGroups());
    }

    public function testResolveGroupToTestsReturnsMatchingTests(): void
    {
        $testDir = $this->tmpDir . '/module/Test/Mftf/Test';
        $this->filesystem->mkdir($testDir);

        $this->createTestXml($testDir . '/CheckoutTest.xml', 'MOEC1234Test', 'checkout');
        $this->createTestXml($testDir . '/PricingTest.xml', 'MOEC5678Test', 'pricing');
        $this->createTestXml($testDir . '/AnotherCheckoutTest.xml', 'MOEC9999Test', 'checkout');

        $service = $this->createService(devModulePath: $this->tmpDir . '/module');

        $this->assertSame(['MOEC1234Test', 'MOEC9999Test'], $service->resolveGroupToTests('checkout'));
    }

    public function testResolveGroupToTestsReturnsEmptyForNonMatchingGroup(): void
    {
        $testDir = $this->tmpDir . '/module/Test/Mftf/Test';
        $this->filesystem->mkdir($testDir);

        $this->createTestXml($testDir . '/CheckoutTest.xml', 'MOEC1234Test', 'checkout');

        $service = $this->createService(devModulePath: $this->tmpDir . '/module');

        $this->assertSame([], $service->resolveGroupToTests('nonexistent'));
    }

    private function createService(
        ?string $projectDir = null,
        ?string $devModulePath = null,
    ): TestDiscoveryService {
        return new TestDiscoveryService(
            $this->createStub(LoggerInterface::class),
            $projectDir ?? $this->tmpDir . '/nonexistent-project',
            'https://example.com/repo.git',
            'main',
            null,
            null,
            $devModulePath,
        );
    }

    private function createTestXml(string $path, string $testName, string $group): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <tests xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:mftf:Test/etc/testSchema.xsd">
                <test name="{$testName}">
                    <annotations>
                        <group value="{$group}"/>
                    </annotations>
                </test>
            </tests>
            XML;

        $this->filesystem->dumpFile($path, $xml);
    }
}
