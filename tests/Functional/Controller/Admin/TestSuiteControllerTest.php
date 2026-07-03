<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for test suite SPA shell pages (mutations covered by TestSuiteApiControllerTest).
 */
class TestSuiteControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/admin/test-suites';

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
        $this->assertSelectorExists('#app');
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
        $suite = $this->createTestSuite();

        $client->request('GET', self::BASE_URL . '/' . $suite->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testShowServesShellForNonExistentId(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // SPA shell is served for any id; the 404 state is handled client-side
        // (API behavior covered by TestSuiteApiControllerTest::testGetReturns404)
        $client->request('GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('#app');
    }

    // =====================
    // Edit Tests
    // =====================

    public function testEditFormReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suite = $this->createTestSuite();

        $client->request('GET', self::BASE_URL . '/' . $suite->getId() . '/edit');

        $this->assertResponseIsSuccessful();
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

    private function createTestSuite(): TestSuite
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));
        $env = $this->createTestEnvironment();

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
