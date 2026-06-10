<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the 2FA setup page shell and the /api/2fa-setup endpoints.
 */
class TwoFactorSetupControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testSetupPageRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/2fa-setup');

        $this->assertResponseRedirects('/login');
    }

    public function testSetupApiRejectsGet(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        // Provisioning is state-changing, so it only accepts POST
        $client->request('GET', '/api/2fa-setup');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testSetupApiRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/2fa-setup');

        $this->assertApiUnauthenticated($client);
    }

    public function testSetupApiRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', '/api/2fa-setup');

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testSetupApiGeneratesSecretAndQrCode(): void
    {
        $client = self::createClient();
        $user = $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', '/api/2fa-setup', csrfTokenId: 'api');

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['enabled']);
        $this->assertNotEmpty($data['secret']);
        $this->assertStringStartsWith('data:image/png;base64,', $data['qrCode']);

        $this->getEntityManager()->refresh($user);
        $this->assertNotNull($user->getTotpSecret());
        $this->assertFalse($user->isTotpEnabled());
    }

    public function testSetupApiReportsAlreadyEnabled(): void
    {
        $client = self::createClient();
        $user = $this->loginAsUser($client);
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setIsTotpEnabled(true);
        $this->getEntityManager()->flush();

        $response = $this->jsonRequest($client, 'POST', '/api/2fa-setup', csrfTokenId: 'api');

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['enabled']);
    }

    public function testVerifyRejectsWhenSetupNotInitiated(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $response = $this->jsonRequest($client, 'POST', '/api/2fa-setup/verify', [
            'code' => '123456',
        ], csrfTokenId: 'api');

        $this->assertJsonError($response, 400, 'not been initiated');
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $client = self::createClient();
        $user = $this->loginAsUser($client);

        // Initiate setup to generate a secret
        $this->jsonRequest($client, 'POST', '/api/2fa-setup', csrfTokenId: 'api');

        $response = $this->jsonRequest($client, 'POST', '/api/2fa-setup/verify', [
            'code' => '000000',
        ], csrfTokenId: 'api');

        $this->assertJsonError($response, 400, 'Invalid verification code');

        // The kernel reboot between requests detaches $user, so re-fetch instead of refresh
        $em = $this->getEntityManager();
        $em->clear();
        $fresh = $em->find(User::class, $user->getId());
        $this->assertFalse($fresh->isTotpEnabled());
    }
}
