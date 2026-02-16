<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\ModuleCloneService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * Integration test for module clone: dev mode → symlink, clone → pull workflow.
 */
class ModuleImportTest extends KernelTestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/matre_module_test_' . uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
        parent::tearDown();
    }

    // =====================
    // Dev Mode
    // =====================

    public function testDevModeEnabledWhenPathSet(): void
    {
        $devPath = $this->tempDir . '/dev-module';
        $this->filesystem->mkdir($devPath);

        $service = $this->buildService(devModulePath: $devPath);

        $this->assertTrue($service->isDevModeEnabled());
        $this->assertSame($devPath, $service->getDevModulePath());
    }

    public function testDevModeDisabledWhenPathEmpty(): void
    {
        $service = $this->buildService(devModulePath: null);

        $this->assertFalse($service->isDevModeEnabled());
        $this->assertNull($service->getDevModulePath());
    }

    public function testUseLocalModuleCreatesSymlink(): void
    {
        $devPath = $this->tempDir . '/dev-module';
        $targetPath = $this->tempDir . '/target/current';
        $this->filesystem->mkdir($devPath);
        file_put_contents($devPath . '/composer.json', '{"name":"test"}');

        $service = $this->buildService(devModulePath: $devPath);
        $service->useLocalModule($targetPath);

        $this->assertTrue(is_link($targetPath));
        $this->assertFileExists($targetPath . '/composer.json');
    }

    public function testUseLocalModuleReplacesExistingTarget(): void
    {
        $devPath = $this->tempDir . '/dev-module';
        $targetPath = $this->tempDir . '/target/current';
        $this->filesystem->mkdir([$devPath, $targetPath]);
        file_put_contents($targetPath . '/old-file.txt', 'old');
        file_put_contents($devPath . '/new-file.txt', 'new');

        $service = $this->buildService(devModulePath: $devPath);
        $service->useLocalModule($targetPath);

        $this->assertTrue(is_link($targetPath));
        $this->assertFileExists($targetPath . '/new-file.txt');
        $this->assertFileDoesNotExist($targetPath . '/old-file.txt');
    }

    public function testUseLocalModuleThrowsIfPathMissing(): void
    {
        $service = $this->buildService(devModulePath: $this->tempDir . '/nonexistent');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $service->useLocalModule($this->tempDir . '/target');
    }

    // =====================
    // prepareModule (Dev Mode)
    // =====================

    public function testPrepareModuleInDevModeReturnsPath(): void
    {
        $devPath = $this->tempDir . '/dev-module';
        $this->filesystem->mkdir($devPath);

        $service = $this->buildService(devModulePath: $devPath);
        $result = $service->prepareModule();

        $this->assertSame($devPath, $result);
    }

    public function testPrepareModuleInDevModeThrowsIfMissing(): void
    {
        $service = $this->buildService(devModulePath: $this->tempDir . '/missing');

        $this->expectException(\RuntimeException::class);
        $service->prepareModule();
    }

    // =====================
    // Default Target Path
    // =====================

    public function testGetDefaultTargetPathDevMode(): void
    {
        $devPath = $this->tempDir . '/dev-module';
        $service = $this->buildService(devModulePath: $devPath);

        $this->assertSame($devPath, $service->getDefaultTargetPath());
    }

    public function testGetDefaultTargetPathNormal(): void
    {
        $service = $this->buildService(devModulePath: null);

        $expected = $this->tempDir . '/var/test-modules/current';
        $this->assertSame($expected, $service->getDefaultTargetPath());
    }

    // =====================
    // Relative Dev Path
    // =====================

    public function testRelativeDevPathResolvedFromProjectDir(): void
    {
        $service = $this->buildService(devModulePath: 'test-module');

        $expected = $this->tempDir . '/test-module';
        $this->assertSame($expected, $service->getDevModulePath());
    }

    public function testAbsoluteDevPathUsedAsIs(): void
    {
        $service = $this->buildService(devModulePath: '/absolute/path/module');

        $this->assertSame('/absolute/path/module', $service->getDevModulePath());
    }

    // =====================
    // Cleanup
    // =====================

    public function testCleanupRemovesDirectory(): void
    {
        $path = $this->tempDir . '/to-clean';
        $this->filesystem->mkdir($path);
        file_put_contents($path . '/file.txt', 'data');

        $service = $this->buildService();
        $service->cleanup($path);

        $this->assertDirectoryDoesNotExist($path);
    }

    public function testCleanupIgnoresNonexistent(): void
    {
        $service = $this->buildService();
        $service->cleanup($this->tempDir . '/nonexistent');

        // Should not throw
        $this->assertTrue(true);
    }

    // =====================
    // Commit Hash
    // =====================

    public function testGetCommitHashReturnsNullForNonGitDir(): void
    {
        $service = $this->buildService();

        $this->assertNull($service->getCommitHash($this->tempDir));
    }

    public function testGetCommitHashReturnsHashForGitRepo(): void
    {
        $repoDir = $this->tempDir . '/git-repo';
        $this->filesystem->mkdir($repoDir);

        $process = new \Symfony\Component\Process\Process(['git', 'init'], $repoDir);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->markTestSkipped('git not available');
        }

        file_put_contents($repoDir . '/file.txt', 'content');
        (new \Symfony\Component\Process\Process(['git', 'add', '.'], $repoDir))->run();
        (new \Symfony\Component\Process\Process(['git', '-c', 'user.email=test@test.com', '-c', 'user.name=Test', 'commit', '-m', 'init'], $repoDir))->run();

        $service = $this->buildService();
        $hash = $service->getCommitHash($repoDir);

        $this->assertNotNull($hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $hash);
    }

    // =====================
    // Repo Config
    // =====================

    public function testGetModuleRepoAndBranch(): void
    {
        $service = $this->buildService();

        $this->assertSame('https://github.com/test/repo.git', $service->getModuleRepo());
        $this->assertSame('main', $service->getModuleBranch());
    }

    // =====================
    // Helpers
    // =====================

    private function buildService(?string $devModulePath = ''): ModuleCloneService
    {
        $lockFactory = new LockFactory(new InMemoryStore());

        return new ModuleCloneService(
            new NullLogger(),
            $lockFactory,
            'https://github.com/test/repo.git',
            'main',
            $this->tempDir,
            null,
            null,
            $devModulePath ?: null,
        );
    }
}
