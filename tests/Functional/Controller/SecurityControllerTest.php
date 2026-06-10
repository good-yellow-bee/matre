<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for SPA authentication: login page shell, json_login, /api/me and logout.
 */
class SecurityControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Login Page (SPA shell)
    // =====================

    public function testLoginPageReturns200(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#app');
    }

    public function testLoginPageServesShellForAuthenticatedUser(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        // The SPA router redirects authenticated users away from /login client-side
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#app');
    }

    // =====================
    // JSON Login
    // =====================

    public function testJsonLoginSucceeds(): void
    {
        $client = self::createClient();
        $user = $this->createUser(password: 'Password123!');

        $response = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $user->getUsername(),
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['authenticated']);
    }

    public function testJsonLoginWithInvalidPasswordReturns401(): void
    {
        $client = self::createClient();
        $user = $this->createUser(password: 'Password123!');

        $response = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $user->getUsername(),
            'password' => 'WrongPassword!',
        ]);

        $data = $this->assertJsonResponse($response, 401);
        $this->assertFalse($data['authenticated']);
        $this->assertArrayHasKey('error', $data);
    }

    public function testJsonLoginWithNonexistentUserReturns401(): void
    {
        $client = self::createClient();

        $response = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => 'doesnotexist_' . bin2hex(random_bytes(4)),
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 401);
        $this->assertFalse($data['authenticated']);
    }

    public function testJsonLoginWithInactiveUserReturns401(): void
    {
        $client = self::createClient();
        $user = $this->createUser(password: 'Password123!', active: false);

        $response = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $user->getUsername(),
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 401);
        $this->assertFalse($data['authenticated']);
    }

    public function testJsonLoginSignalsTwoFactorRequirement(): void
    {
        $client = self::createClient();
        $user = $this->createUser(password: 'Password123!');
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setIsTotpEnabled(true);
        $this->getEntityManager()->flush();

        $response = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $user->getUsername(),
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['authenticated']);
        $this->assertTrue($data['twoFactorRequired']);
    }

    // =====================
    // /api/me
    // =====================

    public function testMeReturnsUnauthenticatedState(): void
    {
        $client = self::createClient();

        $response = $this->jsonRequest($client, 'GET', '/api/me');

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['authenticated']);
        $this->assertArrayNotHasKey('user', $data);
    }

    public function testMeReturnsAuthenticatedState(): void
    {
        $client = self::createClient();
        $user = $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'GET', '/api/me');

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['authenticated']);
        $this->assertEquals($user->getUsername(), $data['user']['username']);
        $this->assertEquals($user->getEmail(), $data['user']['email']);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('csrf', $data);
        $this->assertArrayHasKey('api', $data['csrf']);
    }

    public function testMeReturnsTwoFactorInProgressState(): void
    {
        $client = self::createClient();
        $user = $this->createUser(password: 'Password123!');
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setIsTotpEnabled(true);
        $this->getEntityManager()->flush();

        $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $user->getUsername(),
            'password' => 'Password123!',
        ]);

        $response = $this->jsonRequest($client, 'GET', '/api/me');

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['authenticated']);
        $this->assertTrue($data['twoFactorRequired']);
        $this->assertArrayHasKey('two_factor', $data['csrf']);
    }

    // =====================
    // Logout
    // =====================

    public function testLogoutRoute(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/logout');

        // Symfony intercepts this and redirects to login
        $this->assertResponseRedirects();
    }
}
