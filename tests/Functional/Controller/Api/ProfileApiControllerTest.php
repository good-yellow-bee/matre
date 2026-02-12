<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestEnvironment;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for ProfileApiController.
 */
class ProfileApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/profile';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Get Notifications
    // =====================

    public function testGetNotificationsRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/notifications');

        $this->assertResponseRedirects('/login');
    }

    public function testGetNotificationsReturnsData(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/notifications');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('notificationsEnabled', $data);
        $this->assertArrayHasKey('notificationTrigger', $data);
        $this->assertArrayHasKey('notifyByEmail', $data);
        $this->assertArrayHasKey('notificationEnvironments', $data);
    }

    // =====================
    // Update Notifications
    // =====================

    public function testUpdateNotificationsRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('PUT', self::BASE_URL . '/notifications');

        $this->assertResponseRedirects('/login');
    }

    // =====================
    // Get Environments
    // =====================

    public function testGetEnvironmentsRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/environments');

        $this->assertResponseRedirects('/login');
    }

    public function testGetEnvironmentsReturnsData(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/environments');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
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
}
