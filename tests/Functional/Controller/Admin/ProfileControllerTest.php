<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for ProfileController.
 */
class ProfileControllerTest extends WebTestCase
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

    public function testNotificationsRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/profile/notifications');

        $this->assertResponseRedirects('/login');
    }

    // =====================
    // Authorization Tests
    // =====================

    public function testNotificationsAllowsUserRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/profile/notifications');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Form Tests
    // =====================

    public function testNotificationsFormRendersCorrectly(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $crawler = $client->request('GET', '/admin/profile/notifications');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('[data-vue-island="profile-notifications"]');
    }

    public function testNotificationsFormSubmission(): void
    {
        $this->markTestSkipped('Form CSRF session handling in functional tests needs refactoring');
    }
}
