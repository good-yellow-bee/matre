<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EnvVariableAnalyzerService;
use PHPUnit\Framework\TestCase;

class EnvVariableAnalyzerServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/env_analyzer_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // --- parseEnvFile ---

    public function testParseEnvFileReturnsKeyValuePairs(): void
    {
        $path = $this->tempDir . '/.env.test';
        file_put_contents($path, <<<'ENV'
            ADMIN_URL=https://admin.example.com
            DB_HOST=localhost
            DB_PORT=3306
            ENV);

        $result = $this->createService()->parseEnvFile($path);

        $this->assertSame([
            'ADMIN_URL' => 'https://admin.example.com',
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
        ], $result);
    }

    public function testParseEnvFileSkipsComments(): void
    {
        $path = $this->tempDir . '/.env.test';
        file_put_contents($path, <<<'ENV'
            # This is a comment
            KEY=value
            # Another comment
            ENV);

        $result = $this->createService()->parseEnvFile($path);

        $this->assertSame(['KEY' => 'value'], $result);
    }

    public function testParseEnvFileSkipsBlankLines(): void
    {
        $path = $this->tempDir . '/.env.test';
        file_put_contents($path, "FOO=bar\n\n\nBAZ=qux\n");

        $result = $this->createService()->parseEnvFile($path);

        $this->assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testParseEnvFileStripsQuotesFromValues(): void
    {
        $path = $this->tempDir . '/.env.test';
        file_put_contents($path, <<<'ENV'
            DOUBLE="double quoted"
            SINGLE='single quoted'
            NONE=no quotes
            ENV);

        $result = $this->createService()->parseEnvFile($path);

        $this->assertSame([
            'DOUBLE' => 'double quoted',
            'SINGLE' => 'single quoted',
            'NONE' => 'no quotes',
        ], $result);
    }

    public function testParseEnvFileThrowsForNonexistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        $this->createService()->parseEnvFile($this->tempDir . '/nonexistent.env');
    }

    // --- analyzeTestUsage ---

    public function testAnalyzeTestUsageReturnsEmptyWhenNoTestDirectory(): void
    {
        $result = $this->createService()->analyzeTestUsage($this->tempDir);

        $this->assertSame([], $result);
    }

    public function testAnalyzeTestUsageFindsDirectEnvVarUsage(): void
    {
        $this->createMftfStructure(
            tests: [
                'MOEC2609Test.xml' => <<<'XML'
                    <tests xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <test name="MOEC2609">
                            <fillField selector="#field" userInput="{{_ENV.ADMIN_URL}}" stepKey="s1"/>
                            <fillField selector="#other" userInput="{{_ENV.DB_HOST}}" stepKey="s2"/>
                        </test>
                    </tests>
                    XML,
            ],
        );

        $result = $this->createService()->analyzeTestUsage($this->tempDir);

        $this->assertArrayHasKey('ADMIN_URL', $result);
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertContains('MOEC2609', $result['ADMIN_URL']);
        $this->assertContains('MOEC2609', $result['DB_HOST']);
    }

    public function testAnalyzeTestUsageResolvesActionGroupTransitiveDependencies(): void
    {
        $this->createMftfStructure(
            tests: [
                'MOEC2609Test.xml' => <<<'XML'
                    <tests xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <test name="MOEC2609">
                            <fillField selector="#field" userInput="{{_ENV.ADMIN_URL}}" stepKey="s1"/>
                            <actionGroup ref="LoginAsAdmin" stepKey="s2"/>
                        </test>
                    </tests>
                    XML,
            ],
            actionGroups: [
                'LoginAsAdminActionGroup.xml' => <<<'XML'
                    <actionGroups xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <actionGroup name="LoginAsAdmin">
                            <fillField selector="#user" userInput="{{_ENV.ADMIN_USER}}" stepKey="s1"/>
                            <fillField selector="#pass" userInput="{{_ENV.ADMIN_PASS}}" stepKey="s2"/>
                        </actionGroup>
                    </actionGroups>
                    XML,
            ],
        );

        $result = $this->createService()->analyzeTestUsage($this->tempDir);

        $this->assertContains('MOEC2609', $result['ADMIN_URL']);
        $this->assertContains('MOEC2609', $result['ADMIN_USER']);
        $this->assertContains('MOEC2609', $result['ADMIN_PASS']);
    }

    public function testAnalyzeTestUsageDeduplicatesTestIds(): void
    {
        $this->createMftfStructure(
            tests: [
                'MOEC2609Test.xml' => <<<'XML'
                    <tests xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <test name="MOEC2609">
                            <fillField selector="#a" userInput="{{_ENV.SHARED_VAR}}" stepKey="s1"/>
                            <actionGroup ref="GroupWithSharedVar" stepKey="s2"/>
                        </test>
                    </tests>
                    XML,
            ],
            actionGroups: [
                'GroupWithSharedVarActionGroup.xml' => <<<'XML'
                    <actionGroups xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <actionGroup name="GroupWithSharedVar">
                            <fillField selector="#b" userInput="{{_ENV.SHARED_VAR}}" stepKey="s1"/>
                        </actionGroup>
                    </actionGroups>
                    XML,
            ],
        );

        $result = $this->createService()->analyzeTestUsage($this->tempDir);

        $this->assertCount(1, $result['SHARED_VAR']);
        $this->assertSame(['MOEC2609'], $result['SHARED_VAR']);
    }

    // --- extractTestId ---

    public function testExtractTestIdFromAlphanumericPattern(): void
    {
        $this->assertSame('MOEC1625', $this->createService()->extractTestId('MOEC1625Test.xml'));
    }

    public function testExtractTestIdStripsTestSuffix(): void
    {
        $this->assertSame('AdminCheckout', $this->createService()->extractTestId('AdminCheckoutTest.xml'));
    }

    public function testExtractTestIdReturnsNullForEmptyString(): void
    {
        $this->assertNull($this->createService()->extractTestId(''));
    }

    // --- getDefaultModulePath ---

    public function testGetDefaultModulePathReturnsCorrectPath(): void
    {
        $service = $this->createService(projectDir: '/app');

        $this->assertSame('/app/var/test-modules/current', $service->getDefaultModulePath());
    }

    // --- helpers ---

    private function createService(string $projectDir = '/app'): EnvVariableAnalyzerService
    {
        return new EnvVariableAnalyzerService($projectDir);
    }

    /**
     * @param array<string, string> $tests        filename => XML content
     * @param array<string, string> $actionGroups filename => XML content
     */
    private function createMftfStructure(array $tests = [], array $actionGroups = []): void
    {
        if ($tests) {
            $testDir = $this->tempDir . '/Test/Mftf/Test';
            mkdir($testDir, 0o777, true);
            foreach ($tests as $filename => $content) {
                file_put_contents($testDir . '/' . $filename, $content);
            }
        }

        if ($actionGroups) {
            $agDir = $this->tempDir . '/Test/Mftf/ActionGroup';
            mkdir($agDir, 0o777, true);
            foreach ($actionGroups as $filename => $content) {
                file_put_contents($agDir . '/' . $filename, $content);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
