<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for SPA authentication: login page shell, json_login, 2FA challenge, /api/me and logout.
 */
class SecurityControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private string $totpUsername = '';

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
        $this->assertArrayNotHasKey('csrf', $data);
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
        $this->assertArrayNotHasKey('csrf', $data);
    }

    // =====================
    // Full 2FA challenge (/2fa_check)
    // =====================

    public function testTwoFactorLoginCompletesWithValidCode(): void
    {
        $client = self::createClient();
        $secret = $this->createTotpUser();

        // Step 1: password login signals the 2FA challenge (session now IS_AUTHENTICATED_2FA_IN_PROGRESS)
        $loginResponse = $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $this->totpUsername,
            'password' => 'Password123!',
        ]);
        $loginData = $this->assertJsonResponse($loginResponse, 200);
        $this->assertFalse($loginData['authenticated']);
        $this->assertTrue($loginData['twoFactorRequired']);

        // Step 2: submit a valid TOTP code to /2fa_check (scheb success handler returns JSON)
        $totp = TOTP::createFromSecret($secret);
        if ($totp->expiresIn() < 2) {
            sleep(2);
        }
        $checkResponse = $this->jsonRequest($client, 'POST', '/2fa_check', [
            '_auth_code' => $totp->now(),
        ]);
        $checkData = $this->assertJsonResponse($checkResponse, 200);
        $this->assertTrue($checkData['authenticated']);

        // Step 3: /api/me now reports a fully-authenticated user
        $meResponse = $this->jsonRequest($client, 'GET', '/api/me');
        $meData = $this->assertJsonResponse($meResponse, 200);
        $this->assertTrue($meData['authenticated']);
        $this->assertEquals($this->totpUsername, $meData['user']['username']);
    }

    public function testTwoFactorLoginRejectsInvalidCode(): void
    {
        $client = self::createClient();
        $this->createTotpUser();

        $this->jsonRequest($client, 'POST', '/api/login', [
            'username' => $this->totpUsername,
            'password' => 'Password123!',
        ]);

        $checkResponse = $this->jsonRequest($client, 'POST', '/2fa_check', [
            '_auth_code' => '000000',
        ]);
        $checkData = $this->assertJsonResponse($checkResponse, 401);
        $this->assertFalse($checkData['authenticated']);
        $this->assertTrue($checkData['twoFactorRequired']);

        // Still not fully authenticated
        $meData = $this->assertJsonResponse($this->jsonRequest($client, 'GET', '/api/me'), 200);
        $this->assertFalse($meData['authenticated']);
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

    /**
     * Create a throwaway TOTP-enabled user (never the shared admin) and return the raw base32 secret.
     * OTPHP defaults (SHA1, 30s, 6 digits) match User::getTotpAuthenticationConfiguration.
     */
    private function createTotpUser(): string
    {
        // Standard 32-char base32 secret. TOTP::generate() yields a 103-char secret that the
        // length-based CredentialEncryptionService::isEncrypted() misreads as already-encrypted,
        // so it is stored un-encrypted and then fails to decrypt on load.
        $secret = rtrim(Base32::encodeUpper(random_bytes(20)), '=');
        $user = $this->createUser(password: 'Password123!');
        $user->setTotpSecret($secret);
        $user->setIsTotpEnabled(true);
        $this->getEntityManager()->flush();
        $this->totpUsername = $user->getUsername();

        return $secret;
    }
}
