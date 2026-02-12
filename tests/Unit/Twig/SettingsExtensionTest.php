<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use App\Twig\SettingsExtension;
use PHPUnit\Framework\TestCase;

class SettingsExtensionTest extends TestCase
{
    public function testGetGlobalsReturnsSiteSettings(): void
    {
        $settings = new Settings();
        $settings->setSiteName('Test Site');

        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('getSettings')->willReturn($settings);

        $extension = new SettingsExtension($repository);
        $globals = $extension->getGlobals();

        $this->assertArrayHasKey('site_settings', $globals);
        $this->assertSame($settings, $globals['site_settings']);
    }

    public function testGetGlobalsReturnsDefaultSettingsOnException(): void
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('getSettings')->willThrowException(new \RuntimeException('DB unavailable'));

        $extension = new SettingsExtension($repository);
        $globals = $extension->getGlobals();

        $this->assertArrayHasKey('site_settings', $globals);
        $this->assertInstanceOf(Settings::class, $globals['site_settings']);
    }
}
