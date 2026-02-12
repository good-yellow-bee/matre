<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Settings;
use App\Entity\User;
use App\EventListener\ApiTwoFactorListener;
use App\Repository\SettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiTwoFactorListenerTest extends TestCase
{
    public function testSkipsSubRequest(): void
    {
        $event = $this->createEvent(
            Request::create('/api/users', 'POST'),
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonApiPath(): void
    {
        $event = $this->createEvent(Request::create('/admin/test'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonSensitiveEndpoint(): void
    {
        $event = $this->createEvent(Request::create('/api/users', 'GET'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenEnforce2faDisabled(): void
    {
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(false),
        );
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenNoToken(): void
    {
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage(null),
        );
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsWhenUserNotUserInstance(): void
    {
        $nonUser = $this->createStub(UserInterface::class);
        $token = $this->createTokenWithUser($nonUser);
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testAllowsWhenUserHas2faEnabled(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(true);
        $token = $this->createTokenWithUser($user);
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $listener($event);

        $this->assertNull($event->getResponse());
    }

    public function testBlocks403WhenUserLacks2fa(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(false);
        $token = $this->createTokenWithUser($user);
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $listener($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testBlocks403ForAllSensitiveEndpoints(): void
    {
        $sensitiveRequests = [
            Request::create('/api/users', 'POST'),
            Request::create('/api/users', 'PUT'),
            Request::create('/api/users', 'DELETE'),
            Request::create('/api/test-runs', 'POST'),
            Request::create('/api/test-environments', 'POST'),
            Request::create('/api/test-environments', 'PUT'),
            Request::create('/api/test-environments', 'DELETE'),
            Request::create('/api/cron-jobs', 'POST'),
            Request::create('/api/env-variables', 'DELETE'),
        ];

        foreach ($sensitiveRequests as $request) {
            $user = $this->createStub(User::class);
            $user->method('isTotpEnabled')->willReturn(false);
            $token = $this->createTokenWithUser($user);
            $event = $this->createEvent($request);

            $listener = $this->createListener(
                settingsRepository: $this->createSettingsRepository(true),
                tokenStorage: $this->createTokenStorage($token),
            );
            $listener($event);

            $this->assertSame(
                403,
                $event->getResponse()->getStatusCode(),
                sprintf('Expected 403 for %s %s', $request->getMethod(), $request->getPathInfo()),
            );
        }
    }

    public function testResponseContains2faRequiredCode(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(false);
        $token = $this->createTokenWithUser($user);
        $event = $this->createEvent(Request::create('/api/users', 'POST'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $listener($event);

        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('2FA_REQUIRED', $body['code']);
    }

    public function testSkipsNonSensitiveMethodOnSensitivePath(): void
    {
        $event = $this->createEvent(Request::create('/api/test-runs', 'GET'));

        $this->createListener()($event);

        $this->assertNull($event->getResponse());
    }

    public function testMatchesSensitiveSubpaths(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isTotpEnabled')->willReturn(false);
        $token = $this->createTokenWithUser($user);
        $event = $this->createEvent(Request::create('/api/users/123', 'PUT'));

        $listener = $this->createListener(
            settingsRepository: $this->createSettingsRepository(true),
            tokenStorage: $this->createTokenStorage($token),
        );
        $listener($event);

        $this->assertSame(403, $event->getResponse()->getStatusCode());
    }

    private function createListener(
        ?SettingsRepository $settingsRepository = null,
        ?TokenStorageInterface $tokenStorage = null,
    ): ApiTwoFactorListener {
        return new ApiTwoFactorListener(
            $settingsRepository ?? $this->createStub(SettingsRepository::class),
            $tokenStorage ?? $this->createStub(TokenStorageInterface::class),
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
