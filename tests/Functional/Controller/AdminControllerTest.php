<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AdminController.
 */
class AdminControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testDashboardRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testDashboardAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testDashboardAllowsAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testDashboardRendersCorrectly(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-vue-island]');
    }
}
