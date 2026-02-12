<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\CronJob;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for CronJobController.
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

    public function testShowReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/cron-jobs/99999');

        $this->assertResponseStatusCodeSame(404);
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
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $client->request('POST', '/admin/cron-jobs/' . $job->getId() . '/delete');

        $this->assertResponseRedirects('/admin/cron-jobs');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    public function testDeleteWithInvalidCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $client->request('POST', '/admin/cron-jobs/' . $job->getId() . '/delete', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/cron-jobs');
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
        $job = $this->createCronJob();

        $client->request('POST', '/admin/cron-jobs/' . $job->getId() . '/toggle-active');

        $this->assertResponseRedirects('/admin/cron-jobs');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Run Tests
    // =====================

    public function testRunRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $job = $this->createCronJob();

        $client->request('POST', '/admin/cron-jobs/' . $job->getId() . '/run');

        $this->assertResponseRedirects('/admin/cron-jobs/' . $job->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
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
