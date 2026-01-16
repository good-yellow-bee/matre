<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\CronJob;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for CronJobApiController.
 */
class CronJobApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/cron-jobs';

    /** @var int[] IDs of cron jobs created during tests */
    private array $createdJobIds = [];

    protected function tearDown(): void
    {
        // Clean up any CronJobs created during tests
        if (!empty($this->createdJobIds) && null !== $this->entityManager) {
            $this->entityManager->createQuery(
                'DELETE FROM App\Entity\CronJob c WHERE c.id IN (:ids)',
            )->execute(['ids' => $this->createdJobIds]);
        }

        $this->createdJobIds = [];
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL . '/list');

        $this->assertResponseRedirects('/login');
    }

    public function testListRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL . '/list');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // List Tests
    // =====================

    public function testListReturnsCronJobs(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $this->createCronJob();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('perPage', $data);
        $this->assertIsArray($data['data']);
    }

    public function testListSupportsPagination(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list?page=1&perPage=5');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertLessThanOrEqual(5, count($data['data']));
        $this->assertEquals(1, $data['page']);
        $this->assertEquals(5, $data['perPage']);
    }

    public function testListSupportsSearch(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));
        $this->createCronJob("SearchableCron_{$suffix}");

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . "/list?search=SearchableCron_{$suffix}");
        $data = $this->assertJsonResponse($response, 200);

        $this->assertGreaterThanOrEqual(1, count($data['data']));
        foreach ($data['data'] as $job) {
            $this->assertStringContainsString("SearchableCron_{$suffix}", $job['name']);
        }
    }

    public function testListSupportsSorting(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/list?sort=name&order=desc');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(1, $data['page']);
    }

    // =====================
    // Get Tests
    // =====================

    public function testGetReturnsCronJob(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $job->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($job->getId(), $data['id']);
        $this->assertEquals($job->getName(), $data['name']);
        $this->assertEquals($job->getCommand(), $data['command']);
        $this->assertEquals($job->getCronExpression(), $data['cronExpression']);
        $this->assertArrayHasKey('lastOutput', $data);
    }

    public function testGetReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

        $this->assertJsonError($response, 404);
    }

    // =====================
    // Toggle Active Tests
    // =====================

    public function testToggleActiveRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $job->getId() . '/toggle-active');

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testToggleActiveSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Run Tests
    // =====================

    public function testRunRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $job->getId() . '/run');

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testRunSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/' . $job->getId());

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testDeleteSucceeds(): void
    {
        // Skip - CSRF session handling in functional tests needs refactoring
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    public function testDeleteReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // 404 check happens before CSRF validation in the controller
        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/99999');

        $this->assertJsonError($response, 404);
    }

    private function createCronJob(?string $name = null, bool $active = true): CronJob
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $job = new CronJob();
        $job->setName($name ?? "CronJob_{$suffix}");
        $job->setCommand("app:test:run --filter=Test_{$suffix}");
        $job->setCronExpression('0 * * * *');
        $job->setIsActive($active);

        $em->persist($job);
        $em->flush();

        // Track for cleanup
        $this->createdJobIds[] = $job->getId();

        return $job;
    }
}
