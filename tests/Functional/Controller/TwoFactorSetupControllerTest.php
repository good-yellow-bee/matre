<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TwoFactorSetupController.
 */
class TwoFactorSetupControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testSetupRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/2fa-setup');

        $this->assertResponseRedirects('/login');
    }

    public function testSetupReturns200ForAuthenticatedUser(): void
    {
        // TotpAuthenticatorInterface generates secrets and QR codes,
        // which requires complex service mocking in functional tests
        $this->markTestSkipped('2FA setup requires TotpAuthenticatorInterface mocking');
    }
}
