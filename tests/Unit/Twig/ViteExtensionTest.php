<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\ViteExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Filesystem\Filesystem;
use Twig\TwigFunction;

class ViteExtensionTest extends TestCase
{
    private string $tempDir;

    private Packages $packages;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vite_ext_test_' . uniqid();
        mkdir($this->tempDir . '/public/build', 0o777, true);

        $this->packages = $this->createStub(Packages::class);
        $this->packages->method('getUrl')->willReturnArgument(0);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testRenderScriptsWithManifest(): void
    {
        $this->writeManifest([
            'assets/admin.js' => [
                'file' => 'assets/admin-abc123.js',
                'name' => 'admin',
                'css' => ['assets/admin-xyz789.css'],
                'imports' => ['_vendor-abc.js'],
            ],
            '_vendor-abc.js' => [
                'file' => 'assets/vendor-abc.js',
            ],
        ]);

        $result = $this->createExtension()->renderScripts('admin');

        $this->assertStringContainsString('<link rel="modulepreload" href="build/assets/vendor-abc.js">', $result);
        $this->assertStringContainsString('<script type="module" src="build/assets/admin-abc123.js"></script>', $result);
    }

    public function testRenderLinksWithManifest(): void
    {
        $this->writeManifest([
            'assets/admin.js' => [
                'file' => 'assets/admin-abc123.js',
                'name' => 'admin',
                'css' => ['assets/admin-xyz789.css'],
            ],
        ]);

        $result = $this->createExtension()->renderLinks('admin');

        $this->assertStringContainsString('<link rel="stylesheet" href="build/assets/admin-xyz789.css">', $result);
    }

    public function testRenderScriptsWithoutManifest(): void
    {
        $this->writeManifest([
            'assets/admin.js' => [
                'file' => 'assets/admin-abc123.js',
                'name' => 'admin',
            ],
        ]);

        $result = $this->createExtension()->renderScripts('unknown-entry');

        $this->assertSame('', $result);
    }

    public function testRenderScriptsFallbackForAdminEntry(): void
    {
        // No manifest file at all
        $result = $this->createExtension()->renderScripts('admin');

        $this->assertSame('<script type="module" src="admin.js"></script>', $result);
    }

    public function testRenderLinksWithoutManifest(): void
    {
        $this->writeManifest([
            'assets/admin.js' => [
                'file' => 'assets/admin-abc123.js',
                'name' => 'admin',
            ],
        ]);

        $result = $this->createExtension()->renderLinks('unknown-entry');

        $this->assertSame('', $result);
    }

    public function testGetFunctionsReturnsExpectedNames(): void
    {
        $names = array_map(fn (TwigFunction $f) => $f->getName(), $this->createExtension()->getFunctions());

        $this->assertSame(['vite_entry_script_tags', 'vite_entry_link_tags'], $names);
    }

    private function writeManifest(array $data): void
    {
        file_put_contents(
            $this->tempDir . '/public/build/manifest.json',
            json_encode($data),
        );
    }

    private function createExtension(): ViteExtension
    {
        return new ViteExtension($this->packages, $this->tempDir);
    }
}
