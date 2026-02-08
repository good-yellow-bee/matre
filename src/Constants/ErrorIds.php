<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Error ID constants for Sentry tracking and log correlation.
 */
class ErrorIds
{
    // Security (SEC)
    public const CREDENTIAL_DECRYPTION_FAILED = 'SEC001';
    public const CREDENTIAL_TAMPERING_DETECTED = 'SEC002';
    public const INVALID_ENV_VARIABLE = 'SEC003';
    public const SHELL_INJECTION_ATTEMPT = 'SEC004';

    // File Operations (FILE)
    public const ARTIFACT_PATH_UNRESOLVABLE = 'FILE001';
    public const ARTIFACT_CLEANUP_FAILED = 'FILE002';
    public const ARTIFACT_CLEANUP_SYSTEMIC = 'FILE003';
    public const FLYSYSTEM_WRITE_FAILED = 'FILE004';
    public const FILE_UPLOAD_RUNTIME_ERROR = 'FILE005';

    // Email (EMAIL)
    public const PASSWORD_RESET_EMAIL_FAILED = 'EMAIL001';
    public const PASSWORD_CHANGED_EMAIL_FAILED = 'EMAIL002';
    public const NOTIFICATION_EMAIL_FAILED = 'EMAIL003';

    // Test Execution (TEST)
    public const MFTF_ENV_VAR_INVALID = 'TEST001';
    public const PLAYWRIGHT_ENV_VAR_INVALID = 'TEST002';
    public const TEST_EXECUTION_FAILED = 'TEST003';
    public const ARTIFACT_COLLECTION_FAILED = 'TEST004';
}
