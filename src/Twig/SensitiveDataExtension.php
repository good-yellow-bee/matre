<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for handling sensitive data in templates.
 *
 * Provides filters and functions to:
 * - Mask sensitive values (passwords, secrets, keys)
 * - Detect if a variable name indicates sensitive data
 */
class SensitiveDataExtension extends AbstractExtension
{
    /**
     * Patterns that indicate sensitive variable names.
     */
    private const SENSITIVE_PATTERNS = [
        '/password/i',
        '/secret/i',
        '/key$/i',      // API_KEY, SECRET_KEY, etc.
        '/token/i',
        '/credential/i',
        '/auth/i',
        '/private/i',
        '/^api[_-]/i',  // API_*, api-*
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('mask_sensitive', [$this, 'maskSensitive']),
            new TwigFilter('mask_if_sensitive', [$this, 'maskIfSensitive']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_sensitive_name', [$this, 'isSensitiveName']),
        ];
    }

    /**
     * Mask a value completely, showing only asterisks.
     */
    public function maskSensitive(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $length = min(strlen($value), 16);

        return str_repeat('*', $length);
    }

    /**
     * Mask a value only if the variable name indicates it's sensitive.
     *
     * @param string|null $value The value to potentially mask
     * @param string $name The variable name
     */
    public function maskIfSensitive(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($this->isSensitiveName($name)) {
            return $this->maskSensitive($value);
        }

        return $value;
    }

    /**
     * Check if a variable name indicates sensitive data.
     */
    public function isSensitiveName(string $name): bool
    {
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
