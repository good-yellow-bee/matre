<?php

declare(strict_types=1);

namespace App\Security\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Returns 401 JSON for API requests, redirects browsers to the login page.
 */
class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (JsonRequestMatcher::wantsJson($request)) {
            return new JsonResponse([
                'authenticated' => false,
                'error' => 'Authentication required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
