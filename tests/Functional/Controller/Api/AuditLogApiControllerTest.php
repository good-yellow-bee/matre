<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\AuditLog;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AuditLogApiController.
 */
class AuditLogApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/audit-logs';

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

        $client->request('GET', self::BASE_URL . '/list');

        $this->assertResponseRedirects('/login');
    }

    public function testListRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL . '/list');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Audit Logs
    // =====================

    public function testListReturnsData(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $this->createAuditLog();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('perPage', $data);
        $this->assertIsArray($data['data']);
    }

    public function testListSupportsPagination(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $this->createAuditLog();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list?page=1&perPage=5');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(1, $data['page']);
        $this->assertEquals(5, $data['perPage']);
        $this->assertLessThanOrEqual(5, count($data['data']));
    }

    public function testListSupportsFiltering(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $this->createAuditLog();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list?entityType=TestEnvironment&action=create');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        foreach ($data['data'] as $log) {
            $this->assertEquals('TestEnvironment', $log['entityType']);
            $this->assertEquals('create', $log['action']);
        }
    }

    // =====================
    // Filters Endpoint
    // =====================

    public function testFiltersReturnsAvailableFilters(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/filters');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('entityTypes', $data);
        $this->assertArrayHasKey('actions', $data);
        $this->assertArrayHasKey('users', $data);
        $this->assertIsArray($data['actions']);
    }

    // =====================
    // Get Single Log
    // =====================

    public function testGetReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

        $this->assertJsonError($response, 404, 'not found');
    }

    // =====================
    // Helpers
    // =====================

    private function createAuditLog(): AuditLog
    {
        $em = $this->getEntityManager();
        $log = new AuditLog();
        $log->setEntityType('TestEnvironment');
        $log->setEntityId(1);
        $log->setEntityLabel('Test Entity');
        $log->setAction(AuditLog::ACTION_CREATE);
        $log->setNewData(['name' => 'test']);
        $em->persist($log);
        $em->flush();

        return $log;
    }
}
