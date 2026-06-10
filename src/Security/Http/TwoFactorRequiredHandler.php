<?php

declare(strict_types=1);

namespace App\Security\Http;

use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Access attempt while 2FA is pending: 401 JSON for SPA requests, redirect to the 2FA form otherwise.
 */
class TwoFactorRequiredHandler implements AuthenticationRequiredHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationRequired(Request $request, TokenInterface $token): Response
    {
        if (JsonRequestMatcher::wantsJson($request)) {
            return new JsonResponse([
                'authenticated' => false,
                'twoFactorRequired' => true,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('2fa_login'));
    }
}
