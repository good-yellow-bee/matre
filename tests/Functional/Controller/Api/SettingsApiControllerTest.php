<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for SettingsApiController.
 */
class SettingsApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/settings';

    protected function tearDown(): void
    {
        // The functional suite is non-transactional and Settings is a singleton row.
        // Force enforce2fa back to false so a stray write here can't lock every later test out of /admin and /api.
        if (null !== $this->entityManager) {
            $this->entityManager->getConnection()->executeStatement('UPDATE matre_settings SET enforce2fa = 0');
        }

        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testGetRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL);

        $this->assertApiUnauthenticated($client);
    }

    public function testGetRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $this->jsonRequest($client, 'PUT', self::BASE_URL, ['siteName' => 'Nope']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL, ['siteName' => 'Nope'], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    // =====================
    // Get Tests
    // =====================

    public function testGetReturnsSettingsFields(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('siteName', $data);
        $this->assertArrayHasKey('adminPanelTitle', $data);
        $this->assertArrayHasKey('defaultLocale', $data);
        $this->assertArrayHasKey('enforce2fa', $data);
        $this->assertArrayHasKey('maxRetryCount', $data);
        $this->assertArrayHasKey('autoReportForIndividualRuns', $data);
    }

    // =====================
    // Update Tests
    // =====================

    public function testUpdateRoundtrip(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $suffix = bin2hex(random_bytes(4));
        $newName = "MATRE_{$suffix}";

        // Deliberately not toggling enforce2fa: a non-TOTP admin enabling it would be
        // immediately locked out (403) by TwoFactorEnforcementSubscriber on the next request.
        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL, [
            'siteName' => $newName,
            'maxRetryCount' => 3,
            'autoReportForIndividualRuns' => true,
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        // GET reflects the persisted change
        $getResponse = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $getData = $this->assertJsonResponse($getResponse, 200);
        $this->assertEquals($newName, $getData['siteName']);
        $this->assertEquals(3, $getData['maxRetryCount']);
        $this->assertTrue($getData['autoReportForIndividualRuns']);
    }

    public function testUpdateRejectsBlankSiteName(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL, [
            'siteName' => '',
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('siteName', $data['errors']);
    }
}
