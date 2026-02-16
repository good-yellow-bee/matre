<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testShowReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL . '/99999');

        // Controller does manual find() + addFlash + redirect (not 404)
        $this->assertResponseRedirects('/admin/test-runs');
    }

    // =====================
    // Cancel Tests
    // =====================

    public function testCancelRequiresAuth(): void
    {
        $client = self::createClient();
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/cancel');

        $this->assertResponseRedirects('/login');
    }

    public function testCancelRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/cancel');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCancelWithInvalidCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/cancel', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/test-runs/' . $run->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Retry Tests
    // =====================

    public function testRetryRequiresAuth(): void
    {
        $client = self::createClient();
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/retry');

        $this->assertResponseRedirects('/login');
    }

    public function testRetryRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/retry');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRetryWithInvalidCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $client->request('POST', self::BASE_URL . '/' . $run->getId() . '/retry', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/test-runs/' . $run->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Live Output Tests
    // =====================

    public function testLiveOutputRequiresAuth(): void
    {
        $client = self::createClient();
        $run = $this->createTestRun();

        $client->request('GET', self::BASE_URL . '/' . $run->getId() . '/live-output');

        $this->assertResponseRedirects('/login');
    }

    public function testLiveOutputReturnsJson(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $client->request('GET', self::BASE_URL . '/' . $run->getId() . '/live-output');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals(TestRun::STATUS_RUNNING, $data['status']);
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
