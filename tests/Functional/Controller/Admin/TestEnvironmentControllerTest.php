<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestEnvironmentControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/admin/test-environments';

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

        $client->request('GET', self::BASE_URL);

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseIsSuccessful();
    }

    // =====================
    // New Tests
    // =====================

    public function testNewFormReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL . '/new');

        $this->assertResponseIsSuccessful();
    }

    // =====================
    // Show Tests
    // =====================

    public function testShowReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('GET', self::BASE_URL . '/' . $env->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testShowReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(404);
    }

    // =====================
    // Edit Tests
    // =====================

    public function testEditFormReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('GET', self::BASE_URL . '/' . $env->getId() . '/edit');

        $this->assertResponseIsSuccessful();
    }

    // =====================
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('POST', self::BASE_URL . '/' . $env->getId() . '/delete');

        $this->assertResponseRedirects('/admin/test-environments');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    public function testDeleteWithInvalidCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('POST', self::BASE_URL . '/' . $env->getId() . '/delete', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/test-environments');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Toggle Active Tests
    // =====================

    public function testToggleActiveRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('POST', self::BASE_URL . '/' . $env->getId() . '/toggle-active');

        $this->assertResponseRedirects('/admin/test-environments');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    public function testToggleActiveWithInvalidCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $client->request('POST', self::BASE_URL . '/' . $env->getId() . '/toggle-active', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/test-environments');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
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
