<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\GlobalEnvVariable;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for EnvVariableApiController.
 */
class EnvVariableApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/env-variables';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL.'/list');

        $this->assertResponseRedirects('/login');
    }

    public function testListRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL.'/list');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Tests
    // =====================

    public function testListReturnsVariables(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $this->createEnvVariable();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL.'/list');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertIsArray($data['data']);
    }

    public function testListSupportsSearch(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));
        $this->createEnvVariable("SEARCHABLE_{$suffix}");

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL."/list?search=SEARCHABLE_{$suffix}");
        $data = $this->assertJsonResponse($response, 200);

        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    // =====================
    // Get Tests
    // =====================

    public function testGetReturnsVariable(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL.'/'.$var->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($var->getName(), $data['name']);
        $this->assertEquals($var->getValue(), $data['value']);
    }

    public function testGetReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $this->jsonRequest($client, 'GET', self::BASE_URL.'/99999');

        $this->assertResponseStatusCodeSame(404);
    }

    // =====================
    // Create Tests
    // =====================

    public function testCreateRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'name' => "NEW_VAR_{$suffix}",
            'value' => 'new_value',
        ]);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testCreateSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    public function testCreateValidatesRequiredFields(): void
    {
        // Skip - CSRF required before validation
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Update Tests
    // =====================

    public function testUpdateRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();
        $newValue = 'updated_value_'.bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL.'/'.$var->getId(), [
            'name' => $var->getName(),
            'value' => $newValue,
        ]);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testUpdateSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL.'/'.$var->getId());

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testDeleteSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    private function createEnvVariable(?string $name = null, string $value = 'test_value'): GlobalEnvVariable
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $var = new GlobalEnvVariable();
        $var->setName($name ?? "TEST_VAR_{$suffix}");
        $var->setValue($value);

        $em->persist($var);
        $em->flush();

        return $var;
    }
}
