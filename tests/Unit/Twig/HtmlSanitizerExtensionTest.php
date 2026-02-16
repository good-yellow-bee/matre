<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\HtmlSanitizerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\TwigFilter;

class HtmlSanitizerExtensionTest extends TestCase
{
    public function testSanitizeHtmlReturnsEmptyForNull(): void
    {
        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $extension = new HtmlSanitizerExtension($sanitizer);

        $this->assertSame('', $extension->sanitizeHtml(null));
    }

    public function testSanitizeHtmlReturnsEmptyForEmptyString(): void
    {
        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $extension = new HtmlSanitizerExtension($sanitizer);

        $this->assertSame('', $extension->sanitizeHtml(''));
    }

    public function testSanitizeHtmlDelegatesToSanitizer(): void
    {
        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturn('<p>clean</p>');

        $extension = new HtmlSanitizerExtension($sanitizer);

        $this->assertSame('<p>clean</p>', $extension->sanitizeHtml('<p>clean</p><script>alert(1)</script>'));
    }

    public function testGetFiltersContainsSanitizeHtml(): void
    {
        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $extension = new HtmlSanitizerExtension($sanitizer);

        $names = array_map(fn (TwigFilter $f) => $f->getName(), $extension->getFilters());

        $this->assertContains('sanitize_html', $names);
    }
}
