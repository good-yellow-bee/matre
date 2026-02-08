<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for TwoFactorController.
 */
class TwoFactorControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testTwoFactorPageRedirectsUnauthenticated(): void
    {
        $client = self::createClient();

        $client->request('GET', '/2fa');

        // Unauthenticated users are redirected to login
        $this->assertResponseRedirects('/login');
    }
}
