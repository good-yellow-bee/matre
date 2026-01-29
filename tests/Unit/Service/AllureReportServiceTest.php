<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AllureReportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AllureReportServiceTest extends TestCase
{
    public function testExtractAllureTestIdUsesSuffix(): void
    {
        $service = $this->createService();

        $testId = $this->callPrivate($service, 'extractAllureTestId', [[
            'name' => 'MOEC7212US: Some test',
        ]]);

        $this->assertSame('MOEC7212US', $testId);
    }

    public function testExtractAllureTestIdDistinguishesSuffix(): void
    {
        $service = $this->createService();

        $baseId = $this->callPrivate($service, 'extractAllureTestId', [[
            'name' => 'MOEC7212: Base test',
        ]]);

        $suffixId = $this->callPrivate($service, 'extractAllureTestId', [[
            'name' => 'MOEC7212US: US variant',
        ]]);

        $this->assertSame('MOEC7212', $baseId);
        $this->assertSame('MOEC7212US', $suffixId);
        $this->assertNotSame($baseId, $suffixId);
    }

    public function testExtractAllureTestIdFromFullName(): void
    {
        $service = $this->createService();

        $testId = $this->callPrivate($service, 'extractAllureTestId', [[
            'name' => 'Some other name',
            'fullName' => 'Magento\\AcceptanceTest\\_default\\Backend\\MOEC7212USCest::MOEC7212US',
        ]]);

        $this->assertSame('MOEC7212US', $testId);
    }

    public function testNormalizeTestIdPreservesSuffix(): void
    {
        $service = $this->createService();

        $normalized = $this->callPrivate($service, 'normalizeTestId', ['MOEC-7212US']);

        $this->assertSame('MOEC7212US', $normalized);
    }

    private function createService(): AllureReportService
    {
        $logger = $this->createStub(LoggerInterface::class);
        $httpClient = $this->createStub(HttpClientInterface::class);

        return new AllureReportService(
            $logger,
            $httpClient,
            '/app',
            'http://allure',
            'http://allure-public',
        );
    }

    private function callPrivate(AllureReportService $service, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod(AllureReportService::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }
}
