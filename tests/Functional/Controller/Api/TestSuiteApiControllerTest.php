<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestSuiteApiController.
 */
class TestSuiteApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-suites';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL);

        $this->assertResponseRedirects('/login');
    }

    public function testListAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);

        $this->assertJsonResponse($response, 200);
    }

    public function testListReturnsSuites(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $suite = $this->createTestSuite();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $found = false;
        foreach ($data as $item) {
            if ($item['id'] === $suite->getId()) {
                $found = true;
                $this->assertEquals($suite->getName(), $item['name']);
                $this->assertEquals('mftf_group', $item['type']);
                $this->assertArrayHasKey('typeLabel', $item);

                break;
            }
        }
        $this->assertTrue($found, 'Created suite should be in list');
    }

    // =====================
    // Types Endpoint
    // =====================

    public function testTypesReturnsAvailableTypes(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/types');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('value', $data[0]);
        $this->assertArrayHasKey('label', $data[0]);
    }

    // =====================
    // Get Single Suite
    // =====================

    public function testGetReturnsSuiteDetails(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $suite = $this->createTestSuite();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $suite->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($suite->getId(), $data['id']);
        $this->assertEquals($suite->getName(), $data['name']);
        $this->assertEquals($suite->getType(), $data['type']);
        $this->assertEquals($suite->getTestPattern(), $data['testPattern']);
        $this->assertArrayHasKey('environments', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetReturns404(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

        $this->assertJsonError($response, 404, 'not found');
    }

    // =====================
    // Create Suite
    // =====================

    public function testCreateRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'name' => 'Test Suite',
            'type' => TestSuite::TYPE_MFTF_GROUP,
            'testPattern' => 'TestGroup',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreateSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'name' => "NewSuite_{$suffix}",
            'type' => TestSuite::TYPE_MFTF_GROUP,
            'testPattern' => 'TestGroup',
            'environments' => [$env->getId()],
        ]);

        $data = $this->assertJsonResponse($response, 201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);
    }

    // =====================
    // Update Suite
    // =====================

    public function testUpdateRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $suite = $this->createTestSuite();

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $suite->getId(), [
            'name' => 'Updated',
            'type' => TestSuite::TYPE_MFTF_GROUP,
            'testPattern' => 'Updated',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // =====================
    // Validate Name
    // =====================

    public function testValidateNameReturnsValid(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-name', [
            'name' => "UniqueName_{$suffix}",
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['valid']);
    }

    public function testValidateNameReturnsTaken(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $suite = $this->createTestSuite();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-name', [
            'name' => $suite->getName(),
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }

    // =====================
    // Validate Cron
    // =====================

    public function testValidateCronReturnsValid(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-cron', [
            'cronExpression' => '*/5 * * * *',
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['valid']);
    }

    public function testValidateCronReturnsInvalid(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-cron', [
            'cronExpression' => 'not a cron',
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }

    // =====================
    // Helpers
    // =====================

    private function createTestEnvironment(): TestEnvironment
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
        $em->persist($env);
        $em->flush();

        return $env;
    }

    private function createTestSuite(?TestEnvironment $env = null): TestSuite
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));
        $env ??= $this->createTestEnvironment();
        $suite = new TestSuite();
        $suite->setName("Suite_{$suffix}");
        $suite->setType(TestSuite::TYPE_MFTF_GROUP);
        $suite->setTestPattern("TestGroup_{$suffix}");
        $suite->setIsActive(true);
        $suite->addEnvironment($env);
        $em->persist($suite);
        $em->flush();

        return $suite;
    }
}
