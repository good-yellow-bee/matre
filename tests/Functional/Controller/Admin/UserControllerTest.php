<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for user admin SPA shell pages (mutations covered by UserApiControllerTest).
 */
class UserControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/admin/users';

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

        $client->request('GET', self::BASE_URL);

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#app');
    }

    // =====================
    // New Tests
    // =====================

    public function testNewReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL . '/new');

        $this->assertResponseIsSuccessful();
    }

    // =====================
    // Show Tests
    // =====================

    public function testShowReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $client->request('GET', self::BASE_URL . '/' . $user->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testShowServesShellForNonExistentId(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // SPA shell is served for any id; the 404 state is handled client-side
        // (API behavior covered by UserApiControllerTest::testGetUserReturns404ForNonExistent)
        $client->request('GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('#app');
    }

    // =====================
    // Edit Tests
    // =====================

    public function testEditReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $client->request('GET', self::BASE_URL . '/' . $user->getId() . '/edit');

        $this->assertResponseIsSuccessful();
    }
}
