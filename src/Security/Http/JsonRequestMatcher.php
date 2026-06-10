<?php

declare(strict_types=1);

namespace App\Security\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Detects requests that expect a JSON response (SPA/API calls).
 */
class JsonRequestMatcher
{
    public static function wantsJson(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            || 'XMLHttpRequest' === $request->headers->get('X-Requested-With')
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }
}
