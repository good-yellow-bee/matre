<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for SecurityController.
 */
class SecurityControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testLoginPageReturns200(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageRedirectsAuthenticatedUser(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/login');

        $this->assertResponseRedirects('/admin');
    }

    public function testLogoutRoute(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/logout');

        // Symfony intercepts this and redirects to login
        $this->assertResponseRedirects();
    }

    public function testLoginFormRendersCorrectly(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }
}
