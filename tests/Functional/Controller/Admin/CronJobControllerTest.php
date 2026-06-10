<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\CronJob;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for cron job SPA shell pages (mutations covered by CronJobApiControllerTest).
 */
class CronJobControllerTest extends WebTestCase
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

        $client->request('GET', '/admin/cron-jobs');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/cron-jobs');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/cron-jobs');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('#app');
    }

    // =====================
    // New Form Tests
    // =====================

    public function testNewFormReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/cron-jobs/new');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Show Tests
    // =====================

    public function testShowReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $client->request('GET', '/admin/cron-jobs/' . $job->getId());

        $this->assertResponseStatusCodeSame(200);
    }

    public function testShowServesShellForNonExistentId(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // SPA shell is served for any id; the 404 state is handled client-side
        // (API behavior covered by CronJobApiControllerTest::testGetReturns404ForNonExistent)
        $client->request('GET', '/admin/cron-jobs/99999');

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
        $job = $this->createCronJob();

        $client->request('GET', '/admin/cron-jobs/' . $job->getId() . '/edit');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Helpers
    // =====================

    private function createCronJob(?string $name = null): CronJob
    {
        $em = $this->getEntityManager();
        $suffix = bin2hex(random_bytes(4));

        $job = new CronJob();
        $job->setName($name ?? "CronJob_{$suffix}");
        $job->setCommand("app:test:run --filter=Test_{$suffix}");
        $job->setCronExpression('0 * * * *');
        $job->setIsActive(true);

        $em->persist($job);
        $em->flush();

        return $job;
    }
}
