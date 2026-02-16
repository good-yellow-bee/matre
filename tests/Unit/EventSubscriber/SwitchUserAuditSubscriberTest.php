<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SwitchUserAuditSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class SwitchUserAuditSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsSwitchUser(): void
    {
        $events = SwitchUserAuditSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(SecurityEvents::SWITCH_USER, $events);
    }

    public function testLogsImpersonationStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'SECURITY AUDIT: User impersonation started',
                $this->callback(fn (array $context) => 'switch_user_start' === $context['action']
                    && 'admin' === $context['admin_user']
                    && 'target' === $context['target_user']),
            );

        $adminUser = $this->createUser('admin');
        $targetUser = $this->createUser('target');
        $token = new UsernamePasswordToken($adminUser, 'main', ['ROLE_ADMIN']);

        $event = new SwitchUserEvent(Request::create('/admin'), $targetUser, $token);

        $this->createSubscriber($logger)->onSwitchUser($event);
    }

    public function testLogsImpersonationEnd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'SECURITY AUDIT: User impersonation ended',
                $this->callback(fn (array $context) => 'switch_user_exit' === $context['action']
                    && 'admin' === $context['original_user']
                    && 'admin' === $context['impersonated_user']),
            );

        $adminUser = $this->createUser('admin');
        $originalToken = new UsernamePasswordToken($adminUser, 'main', ['ROLE_ADMIN']);
        $switchToken = new SwitchUserToken($adminUser, 'main', ['ROLE_USER'], $originalToken);

        $event = new SwitchUserEvent(Request::create('/admin'), $adminUser, $switchToken);

        $this->createSubscriber($logger)->onSwitchUser($event);
    }

    private function createSubscriber(?LoggerInterface $logger = null): SwitchUserAuditSubscriber
    {
        return new SwitchUserAuditSubscriber(
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function createUser(string $identifier): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn($identifier);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        return $user;
    }
}
