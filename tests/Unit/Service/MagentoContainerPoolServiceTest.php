<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Service\MagentoContainerPoolService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

class MagentoContainerPoolServiceTest extends TestCase
{
    public function testGetContainerForEnvironmentReturnsMainContainerWhenPoolDisabled(): void
    {
        $service = $this->createService(useContainerPool: false);

        $this->assertSame('magento-main', $service->getContainerForEnvironment($this->createEnvironment(5)));
    }

    public function testGetContainerNameForEnvironmentReturnsMainContainerWhenPoolDisabled(): void
    {
        $service = $this->createService(useContainerPool: false);

        $this->assertSame('magento-main', $service->getContainerNameForEnvironment($this->createEnvironment(5)));
    }

    public function testGetContainerNameForEnvironmentReturnsPrefixedNameWhenPoolEnabled(): void
    {
        $service = $this->createService(useContainerPool: true);

        $this->assertSame('matre_magento_env_5', $service->getContainerNameForEnvironment($this->createEnvironment(5)));
    }

    public function testGetContainerNameForEnvironmentUsesEnvironmentId(): void
    {
        $service = $this->createService(useContainerPool: true);

        $this->assertSame('matre_magento_env_42', $service->getContainerNameForEnvironment($this->createEnvironment(42)));
        $this->assertSame('matre_magento_env_1', $service->getContainerNameForEnvironment($this->createEnvironment(1)));
    }

    public function testExpectedHealthCheckFilesIncludeMagentoCoreAndModuleEnv(): void
    {
        $service = $this->createService(useContainerPool: true);
        $env = $this->createEnvironment(5, 'preprod-es');

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getExpectedHealthCheckFiles');
        $files = $method->invoke($service, $env);

        $this->assertSame([
            '/var/www/html/vendor/autoload.php',
            '/var/www/html/dev/tests/acceptance/codeception.yml',
            '/var/www/html/app/code/TestModule/Cron/data/.env.preprod-es',
        ], $files);
    }

    private function createEnvironment(int $id, string $code = 'stage-us'): TestEnvironment
    {
        $env = new TestEnvironment();
        $env->setCode($code);
        $ref = new \ReflectionClass($env);
        $idProp = $ref->getProperty('id');
        $idProp->setValue($env, $id);

        return $env;
    }

    private function createService(
        bool $useContainerPool = true,
        string $mainContainer = 'magento-main',
    ): MagentoContainerPoolService {
        return new MagentoContainerPoolService(
            $this->createStub(LoggerInterface::class),
            $this->createStub(LockFactory::class),
            projectDir: '/app',
            hostProjectDir: '/host/app',
            magentoImage: 'magento:latest',
            networkName: 'matre_default',
            codeVolume: 'matre_code',
            mainContainer: $mainContainer,
            useContainerPool: $useContainerPool,
        );
    }
}
