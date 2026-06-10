<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\SettingsRepository;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * SPA authentication endpoints: login stub (intercepted by json_login) and session bootstrap.
 */
#[Route('/api')]
class AuthApiController extends AbstractController
{
    private const CSRF_TOKEN_IDS = ['api', 'test_run_api', 'env_variable_api', 'cron_job_api', 'test_discovery'];

    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $noVncUrl,
        private readonly string $allurePublicUrl,
    ) {
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Intercepted by the json_login authenticator.');
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TwoFactorTokenInterface) {
            return $this->json([
                'authenticated' => false,
                'twoFactorRequired' => true,
                'csrf' => ['two_factor' => $this->csrfTokenManager->getToken('two_factor')->getValue()],
            ]);
        }

        $user = $token?->getUser();
        if (!$user instanceof User) {
            return $this->json(['authenticated' => false]);
        }

        $settings = $this->settingsRepository->getOrCreate();

        $csrf = [];
        foreach (self::CSRF_TOKEN_IDS as $id) {
            $csrf[$id] = $this->csrfTokenManager->getToken($id)->getValue();
        }

        return $this->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'totpEnabled' => $user->isTotpEnabled(),
            ],
            'requires2faSetup' => $settings->isEnforce2fa() && !$user->isTotpEnabled(),
            'settings' => [
                'siteName' => $settings->getSiteName(),
                'adminPanelTitle' => $settings->getAdminPanelTitle(),
                'enforce2fa' => $settings->isEnforce2fa(),
            ],
            'urls' => [
                'allure' => $this->allurePublicUrl,
                'novnc' => $this->noVncUrl,
            ],
            'csrf' => $csrf,
        ]);
    }
}
