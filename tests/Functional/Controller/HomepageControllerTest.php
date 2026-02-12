<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for HomepageController.
 */
class HomepageControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testHomepageReturns200ForAnonymous(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testHomepageRedirectsAuthenticatedUser(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/');

        $this->assertResponseRedirects('/admin');
    }
}
