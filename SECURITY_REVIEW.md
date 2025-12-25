# MATRE Security & Performance Review

**Date:** 2025-12-25
**Reviewer:** Security Audit
**Codebase Version:** Commit 2d24934

---

## Executive Summary

This review identified **2 critical**, **3 high**, **4 medium**, and **4 low** severity security issues. The codebase demonstrates good security practices in many areas (SQL injection prevention, XSS protection, CSRF validation), but has significant command injection vulnerabilities in the test execution services that require immediate attention.

---

## Critical Severity Issues

### 1. Command Injection in PlaywrightExecutorService

**Location:** `src/Service/PlaywrightExecutorService.php:97-99`

**Description:** Environment variables from the database are directly interpolated into shell commands without proper escaping.

```php
foreach ($globalVars as $key => $value) {
    $parts[] = sprintf('export %s="%s"', $key, $value);
}
```

**Attack Vector:** An attacker with admin access who can modify GlobalEnvVariable values could inject shell commands:
- Setting value to `"; rm -rf /; "` would execute arbitrary commands
- Setting value to `$(cat /etc/passwd)` would exfiltrate data

**Impact:** Remote Code Execution on the Docker container running tests

**Recommendation:**
1. Use `escapeshellarg()` for all values inserted into shell commands
2. Validate environment variable names match `^[A-Za-z_][A-Za-z0-9_]*$`
3. Consider using environment variable injection via Docker's `-e` flag instead of shell export

---

### 2. Command Injection in MftfExecutorService

**Location:** `src/Service/MftfExecutorService.php:136-140`

**Description:** Global variables are inserted into shell commands. While `quoteEnvValue()` attempts quoting, it's insufficient for shell command context.

```php
$globalContent .= sprintf("%s=%s\n", $key, $quotedValue);
// Later used in: echo $globalContent > file
```

**Attack Vector:** Special characters in variable names or values can break out of the intended context.

**Impact:** Remote Code Execution on the Magento container

**Recommendation:**
1. Validate variable names strictly: `/^[A-Z][A-Z0-9_]*$/`
2. Use `base64_encode()` for values and decode in the container
3. Write env file using PHP file operations instead of shell echo

---

## High Severity Issues

### 3. Sensitive Credentials Stored in Plain Text

**Location:** `src/Entity/TestEnvironment.php:70-71`

**Description:** Admin passwords for test environments are stored as plain strings in the database without encryption.

```php
#[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
private ?string $adminPassword = null;
```

**Additionally:** These passwords are exposed in:
- `templates/admin/test_environment/show.html.twig:76` - displayed in UI
- `buildEnvContent()` method output
- Log files when test execution fails

**Impact:**
- Database compromise exposes all Magento admin credentials
- Accidental exposure through logs or UI

**Recommendation:**
1. Encrypt credentials at rest using Symfony's secrets management
2. Mask passwords in UI (show only `********`)
3. Never log credential values

---

### 4. Path Traversal Risk in Artifact Download

**Location:** `src/Controller/Admin/TestRunController.php:104-128`

**Description:** The artifact download endpoint accepts a filename from user input. While extensions are whitelisted, path traversal sequences may not be fully validated.

```php
#[Route('/{id}/artifacts/{filename}', ...)]
public function artifact(TestRun $run, string $filename): Response
```

**Attack Vector:** Requests like `../../../etc/passwd.html` could potentially access files outside the artifact directory if `getArtifactFilePath()` doesn't properly canonicalize paths.

**Impact:** Local File Disclosure

**Recommendation:**
1. Verify `ArtifactCollectorService::getArtifactFilePath()` uses `realpath()` and validates the result is within the artifact directory
2. Reject any filename containing `..` or absolute paths
3. Use basename extraction to ensure only the filename is used

---

### 5. 2FA Bypass via API Endpoints

**Location:** `src/EventSubscriber/TwoFactorEnforcementSubscriber.php:34-37`

**Description:** API endpoints are explicitly excluded from 2FA enforcement:

```php
private const SKIP_PATH_PREFIXES = [
    '/api/',      // API endpoints exempt
    // ...
];
```

**Impact:** An attacker with stolen credentials can bypass 2FA completely by using the API instead of the web interface.

**Recommendation:**
1. Require 2FA for sensitive API endpoints (test execution, user management)
2. Implement API-specific 2FA verification (TOTP code in header)
3. Consider API key authentication separate from user sessions

---

## Medium Severity Issues

### 6. TOTP Secrets Stored Unencrypted

**Location:** `src/Entity/User.php:98-99`

**Description:** TOTP secrets are stored as plain strings in the database.

```php
#[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
private ?string $totpSecret = null;
```

**Impact:** Database compromise allows attackers to generate valid 2FA codes

**Recommendation:** Encrypt TOTP secrets using application-level encryption

---

### 7. Password Reset Tokens Not Hashed

**Location:** `src/Service/PasswordResetService.php`

