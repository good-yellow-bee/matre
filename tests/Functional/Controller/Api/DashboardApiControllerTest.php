<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for DashboardApiController.
 */
class DashboardApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/dashboard';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testStatsRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/stats');

        $this->assertResponseRedirects('/login');
    }

    public function testStatsAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/stats');

        $this->assertJsonResponse($response, 200);
    }

    public function testStatsReturnsExpectedStructure(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/stats');
        $data = $this->assertJsonResponse($response, 200);

        // Check user stats
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('total', $data['users']);
        $this->assertArrayHasKey('active', $data['users']);

        // Check test run stats
        $this->assertArrayHasKey('testRuns', $data);

        // Check environment stats
        $this->assertArrayHasKey('environments', $data);

        // Check suite stats
        $this->assertArrayHasKey('suites', $data);
    }

    public function testStatsReturnsActivityInfo(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/stats');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('activity', $data);
        $this->assertArrayHasKey('runningNow', $data['activity']);
    }
}
