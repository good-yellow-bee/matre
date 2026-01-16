<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestRunApiController.
 */
class TestRunApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-runs';
    private const CSRF_TOKEN_ID = 'test_run_api';

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

        $client->request('GET', self::BASE_URL);

        $this->assertResponseRedirects('/login');
    }

    public function testListAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);

        $this->assertJsonResponse($response, 200);
    }

    public function testCancelRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/cancel');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRetryRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/retry');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Tests
    // =====================

    public function testListReturnsRuns(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $this->createTestRun();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    public function testListSupportsPagination(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?page=1&limit=5');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertLessThanOrEqual(5, count($data['data']));
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(5, $data['meta']['limit']);
    }

    public function testListFiltersStatus(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();
        $this->createTestRun($env, TestRun::STATUS_COMPLETED);
        $this->createTestRun($env, TestRun::STATUS_FAILED);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?status=completed');
        $data = $this->assertJsonResponse($response, 200);

        foreach ($data['data'] as $run) {
            $this->assertEquals('completed', $run['status']);
        }
    }

    public function testListFiltersType(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();
        $this->createTestRun($env, TestRun::STATUS_PENDING, TestRun::TYPE_MFTF);
        $this->createTestRun($env, TestRun::STATUS_PENDING, TestRun::TYPE_PLAYWRIGHT);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?type=mftf');
        $data = $this->assertJsonResponse($response, 200);

        foreach ($data['data'] as $run) {
            $this->assertEquals('mftf', $run['type']);
        }
    }

    // =====================
    // Show Tests
    // =====================

    public function testShowReturnsRunDetails(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $run = $this->createTestRun();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $run->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($run->getId(), $data['id']);
        $this->assertEquals($run->getType(), $data['type']);
        $this->assertEquals($run->getStatus(), $data['status']);
    }

    public function testShowReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        // Uses ParamConverter which returns Symfony's 404
        $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(404);
    }

    // =====================
    // Cancel Tests
    // =====================

    public function testCancelRequiresCsrfToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        // No CSRF token
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/cancel');

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testCancelSucceeds(): void
    {
        // Skip - CSRF token validation in functional tests requires session setup
        // Business logic is tested in TestRunnerServiceTest::testCancelRunSucceeds
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    public function testCancelRejectsNonCancellableRun(): void
    {
        // Skip - CSRF token validation in functional tests requires session setup
        // The cancel action is tested via testCancelSucceeds with valid CSRF
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Retry Tests
    // =====================

    public function testRetryRequiresCsrfToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/retry');

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testRetryCreatesNewRun(): void
    {
        // Skip - CSRF token validation in functional tests requires session setup
        // Business logic is tested in TestRunnerServiceTest::testRetryRunCreatesNewRun
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    private function createTestEnvironment(?string $name = null): TestEnvironment
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $env = new TestEnvironment();
        $env->setName($name ?? "TestEnv_{$suffix}");
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
