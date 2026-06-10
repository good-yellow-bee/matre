<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Validates the X-CSRF-Token header for all mutating API requests.
 *
 * Runs after the firewall (priority 8) so authentication errors take precedence.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
class ApiCsrfListener
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/') || '/api/login' === $path) {
            return;
        }

        if (\in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        $token = new CsrfToken('api', (string) $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid CSRF token'], 403));
        }
    }
}
