<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for SettingsController.
 */
class SettingsControllerTest extends WebTestCase
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

    public function testEditRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/settings');

        $this->assertResponseRedirects('/login');
    }

    public function testEditRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/settings');

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // Page Tests
    // =====================

    public function testEditReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/settings');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testEditFormRendersCorrectly(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $crawler = $client->request('GET', '/admin/settings');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('form');
    }
}
