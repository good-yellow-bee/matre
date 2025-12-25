<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Logs all user impersonation events for security audit purposes.
 *
 * This addresses the security concern that admin impersonation could go undetected.
 * All switch_user events are logged with relevant details.
 */
class SwitchUserAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::SWITCH_USER => 'onSwitchUser',
        ];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $request = $event->getRequest();
        $targetUser = $event->getTargetUser();
        $token = $event->getToken();

        // Determine if this is an impersonation start or exit
        $isExitingImpersonation = $token instanceof SwitchUserToken;

        if ($isExitingImpersonation) {
            // Exiting impersonation - returning to original user
            $originalUser = $token->getOriginalToken()->getUser();

            $this->logger->warning('SECURITY AUDIT: User impersonation ended', [
                'action' => 'switch_user_exit',
                'original_user' => $originalUser?->getUserIdentifier(),
                'impersonated_user' => $targetUser->getUserIdentifier(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]);
        } else {
            // Starting impersonation
            $currentUser = $token?->getUser();

            $this->logger->warning('SECURITY AUDIT: User impersonation started', [
                'action' => 'switch_user_start',
                'admin_user' => $currentUser?->getUserIdentifier(),
                'target_user' => $targetUser->getUserIdentifier(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'request_uri' => $request->getRequestUri(),
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]);
        }
    }
}
