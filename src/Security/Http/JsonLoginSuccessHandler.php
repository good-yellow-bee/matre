<?php

declare(strict_types=1);

namespace App\Security\Http;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * JSON response for POST /api/login; signals when a 2FA code is still required.
 */
class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        if ($token instanceof TwoFactorTokenInterface) {
            return new JsonResponse(['authenticated' => false, 'twoFactorRequired' => true]);
        }

        return new JsonResponse(['authenticated' => true]);
    }
}
