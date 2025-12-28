<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all responses.
 *
 * This helps protect against common web vulnerabilities:
 * - XSS attacks
 * - Clickjacking
 * - MIME sniffing
 * - Information disclosure
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -256)]
class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Prevent clickjacking - deny framing from other origins
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in browsers (legacy, but still useful)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy - don't leak full URLs to external sites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy - disable unused browser features
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        );

        // Content Security Policy
        // Note: This is a relatively permissive policy to avoid breaking functionality
        // In production, you may want to tighten this further
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Required for Vue/Vite
            "style-src 'self' 'unsafe-inline'", // Required for Tailwind/inline styles
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' ws: wss:", // WebSocket for HMR in dev
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Strict Transport Security (only in production with HTTPS)
        // This is typically handled by the reverse proxy, but we set it as fallback
        if ($event->getRequest()->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }
    }
}
