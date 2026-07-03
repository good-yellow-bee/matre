<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for test run SPA shell pages (mutations and live output covered by TestRunApiControllerTest).
 */
class TestRunControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/admin/test-runs';

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
    // Show Tests
    // =====================

    public function testShowReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun();

        $client->request('GET', self::BASE_URL . '/' . $run->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testShowServesShellForNonExistentId(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // SPA shell is served for any id; the 404 state is handled client-side
        // (API behavior covered by TestRunApiControllerTest::testShowReturns404ForNonExistent)
        $client->request('GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('#app');
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

    private function createTestRun(
        ?TestEnvironment $env = null,
        string $status = TestRun::STATUS_PENDING,
        string $type = TestRun::TYPE_MFTF,
    ): TestRun {
        $em = $this->getEntityManager();

        $run = new TestRun();
        $run->setEnvironment($env ?? $this->createTestEnvironment());
        $run->setType($type);
        $run->setStatus($status);

        $em->persist($run);
        $em->flush();

        return $run;
    }
}
