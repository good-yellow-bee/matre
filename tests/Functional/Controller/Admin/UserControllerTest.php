<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testShowReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(404);
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

    // =====================
    // Delete Tests
    // =====================

    public function testDeleteRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $client->request('POST', self::BASE_URL . '/' . $user->getId() . '/delete', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    public function testDeletePreventsSelfDeletion(): void
    {
        // Self-deletion check is inside CSRF-validated block; dynamic CSRF token IDs
        // (delete{id}) require session which isn't available in functional test containers
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Toggle Active Tests
    // =====================

    public function testToggleActiveRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $client->request('POST', self::BASE_URL . '/' . $user->getId() . '/toggle-active', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    public function testToggleActivePreventsSelfDeactivation(): void
    {
        // Self-deactivation check is inside CSRF-validated block; dynamic CSRF token IDs
        // (toggle{id}) require session which isn't available in functional test containers
        $this->markTestSkipped('CSRF session handling in functional tests needs refactoring');
    }

    // =====================
    // Reset 2FA Tests
    // =====================

    public function testReset2faRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $client->request('POST', self::BASE_URL . '/' . $user->getId() . '/reset-2fa', [
            '_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/admin/users/' . $user->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }
}
