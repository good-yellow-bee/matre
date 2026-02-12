<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\SensitiveDataExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SensitiveDataExtensionTest extends TestCase
{
    private SensitiveDataExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SensitiveDataExtension();
    }

    public function testMaskSensitiveReturnsEmptyForNull(): void
    {
        $this->assertSame('', $this->extension->maskSensitive(null));
    }

    public function testMaskSensitiveReturnsEmptyForEmpty(): void
    {
        $this->assertSame('', $this->extension->maskSensitive(''));
    }

    public function testMaskSensitiveReturnsAsterisks(): void
    {
        $this->assertSame('***', $this->extension->maskSensitive('abc'));
    }

    public function testMaskSensitiveCapsAt16(): void
    {
        $this->assertSame(str_repeat('*', 16), $this->extension->maskSensitive(str_repeat('x', 30)));
    }

    public function testMaskIfSensitiveMasksSensitiveName(): void
    {
        $result = $this->extension->maskIfSensitive('secret123', 'api_key');

        $this->assertSame(str_repeat('*', 9), $result);
    }

    public function testMaskIfSensitiveReturnsValueForNonSensitive(): void
    {
        $this->assertSame('hello', $this->extension->maskIfSensitive('hello', 'username'));
    }

    public function testIsSensitiveNameDetectsPatterns(): void
    {
        $sensitiveNames = [
            'password',
            'SECRET_KEY',
            'api_token',
            'my_auth_token',
            'PRIVATE_KEY',
            'credential_store',
        ];

        foreach ($sensitiveNames as $name) {
            $this->assertTrue($this->extension->isSensitiveName($name), "Expected '$name' to be sensitive");
        }
    }

    public function testIsSensitiveNameReturnsFalseForSafe(): void
    {
        $safeNames = [
            'username',
            'email',
            'display_name',
            'url',
        ];

        foreach ($safeNames as $name) {
            $this->assertFalse($this->extension->isSensitiveName($name), "Expected '$name' to be safe");
        }
    }

    public function testGetFiltersReturnsExpectedNames(): void
    {
        $names = array_map(fn (TwigFilter $f) => $f->getName(), $this->extension->getFilters());

        $this->assertSame(['mask_sensitive', 'mask_if_sensitive'], $names);
    }

    public function testGetFunctionsReturnsExpectedNames(): void
    {
        $names = array_map(fn (TwigFunction $f) => $f->getName(), $this->extension->getFunctions());

        $this->assertSame(['is_sensitive_name'], $names);
    }
}
