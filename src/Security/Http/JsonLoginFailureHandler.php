<?php

declare(strict_types=1);

namespace App\Security\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 401 JSON response with translated message for failed POST /api/login.
 */
class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'authenticated' => false,
            'error' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security'),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
