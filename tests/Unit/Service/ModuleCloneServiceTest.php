<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ModuleCloneService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

class ModuleCloneServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/matre_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }

    public function testIsDevModeEnabledReturnsFalseWhenNoDevPath(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->isDevModeEnabled());
    }

    public function testIsDevModeEnabledReturnsTrueWhenDevPathSet(): void
    {
        $service = $this->createService(devModulePath: './test-module');

        $this->assertTrue($service->isDevModeEnabled());
    }

    public function testGetDevModulePathReturnsNullWhenEmpty(): void
    {
        $service = $this->createService();

        $this->assertNull($service->getDevModulePath());
    }

    public function testGetDevModulePathResolvesRelativePath(): void
    {
        $service = $this->createService(projectDir: '/app', devModulePath: 'test-module');

        $this->assertSame('/app/test-module', $service->getDevModulePath());
    }

    public function testGetDevModulePathReturnsAbsolutePathAsIs(): void
    {
        $service = $this->createService(devModulePath: '/absolute/path/module');

        $this->assertSame('/absolute/path/module', $service->getDevModulePath());
    }

    public function testGetDefaultTargetPathReturnsDevPathInDevMode(): void
    {
        $service = $this->createService(projectDir: '/app', devModulePath: 'my-module');

        $this->assertSame('/app/my-module', $service->getDefaultTargetPath());
    }

    public function testGetDefaultTargetPathReturnsVarPathNormally(): void
    {
        $service = $this->createService(projectDir: '/app');

        $this->assertSame('/app/var/test-modules/current', $service->getDefaultTargetPath());
    }

    public function testUseLocalModuleCreatesSymlink(): void
    {
        $sourcePath = $this->tempDir . '/source-module';
        mkdir($sourcePath);
        file_put_contents($sourcePath . '/test.txt', 'hello');

        $targetPath = $this->tempDir . '/target-link';

        $service = $this->createService(projectDir: $this->tempDir, devModulePath: $sourcePath);
        $service->useLocalModule($targetPath);

        $this->assertTrue(is_link($targetPath));
        $this->assertFileExists($targetPath . '/test.txt');
    }

    public function testUseLocalModuleThrowsWhenSourceMissing(): void
    {
        $service = $this->createService(devModulePath: '/nonexistent/path');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $service->useLocalModule($this->tempDir . '/target');
    }

    public function testCleanupRemovesExistingDirectory(): void
    {
        $dirToClean = $this->tempDir . '/cleanup-target';
        mkdir($dirToClean);
        file_put_contents($dirToClean . '/file.txt', 'data');

        $service = $this->createService();
        $service->cleanup($dirToClean);

        $this->assertDirectoryDoesNotExist($dirToClean);
    }

    public function testCleanupHandlesNonexistentPath(): void
    {
        $service = $this->createService();
        $service->cleanup($this->tempDir . '/does-not-exist');

        $this->addToAssertionCount(1);
    }

    public function testGetModuleRepoReturnsConfiguredValue(): void
    {
        $service = $this->createService(moduleRepo: 'https://github.com/org/repo.git');

        $this->assertSame('https://github.com/org/repo.git', $service->getModuleRepo());
    }

    public function testGetModuleBranchReturnsConfiguredValue(): void
    {
        $service = $this->createService(moduleBranch: 'develop');

        $this->assertSame('develop', $service->getModuleBranch());
    }

    private function createService(
        string $moduleRepo = 'https://example.com/repo.git',
        string $moduleBranch = 'main',
        string $projectDir = '/app',
        ?string $devModulePath = null,
    ): ModuleCloneService {
        return new ModuleCloneService(
            $this->createStub(LoggerInterface::class),
            $this->createStub(LockFactory::class),
            $moduleRepo,
            $moduleBranch,
            $projectDir,
            devModulePath: $devModulePath,
        );
    }

    private function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }

        // Remove symlinks at top level
        foreach (glob($dir . '/*') as $item) {
            if (is_link($item)) {
                unlink($item);
            }
        }

        rmdir($dir);
    }
}
