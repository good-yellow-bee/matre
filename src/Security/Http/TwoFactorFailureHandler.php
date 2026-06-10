<?php

declare(strict_types=1);

namespace App\Security\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Failed 2FA code check: 401 JSON for SPA requests, redirect with session error for the legacy form.
 */
class TwoFactorFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if (JsonRequestMatcher::wantsJson($request)) {
            return new JsonResponse([
                'authenticated' => false,
                'twoFactorRequired' => true,
                'error' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'SchebTwoFactorBundle'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('2fa_login'));
    }
}