**Description:** Password reset tokens are stored in plain text in the database rather than being hashed.

**Impact:** Database read access allows using tokens directly

**Recommendation:** Store `hash('sha256', $token)` in database, compare hashes on validation

---

### 8. Admin Impersonation Without Audit Trail

**Location:** `config/packages/security.yaml:57`

**Description:** Switch user functionality is enabled but there's no visible audit logging of impersonation events.

```yaml
switch_user: true
```

**Impact:** Admin abuse may go undetected

**Recommendation:**
1. Add event listener to log all switch_user events
2. Display banner when user is being impersonated
3. Consider restricting to specific admin roles

---

### 9. Environment Variable Value Exposure in Templates

**Location:** `templates/admin/test_environment/show.html.twig:76`

**Description:** Environment variable values (which may contain secrets) are displayed in the admin UI.

```twig
<td class="text-muted small">{{ data.value }}</td>
```

**Impact:** Sensitive values visible to any admin user

**Recommendation:**
1. Mask values for variables containing PASSWORD, SECRET, KEY, TOKEN
2. Require explicit "show value" click with additional confirmation

---

## Low Severity Issues

### 10. Rate Limiting Inconsistency

**Locations:**
- `config/packages/rate_limiter.yaml` - defines limiters
- `src/EventListener/ApiRateLimitListener.php` - applies to `/api/`

**Description:** Rate limiting is well-implemented for APIs but login throttling may need adjustment for distributed attacks (uses IP-based limiting only).

**Recommendation:** Consider adding account-based rate limiting in addition to IP-based

---

### 11. Remember Me Cookie Duration

**Location:** `config/packages/security.yaml:51`

**Description:** Remember me cookies last 1 week, which may be excessive for security-sensitive applications.

```yaml
lifetime: 604800 # 1 week in seconds
```

**Recommendation:** Consider reducing to 24-48 hours for admin users

---

### 12. Missing Security Headers

**Not Found:** No explicit configuration for security headers like:
- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options

**Recommendation:** Add NelmioSecurityBundle or configure headers in web server

---

### 13. Git Credentials in Memory

**Location:** `src/Service/ModuleCloneService.php:236`

**Description:** Git credentials are embedded in URLs which may persist in process memory or logs.

**Recommendation:** Use Git credential helpers or SSH keys instead of URL-embedded credentials

---

## Performance Analysis

### Positive Findings

1. **N+1 Query Prevention** - Well implemented
   - `TestRunRepository::findPaginatedWithRelations()` uses JOINs
   - `TestResultRepository::getResultCountsForRuns()` batch fetches counts
   - Eager loading properly configured

2. **Production Caching** - Properly configured
   - Query cache and result cache enabled in production
   - Using cache pool adapters

3. **Output Streaming** - Good memory management
   - Test output streamed to files, not buffered in memory
   - Large files truncated to 100KB for display

4. **Pagination** - Implemented consistently across API endpoints

### Recommendations

1. **Add Database Indexes**
   ```sql
   CREATE INDEX idx_test_run_status ON matre_test_runs(status);
   CREATE INDEX idx_test_run_env_status ON matre_test_runs(environment_id, status);
   CREATE INDEX idx_test_result_run_status ON matre_test_results(test_run_id, status);
   ```

2. **Consider Connection Pooling** for high-concurrency scenarios

3. **Add Query Result Caching** for frequently accessed, rarely-changing data:
   - Settings (singleton)
   - Active environments list
   - Test suites list

---

## Security Strengths

The codebase demonstrates good security practices in several areas:

1. **SQL Injection Prevention** - All queries use parameterized statements via Doctrine ORM
2. **XSS Protection** - Twig auto-escaping enabled, HTML sanitizer properly configured
3. **CSRF Protection** - Tokens validated on all state-changing operations
4. **Input Validation** - Strong validation on User API, role whitelist prevents privilege escalation
5. **File Upload Security** - MIME type verification, content validation, size limits
6. **Password Security** - Bcrypt with cost 12, strong password policy enforced
7. **Rate Limiting** - Applied to login, API, and sensitive endpoints
8. **Email Enumeration Prevention** - Password reset always returns success

---

## Priority Remediation

| Priority | Issue | Effort |
|----------|-------|--------|
| P0 | Command Injection in Playwright/MFTF Executors | Medium |
| P1 | Credential Encryption at Rest | Medium |
| P1 | Path Traversal Validation | Low |
| P2 | 2FA for API Endpoints | High |
| P2 | TOTP Secret Encryption | Low |
| P3 | Password Reset Token Hashing | Low |
| P3 | Security Headers | Low |
| P3 | Audit Logging for Impersonation | Medium |

---

## Conclusion

The MATRE codebase has a solid security foundation but requires immediate attention to the command injection vulnerabilities in the test execution services. The critical issues should be addressed before any production deployment. The medium and low severity issues should be included in the security backlog for the next development cycle.
