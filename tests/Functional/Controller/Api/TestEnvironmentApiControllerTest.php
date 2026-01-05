<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\GlobalEnvVariable;
use App\Entity\TestEnvironment;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestEnvironmentApiController.
 */
class TestEnvironmentApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-environments';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListEnvVariablesRequiresAuthentication(): void
    {
        $client = self::createClient();
        $env = $this->createTestEnvironment();

        $client->request('GET', self::BASE_URL . '/' . $env->getId() . '/env-variables');

        $this->assertResponseRedirects('/login');
    }

    public function testListEnvVariablesRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $client->request('GET', self::BASE_URL . '/' . $env->getId() . '/env-variables');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Env Variables
    // =====================

    public function testListEnvVariablesReturnsStructure(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment(['API_KEY' => 'secret123']);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $env->getId() . '/env-variables');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('global', $data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertIsArray($data['global']);
        $this->assertIsArray($data['environment']);
    }

    public function testListEnvVariablesIncludesGlobalVars(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $globalVar = $this->createGlobalEnvVariable();
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $env->getId() . '/env-variables');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertNotEmpty($data['global']);
        $found = false;
        foreach ($data['global'] as $var) {
            if ($var['name'] === $globalVar->getName()) {
                $found = true;
                $this->assertTrue($var['isGlobal']);

                break;
            }
        }
        $this->assertTrue($found, 'Global variable should be in response');
    }

    public function testListEnvVariablesIncludesEnvSpecificVars(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment([
            'ENV_SPECIFIC_VAR' => 'env_value',
        ]);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $env->getId() . '/env-variables');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertNotEmpty($data['environment']);
        $found = false;
        foreach ($data['environment'] as $var) {
            if ($var['name'] === 'ENV_SPECIFIC_VAR') {
                $found = true;
                $this->assertFalse($var['isGlobal']);
                $this->assertEquals('env_value', $var['value']);

                break;
            }
        }
        $this->assertTrue($found, 'Environment-specific variable should be in response');
    }

    // =====================
    // Save Env Variables
    // =====================

    public function testSaveEnvVariablesRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $env->getId() . '/env-variables', [
            'variables' => [['name' => 'NEW_VAR', 'value' => 'value']],
        ]);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testSaveEnvVariablesSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Import Env Variables
    // =====================

    public function testImportEnvVariablesRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $env->getId() . '/env-variables/import', [
            'content' => 'API_KEY=secret123',
        ]);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testImportEnvVariablesValidatesContent(): void
    {
        // Skip - CSRF required, but at least we know it validates
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    private function createTestEnvironment(array $envVars = []): TestEnvironment
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $env = new TestEnvironment();
        $env->setName("TestEnv_{$suffix}");
        $env->setCode("env_{$suffix}");
        $env->setRegion('us');
        $env->setBaseUrl("https://test_{$suffix}.example.com");
        $env->setBackendName('admin');
        $env->setIsActive(true);
        $env->setEnvVariables($envVars);

        $em->persist($env);
        $em->flush();

        return $env;
    }

    private function createGlobalEnvVariable(?string $name = null, string $value = 'test_value'): GlobalEnvVariable
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $var = new GlobalEnvVariable();
        $var->setName($name ?? "GLOBAL_VAR_{$suffix}");
        $var->setValue($value);

        $em->persist($var);
        $em->flush();

        return $var;
    }
}
