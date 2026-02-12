<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Settings;
use App\Entity\User;
use App\EventSubscriber\TwoFactorEnforcementSubscriber;
use App\Repository\SettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class TwoFactorEnforcementSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsKernelRequest(): void
    {
        $events = TwoFactorEnforcementSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testSkipsSubRequest(): void
    {
        $request = Request::create('/admin/dashboard');
        $event = $this->createEvent($request, HttpKernelInterface::SUB_REQUEST);

        $this->createSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsAllowedRoutes(): void
    {
        $request = Request::create('/admin/2fa-setup');
        $request->attributes->set('_route', '2fa_setup');
        $event = $this->createEvent($request);

        $this->createSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsApiPaths(): void
    {
        $request = Request::create('/api/test');
        $event = $this->createEvent($request);

        $this->createSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonAdminPaths(): void
    {
        $request = Request::create('/public');
        $event = $this->createEvent($request);

        $this->createSubscriber()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenNotAuthenticated(): void
    {
        $request = Request::create('/admin/dashboard');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $subscriber = $this->createSubscriber(
            tokenStorage: $this->createTokenStorage(null),
        );
        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenNotUserEntity(): void
    {
        $nonUser = $this->createStub(UserInterface::class);
        $token = $this->createTokenWithUser($nonUser);

        $request = Request::create('/admin/dashboard');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $subscriber = $this->createSubscriber(
            tokenStorage: $this->createTokenStorage($token),
        );
        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhen2faNotEnforced(): void
    {
        $user = $this->createStub(User::class);
        $token = $this->createTokenWithUser($user);

        $request = Request::create('/admin/dashboard');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $subscriber = $this->createSubscriber(
            settingsRepository: $this->createSettingsRepository(false),
            tokenStorage: $this->createTokenStorage($token),
        );
        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenUserHas2faEnabled(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(true);
        $token = $this->createTokenWithUser($user);

        $request = Request::create('/admin/dashboard');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $subscriber = $this->createSubscriber(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testRedirectsWhen2faRequiredButNotSetup(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(false);
        $token = $this->createTokenWithUser($user);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/admin/2fa-setup');

        $request = Request::create('/admin/dashboard');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $subscriber = $this->createSubscriber(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
            urlGenerator: $urlGenerator,
        );
        $subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertTrue($event->getResponse()->isRedirection());
    }

    private function createSubscriber(
        ?SettingsRepository $settingsRepository = null,
        ?TokenStorageInterface $tokenStorage = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): TwoFactorEnforcementSubscriber {
        return new TwoFactorEnforcementSubscriber(
            $settingsRepository ?? $this->createStub(SettingsRepository::class),
            $tokenStorage ?? $this->createStub(TokenStorageInterface::class),
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
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

    private function createTokenStorage(?TokenInterface $token): TokenStorageInterface
    {
        $storage = $this->createStub(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }

    private function createTokenWithUser(UserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createSettingsRepository(bool $enforce2fa): SettingsRepository
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('isEnforce2fa')->willReturn($enforce2fa);

        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return $repository;
    }
}
