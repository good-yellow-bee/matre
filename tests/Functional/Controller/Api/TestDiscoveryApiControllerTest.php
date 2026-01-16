<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestSuite;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestDiscoveryApiController.
 */
class TestDiscoveryApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-discovery';

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

        $client->request('GET', self::BASE_URL . '?type=mftf_group');

        $this->assertResponseRedirects('/login');
    }

    public function testListAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=mftf_group');

        // Should return success even if cache not available
        $data = $this->assertJsonResponse($response, 200);
        $this->assertArrayHasKey('success', $data);
    }

    public function testRefreshRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('POST', self::BASE_URL . '/refresh');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Tests
    // =====================

    public function testListRequiresTypeParameter(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $data = $this->assertJsonResponse($response, 400);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Type', $data['error']);
    }

    public function testListRejectsInvalidType(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=invalid_type');
        $data = $this->assertJsonResponse($response, 400);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    public function testListAcceptsValidMftfGroupType(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=' . TestSuite::TYPE_MFTF_GROUP);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertTrue($data['success']);
        $this->assertEquals(TestSuite::TYPE_MFTF_GROUP, $data['type']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('cached', $data);
    }

    public function testListAcceptsValidMftfTestType(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=' . TestSuite::TYPE_MFTF_TEST);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertTrue($data['success']);
        $this->assertEquals(TestSuite::TYPE_MFTF_TEST, $data['type']);
    }

    public function testListPlaywrightReturnsNotImplemented(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=' . TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertTrue($data['success']);
        $this->assertEquals(TestSuite::TYPE_PLAYWRIGHT_GROUP, $data['type']);
        $this->assertEmpty($data['items']);
        $this->assertStringContainsString('not implemented', $data['message']);
    }

    // =====================
    // Status Tests
    // =====================

    public function testStatusReturnsAvailability(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/status');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('available', $data);
        $this->assertArrayHasKey('lastUpdated', $data);
    }

    // =====================
    // Refresh Tests
    // =====================

    public function testRefreshRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/refresh');

        $this->assertJsonError($response, 403);
    }

    public function testRefreshSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        // Also would require mocking TestDiscoveryService
        $this->markTestSkipped('CSRF + service mocking required');
    }
}
