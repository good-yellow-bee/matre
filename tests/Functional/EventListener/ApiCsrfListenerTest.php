<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventListener;

use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for ApiCsrfListener same-origin protection.
 *
 * The stateless CSRF manager (SameOriginCsrfTokenManager) accepts any sufficiently long token
 * only when the request looks same-origin. These tests pin that a valid-LENGTH token is still
 * rejected when the origin signals say cross-site — i.e. token length alone is not the gate.
 */
class ApiCsrfListenerTest extends WebTestCase
{
    use ApiTestTrait;

    /** A token long enough to pass the 24-char minimum (so the only failing factor is the origin) */
    private const VALID_LENGTH_TOKEN = 'functional-test-csrf-token-value';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    public function testCrossSiteMutatingRequestRejectedDespiteValidLengthToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // Mutating /api request, long token, but cross-site origin signals → listener returns 403
        $response = $this->jsonRequest(
            $client,
            'POST',
            '/api/users/validate-username',
            ['username' => 'someuser'],
            [
                'HTTP_X-CSRF-Token' => self::VALID_LENGTH_TOKEN,
                'HTTP_SEC_FETCH_SITE' => 'cross-site',
            ],
        );

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testSameOriginMutatingRequestPassesWithValidLengthToken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // Same payload + token, but same-origin → listener lets it through to the controller (200)
        $response = $this->jsonRequest(
            $client,
            'POST',
            '/api/users/validate-username',
            ['username' => 'someuser'],
            [
                'HTTP_X-CSRF-Token' => self::VALID_LENGTH_TOKEN,
                'HTTP_SEC_FETCH_SITE' => 'same-origin',
            ],
        );

        $this->assertJsonResponse($response, 200);
    }
}
