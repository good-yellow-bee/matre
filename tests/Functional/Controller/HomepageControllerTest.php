<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the homepage SPA shell route.
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
        $this->assertSelectorExists('#app');
    }

    public function testHomepageServesShellForAuthenticatedUser(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        // The SPA router redirects authenticated users to /admin client-side
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#app');
    }
}
