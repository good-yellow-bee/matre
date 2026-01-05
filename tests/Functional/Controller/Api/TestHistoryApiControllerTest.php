<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\TestEnvironment;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TestHistoryApiController.
 */
class TestHistoryApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/test-history';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testHistoryRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '?testId=SomeTest&environmentId=1');

        $this->assertResponseRedirects('/login');
    }

    public function testHistoryAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        // Will return empty data but should be allowed
        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?testId=SomeTest&environmentId=' . $env->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
    }

    public function testTestIdsRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/test-ids');

        $this->assertResponseRedirects('/login');
    }

    // =====================
    // History Tests
    // =====================

    public function testHistoryRequiresTestId(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?environmentId=1');

        $this->assertJsonError($response, 400);
    }

    public function testHistoryRequiresEnvironmentId(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?testId=SomeTest');

        $this->assertJsonError($response, 400);
    }

    public function testHistoryReturns404ForNonExistentEnvironment(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?testId=SomeTest&environmentId=99999');

        $this->assertJsonError($response, 404);
    }

    public function testHistoryReturnsResults(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();
        $suffix = bin2hex(random_bytes(4));
        $testId = "TestClass_{$suffix}";
        $this->createTestRunWithResult($env, $testId);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . "?testId={$testId}&environmentId=" . $env->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertNotEmpty($data['data']);

        $result = $data['data'][0];
        $this->assertEquals($testId, $result['testId']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('testRun', $result);
    }

    public function testHistoryRespectsLimit(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?testId=SomeTest&environmentId=' . $env->getId() . '&limit=5');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertLessThanOrEqual(5, count($data['data']));
    }

    public function testHistoryMetaContainsEnvironmentInfo(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?testId=SomeTest&environmentId=' . $env->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals('SomeTest', $data['meta']['testId']);
        $this->assertEquals($env->getId(), $data['meta']['environmentId']);
        $this->assertEquals($env->getName(), $data['meta']['environmentName']);
    }

    // =====================
    // Test IDs Tests
    // =====================

    public function testTestIdsReturnsDistinctIds(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $env = $this->createTestEnvironment();
        $suffix = bin2hex(random_bytes(4));
        $this->createTestRunWithResult($env, "UniqueTest_{$suffix}");

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/test-ids');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertIsArray($data['data']);
    }

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

    private function createTestRunWithResult(TestEnvironment $env, string $testId): TestResult
    {
        $em = $this->getEntityManager();

        $run = new TestRun();
        $run->setEnvironment($env);
        $run->setType(TestRun::TYPE_MFTF);
        $run->setStatus(TestRun::STATUS_COMPLETED);
        $em->persist($run);

        $result = new TestResult();
        $result->setTestRun($run);
        $result->setTestId($testId);
        $result->setTestName("Test Name for {$testId}");
        $result->setStatus(TestResult::STATUS_PASSED);
        $result->setDuration(1500);
        $em->persist($result);

        $em->flush();

        return $result;
    }
}
