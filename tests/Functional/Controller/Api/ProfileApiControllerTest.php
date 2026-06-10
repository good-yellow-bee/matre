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

        $this->assertApiUnauthenticated($client);
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

        $this->assertApiUnauthenticated($client);
    }

    public function testUpdateNotificationsRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/notifications', [
            'notificationsEnabled' => true,
        ], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testUpdateNotificationsSucceeds(): void
    {
        $client = self::createClient();
        $user = $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/notifications', [
            'notificationsEnabled' => true,
            'notificationTrigger' => 'all',
            'notifyByEmail' => true,
            'notificationEnvironments' => [$env->getId()],
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        $this->getEntityManager()->refresh($user);
        $this->assertTrue($user->isNotificationsEnabled());
        $this->assertEquals('all', $user->getNotificationTrigger());
        $this->assertTrue($user->isNotifyByEmail());
    }

    // =====================
    // Get Environments
    // =====================

    public function testGetEnvironmentsRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/environments');

        $this->assertApiUnauthenticated($client);
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
