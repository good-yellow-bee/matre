<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AuditLogController.
 */
class AuditLogControllerTest extends WebTestCase
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

        $client->request('GET', '/admin/audit-logs');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/audit-logs');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // Page Tests
    // =====================

    public function testIndexReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/audit-logs');

        $this->assertResponseStatusCodeSame(200);
    }
}
