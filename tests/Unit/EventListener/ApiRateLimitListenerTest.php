<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class ApiRateLimitListenerTest extends TestCase
{
    public function testSkipsSubRequest(): void
    {
        $event = $this->createEvent(
            Request::create('/api/test'),
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonApiPath(): void
    {
        $event = $this->createEvent(Request::create('/admin/dashboard'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testAllowsApiRequestWithinLimit(): void
    {
        $event = $this->createEvent(Request::create('/api/test'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testBlocksApiRequestWhenRateLimited(): void
    {
        $apiLimiter = $this->createRateLimiterFactory('api', 1);
        $listener = $this->createListener(apiLimiter: $apiLimiter);

        $first = $this->createEvent(Request::create('/api/test'));
        $listener($first);
        $this->assertNull($first->getResponse());

        $second = $this->createEvent(Request::create('/api/test'));
        $listener($second);
        $this->assertNotNull($second->getResponse());
        $this->assertSame(429, $second->getResponse()->getStatusCode());
    }

    public function testUserCreationHasStricterLimit(): void
    {
        $userCreationLimiter = $this->createRateLimiterFactory('user_creation', 1);
        $listener = $this->createListener(userCreationLimiter: $userCreationLimiter);

        $first = $this->createEvent(Request::create('/api/users', 'POST'));
        $listener($first);
        $this->assertNull($first->getResponse());

        $second = $this->createEvent(Request::create('/api/users', 'POST'));
        $listener($second);
        $this->assertNotNull($second->getResponse());
        $this->assertSame(429, $second->getResponse()->getStatusCode());
    }

    public function testUserCreationLimitDoesNotAffectGetRequests(): void
    {
        $userCreationLimiter = $this->createRateLimiterFactory('user_creation', 1);
        $listener = $this->createListener(userCreationLimiter: $userCreationLimiter);

        $first = $this->createEvent(Request::create('/api/users', 'POST'));
        $listener($first);
        $this->assertNull($first->getResponse());

        $get = $this->createEvent(Request::create('/api/users', 'GET'));
        $listener($get);
        $this->assertNull($get->getResponse());
    }

    public function testRateLimitResponseContainsRetryAfterHeader(): void
    {
        $apiLimiter = $this->createRateLimiterFactory('api', 1);
        $listener = $this->createListener(apiLimiter: $apiLimiter);

        $this->createEvent(Request::create('/api/test'));
        $first = $this->createEvent(Request::create('/api/test'));
        $listener($first);

        $second = $this->createEvent(Request::create('/api/test'));
        $listener($second);

        $this->assertTrue($second->getResponse()->headers->has('Retry-After'));
        $this->assertTrue($second->getResponse()->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($second->getResponse()->headers->has('X-RateLimit-Limit'));
    }

    public function testUsesUnknownWhenClientIpIsNull(): void
    {
        $event = $this->createEvent(Request::create('/api/test'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    private function createListener(
        ?RateLimiterFactory $apiLimiter = null,
        ?RateLimiterFactory $userCreationLimiter = null,
    ): ApiRateLimitListener {
        return new ApiRateLimitListener(
            $apiLimiter ?? $this->createRateLimiterFactory('api', 100),
            $userCreationLimiter ?? $this->createRateLimiterFactory('user_creation', 100),
        );
    }

    private function createEvent(
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $requestType,
        );
    }

    private function createRateLimiterFactory(string $id, int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );
    }
}
