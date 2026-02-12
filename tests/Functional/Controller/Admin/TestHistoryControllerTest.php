<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestHistoryController.
 */
class TestHistoryControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testIndexRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/test-history');

        $this->assertResponseRedirects('/login');
    }

    // =====================
    // Authorization Tests
    // =====================

    public function testIndexAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/test-history');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testIndexAllowsAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/test-history');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Query Parameter Tests
    // =====================

    public function testIndexWithQueryParams(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $client->request('GET', '/admin/test-history?testId=SomeTestClass&environmentId=' . $env->getId());

        $this->assertResponseStatusCodeSame(200);
    }

    public function testIndexWithEnvironmentId(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $client->request('GET', '/admin/test-history?environmentId=' . $env->getId());

        $this->assertResponseStatusCodeSame(200);
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
