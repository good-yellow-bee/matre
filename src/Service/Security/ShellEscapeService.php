<?php

declare(strict_types=1);

namespace App\Service\Security;

/**
 * Service for secure shell command building.
 *
 * Provides validation and escaping utilities to prevent command injection
 * when building shell commands dynamically.
 */
class ShellEscapeService
{
    /**
     * Valid environment variable name pattern.
     * Must start with letter or underscore, followed by alphanumerics/underscores.
     */
    private const ENV_VAR_NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Maximum length for environment variable names.
     */
    private const MAX_ENV_VAR_NAME_LENGTH = 128;

    /**
     * Maximum length for environment variable values.
     */
    private const MAX_ENV_VAR_VALUE_LENGTH = 32768;

    /**
     * Validate an environment variable name.
     *
     * @throws \InvalidArgumentException if name is invalid
     */
    public function validateEnvVarName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Environment variable name cannot be empty');
        }

        if (strlen($name) > self::MAX_ENV_VAR_NAME_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Environment variable name exceeds maximum length of %d characters', self::MAX_ENV_VAR_NAME_LENGTH));
        }

        if (!preg_match(self::ENV_VAR_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid environment variable name "%s". Must match pattern: %s', $name, self::ENV_VAR_NAME_PATTERN));
        }
    }

    /**
     * Validate an environment variable value.
     *
     * @throws \InvalidArgumentException if value is invalid
     */
    public function validateEnvVarValue(string $value): void
    {
        if (strlen($value) > self::MAX_ENV_VAR_VALUE_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Environment variable value exceeds maximum length of %d characters', self::MAX_ENV_VAR_VALUE_LENGTH));
        }

        // Check for null bytes which could cause truncation
        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Environment variable value cannot contain null bytes');
        }
    }

    /**
     * Build a safe shell export statement.
     *
     * @throws \InvalidArgumentException if name or value is invalid
     */
    public function buildExportStatement(string $name, string $value): string
    {
        $this->validateEnvVarName($name);
        $this->validateEnvVarValue($value);

        // Use escapeshellarg for the value to prevent injection
        return sprintf('export %s=%s', $name, escapeshellarg($value));
    }

    /**
     * Build multiple export statements safely.
     *
     * @param array<string, string> $variables
     *
     * @return string[] Array of export statements
     *
     * @throws \InvalidArgumentException if any name or value is invalid
     */
    public function buildExportStatements(array $variables): array
    {
        $statements = [];

        foreach ($variables as $name => $value) {
            $statements[] = $this->buildExportStatement($name, $value);
        }

        return $statements;
    }

    /**
     * Build a safe environment file content line.
     *
     * @throws \InvalidArgumentException if name or value is invalid
     */
    public function buildEnvFileLine(string $name, string $value): string
    {
        $this->validateEnvVarName($name);
        $this->validateEnvVarValue($value);

        // Quote value if it contains special characters
        $quotedValue = $this->quoteEnvFileValue($value);

        return sprintf('%s=%s', $name, $quotedValue);
    }

    /**
     * Quote a value for use in an environment file.
     * Uses single quotes and escapes embedded single quotes.
     */
    public function quoteEnvFileValue(string $value): string
    {
        // Empty value
        if ($value === '') {
            return "''";
        }

        // If value is safe (no special chars), return as-is
        if (preg_match('/^[A-Za-z0-9_.\/:-]+$/', $value)) {
            return $value;
        }

        // Use single quotes and escape embedded single quotes
        // 'foo'bar' becomes 'foo'\''bar'
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    /**
     * Sanitize a filename to prevent path traversal.
     *
     * @throws \InvalidArgumentException if filename contains dangerous patterns
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Get just the basename (removes directory components)
        $basename = basename($filename);

        // Reject empty result
        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new \InvalidArgumentException('Invalid filename');
        }

        return $basename;
    }

    /**
     * Validate that a path is within an allowed base directory.
     *
     * @throws \InvalidArgumentException if path escapes the base directory
     */
    public function validatePathWithinBase(string $path, string $baseDir): string
    {
        // Resolve real paths
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            throw new \InvalidArgumentException('Base directory does not exist');
        }

        // For files that may not exist yet, resolve parent
        $realPath = realpath($path);
        if ($realPath === false) {
            // File doesn't exist, check parent directory
            $parent = dirname($path);
            $realParent = realpath($parent);
            if ($realParent === false) {
                throw new \InvalidArgumentException('Path parent directory does not exist');
            }

            // Reconstruct with real parent
            $realPath = $realParent . '/' . basename($path);
        }

        // Ensure path starts with base directory
        if (!str_starts_with($realPath . '/', $realBase . '/')) {
            throw new \InvalidArgumentException('Path escapes allowed directory');
        }

        return $realPath;
    }
}
