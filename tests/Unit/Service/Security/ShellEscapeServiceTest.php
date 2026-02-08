<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\ShellEscapeService;
use PHPUnit\Framework\TestCase;

class ShellEscapeServiceTest extends TestCase
{
    private ShellEscapeService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new ShellEscapeService();
        $this->tempDir = sys_get_temp_dir() . '/shell_escape_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }

    // --- validateEnvVarName ---

    public function testValidateEnvVarNameAcceptsValidNames(): void
    {
        $this->service->validateEnvVarName('MY_VAR');
        $this->service->validateEnvVarName('_var');
        $this->service->validateEnvVarName('A');

        $this->addToAssertionCount(3);
    }

    public function testValidateEnvVarNameRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->service->validateEnvVarName('');
    }

    public function testValidateEnvVarNameRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum length');

        $this->service->validateEnvVarName(str_repeat('A', 129));
    }

    public function testValidateEnvVarNameRejectsStartingWithDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid environment variable name');

        $this->service->validateEnvVarName('1VAR');
    }

    public function testValidateEnvVarNameRejectsSpecialChars(): void
    {
        $invalidNames = ['VAR-NAME', 'VAR.NAME', 'VAR NAME', 'VAR@NAME', 'VAR$NAME'];

        foreach ($invalidNames as $name) {
            try {
                $this->service->validateEnvVarName($name);
                $this->fail("Expected exception for invalid name: {$name}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid environment variable name', $e->getMessage());
            }
        }
    }

    // --- validateEnvVarValue ---

    public function testValidateEnvVarValueAcceptsValid(): void
    {
        $this->service->validateEnvVarValue('hello world');
        $this->service->validateEnvVarValue('');
        $this->service->validateEnvVarValue(str_repeat('x', 32768));

        $this->addToAssertionCount(3);
    }

    public function testValidateEnvVarValueRejectsNullBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes');

        $this->service->validateEnvVarValue("hello\0world");
    }

    public function testValidateEnvVarValueRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum length');

        $this->service->validateEnvVarValue(str_repeat('x', 32769));
    }

    // --- buildExportStatement ---

    public function testBuildExportStatementSimpleValue(): void
    {
        $result = $this->service->buildExportStatement('MY_VAR', 'hello');

        $this->assertSame("export MY_VAR='hello'", $result);
    }

    public function testBuildExportStatementSpecialChars(): void
    {
        $result = $this->service->buildExportStatement('VAR', "it's a \"test\"");

        $this->assertSame('export VAR=' . escapeshellarg("it's a \"test\""), $result);
    }

    public function testBuildExportStatementRejectsInvalidName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->buildExportStatement('1BAD', 'value');
    }

    // --- buildExportStatements ---

    public function testBuildExportStatementsMultiple(): void
    {
        $result = $this->service->buildExportStatements([
            'FOO' => 'bar',
            'BAZ' => 'qux',
        ]);

        $this->assertSame([
            "export FOO='bar'",
            "export BAZ='qux'",
        ], $result);
    }

    public function testBuildExportStatementsEmpty(): void
    {
        $this->assertSame([], $this->service->buildExportStatements([]));
    }

    // --- buildEnvFileLine ---

    public function testBuildEnvFileLineSimpleValue(): void
    {
        $this->assertSame('DB_HOST=localhost', $this->service->buildEnvFileLine('DB_HOST', 'localhost'));
    }

    public function testBuildEnvFileLineSpecialChars(): void
    {
        $this->assertSame("MSG='hello world!'", $this->service->buildEnvFileLine('MSG', 'hello world!'));
    }

    // --- quoteEnvFileValue ---

    public function testQuoteEnvFileValueEmpty(): void
    {
        $this->assertSame("''", $this->service->quoteEnvFileValue(''));
    }

    public function testQuoteEnvFileValueSafeCharsAsIs(): void
    {
        $this->assertSame('simple_value-1.0/path:8080', $this->service->quoteEnvFileValue('simple_value-1.0/path:8080'));
    }

    public function testQuoteEnvFileValueSpecialCharsQuoted(): void
    {
        $this->assertSame("'hello world'", $this->service->quoteEnvFileValue('hello world'));
    }

    public function testQuoteEnvFileValueEmbeddedSingleQuotesEscaped(): void
    {
        $this->assertSame("'it'\\''s here'", $this->service->quoteEnvFileValue("it's here"));
    }

    // --- sanitizeFilename ---

    public function testSanitizeFilenameNormal(): void
    {
        $this->assertSame('report.txt', $this->service->sanitizeFilename('report.txt'));
    }

    public function testSanitizeFilenameStripsDirectory(): void
    {
        $this->assertSame('file.txt', $this->service->sanitizeFilename('/etc/passwd/../file.txt'));
    }

    public function testSanitizeFilenameRejectsDotDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filename');

        $this->service->sanitizeFilename('..');
    }

    public function testSanitizeFilenameRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filename');

        $this->service->sanitizeFilename('');
    }

    public function testSanitizeFilenameRemovesNullBytes(): void
    {
        $this->assertSame('safe.txt', $this->service->sanitizeFilename("sa\0fe.txt"));
    }

    // --- validatePathWithinBase ---

    public function testValidatePathWithinBaseValidPath(): void
    {
        $subDir = $this->tempDir . '/sub';
        mkdir($subDir);
        file_put_contents($subDir . '/file.txt', 'test');

        $result = $this->service->validatePathWithinBase($subDir . '/file.txt', $this->tempDir);

        $this->assertSame(realpath($subDir . '/file.txt'), $result);
    }

    public function testValidatePathWithinBaseRejectsTraversal(): void
    {
        // Create a subdir so the traversal resolves to a real parent outside base
        $subDir = $this->tempDir . '/sub';
        mkdir($subDir);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->validatePathWithinBase($subDir . '/../../etc/passwd', $subDir);
    }

    public function testValidatePathWithinBaseRejectsNonexistentBaseDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base directory does not exist');

        $this->service->validatePathWithinBase('/some/file', '/nonexistent/base');
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
