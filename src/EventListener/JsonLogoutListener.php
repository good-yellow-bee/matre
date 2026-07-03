<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\Http\JsonRequestMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Returns JSON instead of the redirect for SPA logout requests.
 */
#[AsEventListener(event: LogoutEvent::class)]
class JsonLogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        if (JsonRequestMatcher::wantsJson($event->getRequest())) {
            $event->setResponse(new JsonResponse(['success' => true]));
        }
    }
}
