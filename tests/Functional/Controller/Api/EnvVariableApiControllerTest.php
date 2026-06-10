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

    /** @var int[] IDs of variables created during tests (for cleanup) */
    private array $createdVarIds = [];

    protected function tearDown(): void
    {
        if (!empty($this->createdVarIds) && null !== $this->entityManager) {
            $this->entityManager->createQuery(
                'DELETE FROM App\Entity\GlobalEnvVariable v WHERE v.id IN (:ids)',
            )->execute(['ids' => $this->createdVarIds]);
        }

        $this->createdVarIds = [];
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/list');

        $this->assertApiUnauthenticated($client);
    }

    public function testListRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL . '/list');

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

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list');
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

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . "/list?search=SEARCHABLE_{$suffix}");
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

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $var->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($var->getName(), $data['name']);
        $this->assertEquals($var->getValue(), $data['value']);
    }

    public function testGetReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

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
        ], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testCreateSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'name' => "new_var_{$suffix}",
            'value' => 'new_value',
        ]);

        $data = $this->assertJsonResponse($response, 201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);
        $this->createdVarIds[] = $data['id'];

        // The controller uppercases the whole name
        $var = $this->getEntityManager()->getRepository(GlobalEnvVariable::class)->find($data['id']);
        $this->assertNotNull($var);
        $this->assertEquals(strtoupper("new_var_{$suffix}"), $var->getName());
        $this->assertEquals('new_value', $var->getValue());
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // Blank name + value violates NotBlank on both fields
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'name' => '',
            'value' => '',
        ]);

        $data = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    // =====================
    // Update Tests
    // =====================

    public function testUpdateRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();
        $newValue = 'updated_value_' . bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $var->getId(), [
            'name' => $var->getName(),
            'value' => $newValue,
        ], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testUpdateSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();
        $newValue = 'updated_value_' . bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $var->getId(), [
            'name' => $var->getName(),
            'value' => $newValue,
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        $this->getEntityManager()->refresh($var);
        $this->assertEquals($newValue, $var->getValue());
    }

    // =====================
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/' . $var->getId(), [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testDeleteSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();
        $varId = $var->getId();

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/' . $varId);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        $this->assertNull($this->getEntityManager()->getRepository(GlobalEnvVariable::class)->find($varId));
    }

    // =====================
    // Bulk Save Tests (data-loss-fix contract)
    // =====================

    public function testBulkSaveWithIdPreservesScopeAndUsedInTests(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/bulk', [
            'variables' => [[
                'id' => $var->getId(),
                'name' => $var->getName(),
                'value' => 'bulk_updated',
                'environments' => ['preprod-es'],
                'usedInTests' => 'SomeCest',
                'description' => 'desc',
            ]],
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['updated']);
        $this->assertEquals(0, $data['created']);

        $this->getEntityManager()->refresh($var);
        $this->assertEquals('bulk_updated', $var->getValue());
        $this->assertEquals(['preprod-es'], $var->getEnvironments());
        $this->assertEquals('SomeCest', $var->getUsedInTests());
    }

    public function testBulkSaveWithNullIdMatchesByNameInsteadOfDuplicating(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $var = $this->createEnvVariable();
        $name = $var->getName();

        // id omitted: controller falls back to findByName, so an existing name updates rather than duplicates
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/bulk', [
            'variables' => [[
                'id' => null,
                'name' => $name,
                'value' => 'merged_value',
            ]],
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertEquals(1, $data['updated']);
        $this->assertEquals(0, $data['created']);

        // Exactly one row for this name, value overwritten
        $matches = $this->getEntityManager()->getRepository(GlobalEnvVariable::class)->findBy(['name' => $name]);
        $this->assertCount(1, $matches);
        $this->assertEquals('merged_value', $matches[0]->getValue());
    }

    // =====================
    // Import Tests (parse .env content)
    // =====================

    public function testImportParsesEnvContent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/import', [
            'content' => "FOO=bar\nBAZ=qux",
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['count']);
        $names = array_column($data['variables'], 'name');
        $this->assertContains('FOO', $names);
        $this->assertContains('BAZ', $names);
    }

    public function testImportRejectsEmptyContent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/import', [
            'content' => '',
        ]);

        $this->assertJsonError($response, 400);
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

        $this->createdVarIds[] = $var->getId();

        return $var;
    }
}
