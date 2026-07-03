<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use App\Message\TestRunMessage;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestRunApiController.
 */
class TestRunApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-runs';

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

        $this->assertApiUnauthenticated($client);
    }

    public function testListAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);

        $this->assertJsonResponse($response, 200);
    }

    public function testCancelRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('POST', self::BASE_URL . '/1/cancel');

        $this->assertApiUnauthenticated($client);
    }

    public function testRetryRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('POST', self::BASE_URL . '/1/retry');

        $this->assertApiUnauthenticated($client);
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
    // Create Tests
    // =====================

    public function testCreateRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('POST', self::BASE_URL);

        $this->assertApiUnauthenticated($client);
    }

    public function testCreateRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => TestRun::TYPE_MFTF,
            'testFilter' => 'SomeTestCest',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateRequiresCsrfToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL);

        $data = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('environmentId', $data['errors']);
        $this->assertArrayHasKey('type', $data['errors']);
        // Either suiteId or testFilter must be provided
        $this->assertArrayHasKey('testFilter', $data['errors']);
    }

    public function testCreateRejectsInactiveEnvironment(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment(active: false);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => TestRun::TYPE_MFTF,
            'testFilter' => 'SomeTestCest',
        ]);

        $data = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('environmentId', $data['errors']);
    }

    public function testCreateRejectsInvalidType(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => 'invalid-type',
            'testFilter' => 'SomeTestCest',
        ]);

        $data = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('type', $data['errors']);
    }

    public function testCreateRejectsInactiveSuite(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuite(active: false);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => TestRun::TYPE_MFTF,
            'suiteId' => $suite->getId(),
        ]);

        $data = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('suiteId', $data['errors']);
    }

    public function testCreateSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();

        $connection = $this->getEntityManager()->getConnection();
        $maxMessageId = (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => TestRun::TYPE_MFTF,
            'testFilter' => 'SomeTestCest',
        ]);

        $data = $this->assertJsonResponse($response, 201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(TestRun::STATUS_PENDING, $data['run']['status']);

        // Run persisted with pending status and manual trigger
        $run = $this->getEntityManager()->getRepository(TestRun::class)->find($data['id']);
        $this->assertNotNull($run);
        $this->assertEquals(TestRun::STATUS_PENDING, $run->getStatus());
        $this->assertEquals(TestRun::TRIGGER_MANUAL, $run->getTriggeredBy());
        $this->assertEquals('SomeTestCest', $run->getTestFilter());

        // TestRunMessage dispatched to the doctrine transport table
        $rows = $connection->fetchAllAssociative(
            'SELECT body, queue_name FROM messenger_messages WHERE id > ?',
            [$maxMessageId],
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('test_runner_env_' . $env->getId(), $rows[0]['queue_name']);

        // PhpSerializer encodes the envelope with addslashes
        $envelope = unserialize(stripslashes($rows[0]['body']));
        $message = $envelope->getMessage();
        $this->assertInstanceOf(TestRunMessage::class, $message);
        $this->assertEquals($data['id'], $message->testRunId);
        $this->assertEquals($env->getId(), $message->environmentId);

        // Suite is not transactional - remove the queued message so no worker can pick it up
        $connection->executeStatement('DELETE FROM messenger_messages WHERE id > ?', [$maxMessageId]);
    }

    public function testCreateSucceedsWithSuite(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuite();

        $connection = $this->getEntityManager()->getConnection();
        $maxMessageId = (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        // Suite-based create: testFilter omitted, run inherits the suite's test pattern
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'environmentId' => $env->getId(),
            'type' => TestRun::TYPE_MFTF,
            'suiteId' => $suite->getId(),
        ]);

        $data = $this->assertJsonResponse($response, 201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);

        $run = $this->getEntityManager()->getRepository(TestRun::class)->find($data['id']);
        $this->assertNotNull($run);
        $this->assertEquals(TestRun::STATUS_PENDING, $run->getStatus());
        $this->assertNotNull($run->getSuite());
        $this->assertEquals($suite->getId(), $run->getSuite()->getId());
        $this->assertEquals($suite->getTestPattern(), $run->getTestFilter());

        // Remove the queued TestRunMessage so no worker can pick it up
        $connection->executeStatement('DELETE FROM messenger_messages WHERE id > ?', [$maxMessageId]);
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
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/cancel', [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testCancelSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        // Playwright run: cancelRun() skips the MFTF stop path (no docker exec), pure state change
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING, type: TestRun::TYPE_PLAYWRIGHT);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/cancel');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals('Run cancelled', $data['message']);

        $this->getEntityManager()->refresh($run);
        $this->assertEquals(TestRun::STATUS_CANCELLED, $run->getStatus());
    }

    public function testCancelRejectsNonCancellableRun(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        // Finished runs cannot be cancelled; the controller returns 400 before calling the service
        $run = $this->createTestRun(status: TestRun::STATUS_COMPLETED, type: TestRun::TYPE_PLAYWRIGHT);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/cancel');

        $this->assertJsonError($response, 400, 'cannot be cancelled');
    }

    // =====================
    // Retry Tests
    // =====================

    public function testRetryRequiresCsrfToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_FAILED);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $run->getId() . '/retry', [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testRetryCreatesNewRun(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $env = $this->createTestEnvironment();
        $original = $this->createTestRun($env, TestRun::STATUS_FAILED, TestRun::TYPE_PLAYWRIGHT);

        $connection = $this->getEntityManager()->getConnection();
        $maxMessageId = (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $original->getId() . '/retry');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals('New run created', $data['message']);
        $this->assertNotEquals($original->getId(), $data['run']['id']);
        $this->assertEquals(TestRun::STATUS_PENDING, $data['run']['status']);

        // New run persisted as a retry attempt of the original
        $newRun = $this->getEntityManager()->getRepository(TestRun::class)->find($data['run']['id']);
        $this->assertNotNull($newRun);
        $this->assertEquals(1, $newRun->getRetryAttempt());

        $connection->executeStatement('DELETE FROM messenger_messages WHERE id > ?', [$maxMessageId]);
    }

    // =====================
    // Live Output Tests
    // =====================

    public function testLiveOutputRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/1/live-output');

        $this->assertApiUnauthenticated($client);
    }

    public function testLiveOutputReturnsJson(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $run->getId() . '/live-output');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('status', $data);
        $this->assertEquals(TestRun::STATUS_RUNNING, $data['status']);
        $this->assertArrayHasKey('output', $data);
        $this->assertArrayHasKey('results', $data);
    }

    public function testLiveOutputReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999/live-output');

        $this->assertJsonError($response, 404);
    }

    private function createTestEnvironment(?string $name = null, bool $active = true): TestEnvironment
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $env = new TestEnvironment();
        $env->setName($name ?? "TestEnv_{$suffix}");
        $env->setCode("env_{$suffix}");
        $env->setRegion('us');
        $env->setBaseUrl("https://test_{$suffix}.example.com");
        $env->setBackendName('admin');
        $env->setIsActive($active);

        $em->persist($env);
        $em->flush();

        return $env;
    }

    private function createTestSuite(bool $active = true): TestSuite
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $suite = new TestSuite();
        $suite->setName("Suite_{$suffix}");
        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $suite->setTestPattern('SomeTestCest');
        $suite->setIsActive($active);

        $em->persist($suite);
        $em->flush();

        return $suite;
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
