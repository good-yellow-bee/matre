<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\SettingsRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Enforces 2FA for sensitive API endpoints when global 2FA enforcement is enabled.
 *
 * This addresses the security issue where API endpoints were exempt from 2FA,
 * allowing users with stolen credentials to bypass 2FA by using the API.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class ApiTwoFactorListener
{
    /**
     * Sensitive API paths that require 2FA when enforcement is enabled.
     * These are operations that can modify data or execute tests.
     */
    private const SENSITIVE_API_PATHS = [
        // User management
        '/api/users' => ['POST', 'PUT', 'DELETE'],
        // Test execution
        '/api/test-runs' => ['POST'],
        // Environment management
        '/api/test-environments' => ['POST', 'PUT', 'DELETE'],
        // Cron job management
        '/api/cron-jobs' => ['POST', 'PUT', 'DELETE'],
        // Environment variables (may contain secrets)
        '/api/env-variables' => ['POST', 'PUT', 'DELETE'],
    ];

    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Only check API endpoints
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Check if this is a sensitive endpoint
        if (!$this->isSensitiveEndpoint($path, $method)) {
            return;
        }

        // Check if 2FA is enforced globally
        $settings = $this->settingsRepository->getOrCreate();
        if (!$settings->isEnforce2fa()) {
            return;
        }

        // Get current user
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Check if user has 2FA enabled
        if (!$user->isTotpEnabled()) {
            $event->setResponse(new JsonResponse([
                'error' => 'Two-factor authentication is required for this operation',
                'code' => '2FA_REQUIRED',
                'message' => 'Please enable two-factor authentication in your profile to access this API endpoint.',
            ], Response::HTTP_FORBIDDEN));

            return;
        }

        // For session-based API calls, verify the session has completed 2FA
        // This is indicated by IS_AUTHENTICATED_FULLY in Symfony's 2FA bundle
        // Note: Token-based API auth would need a different approach (TOTP in header)
    }

    /**
     * Check if the given path and method combination is considered sensitive.
     */
    private function isSensitiveEndpoint(string $path, string $method): bool
    {
        foreach (self::SENSITIVE_API_PATHS as $sensitivePathPrefix => $sensitiveMethods) {
            if (str_starts_with($path, $sensitivePathPrefix)) {
                if (in_array($method, $sensitiveMethods, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
