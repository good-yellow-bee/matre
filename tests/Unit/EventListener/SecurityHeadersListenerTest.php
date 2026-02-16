<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityHeadersListenerTest extends TestCase
{
    public function testSkipsSubRequest(): void
    {
        $response = new Response();
        $event = $this->createEvent(
            Request::create('/admin'),
            $response,
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->createListener()($event);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function testSkipsDevEnvironment(): void
    {
        $response = new Response();
        $event = $this->createEvent(Request::create('/admin'), $response);

        $this->createListener('dev')($event);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function testSetsSecurityHeadersInProd(): void
    {
        $response = new Response();
        $event = $this->createEvent(Request::create('/admin'), $response);

        $this->createListener()($event);

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function testSetsHstsForSecureRequests(): void
    {
        $response = new Response();
        $request = Request::create('https://example.com/admin');
        $request->server->set('HTTPS', 'on');
        $event = $this->createEvent($request, $response);

        $this->createListener()($event);

        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
    }

    public function testNoHstsForInsecureRequests(): void
    {
        $response = new Response();
        $event = $this->createEvent(Request::create('http://example.com/admin'), $response);

        $this->createListener()($event);

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    private function createListener(string $environment = 'prod'): SecurityHeadersListener
    {
        return new SecurityHeadersListener($environment);
    }

    private function createEvent(
        Request $request,
        Response $response,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $requestType,
            $response,
        );
    }
}
