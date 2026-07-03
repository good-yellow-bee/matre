# Security

This guide covers security features and best practices in MATRE.

## Authentication

### JSON Login

The SPA authenticates via `json_login` — credentials are posted as JSON to `POST /api/login` (route `api_login`), which establishes a session cookie:

```yaml
# config/packages/security.yaml
firewalls:
    main:
        entry_point: App\Security\Http\AuthenticationEntryPoint

        json_login:
            check_path: api_login
            username_path: username
            password_path: password
            success_handler: App\Security\Http\JsonLoginSuccessHandler
            failure_handler: App\Security\Http\JsonLoginFailureHandler
```

```bash
curl -c cookies.txt -X POST http://localhost:8089/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "secret", "_remember_me": false}'
```

The session state is exposed by `GET /api/me`, which returns one of three shapes: anonymous (`{"authenticated": false}`), 2FA challenge pending (`{"authenticated": false, "twoFactorRequired": true}`), or authenticated (user, roles, settings, Allure/noVNC URLs).

The custom entry point (`App\Security\Http\AuthenticationEntryPoint`) returns 401 JSON for API/XHR requests and redirects browsers to `/login`. Logout is `POST /logout` (also accepts GET), returning JSON for XHR callers.

### Password Hashing

Passwords are hashed with bcrypt (cost 12):

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: bcrypt
            cost: 12
```

### Login Throttling

Brute force protection: 5 attempts per minute.

```yaml
firewalls:
    main:
        login_throttling:
            max_attempts: 5
            interval: '1 minute'
```

After 5 failed attempts, the user must wait 1 minute.

### Remember Me

Optional "remember me" functionality (1 week):

```yaml
remember_me:
    secret: '%kernel.secret%'
    lifetime: 604800  # 1 week
    path: /
    always_remember_me: false
```

---

## Two-Factor Authentication

MATRE supports TOTP-based 2FA via scheb/2fa-bundle.

### Configuration

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    security_tokens:
        - Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken
        - Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken

    totp:
        enabled: true
        server_name: 'MATRE'
        issuer: 'MATRE'
        leeway: 1  # Allow +/- 1 period (30 seconds) for clock drift
```

```yaml
# config/packages/security.yaml (firewall)
two_factor:
    auth_form_path: 2fa_login
    check_path: 2fa_login_check
    enable_csrf: true
    csrf_header: X-CSRF-Token
    success_handler: App\Security\Http\TwoFactorSuccessHandler
    failure_handler: App\Security\Http\TwoFactorFailureHandler
    authentication_required_handler: App\Security\Http\TwoFactorRequiredHandler
```

### Routes

The `/2fa` page is rendered by the SPA; only the check endpoint is server-side:

```yaml
# config/routes/scheb_2fa.yaml
2fa_login:
    path: /2fa
    methods: [GET]
    defaults:
        _controller: App\Controller\SpaController::shell

2fa_login_check:
    path: /2fa_check
```

The SPA submits the code as JSON: `POST /2fa_check` with body `{"_auth_code": "123456"}` and an `X-CSRF-Token` header. Custom JSON handlers (`TwoFactorSuccessHandler` / `TwoFactorFailureHandler` / `TwoFactorRequiredHandler`) return JSON instead of redirects.

### User Entity

Users with 2FA enabled have:
- `totpSecret` - The shared secret
- `isTotpEnabled()` - Whether 2FA is active

### Setup Flow

1. User opens `/2fa-setup` (SPA view; forced there by the router when `Settings.enforce2fa` is on and TOTP is not yet enabled)
2. `POST /api/2fa-setup` provisions a secret + QR code
3. User scans QR code with authenticator app
4. `POST /api/2fa-setup/verify` confirms with a valid code and enables TOTP
5. 2FA is now required at login

---

## CSRF Protection

CSRF protection is **stateless** (no server-side token storage):

```yaml
# config/packages/csrf.yaml
framework:
    csrf_protection:
        stateless_token_ids:
            - submit
            - authenticate
            - logout
            - api
            - two_factor
```

### API Requests

`App\EventListener\ApiCsrfListener` validates the `X-CSRF-Token` header on **every mutating** (non-GET/HEAD/OPTIONS) `/api` request except `/api/login`, responding `403` with `{"error": "Invalid CSRF token"}` on failure. Controllers contain no token checks of their own.

With stateless tokens, Symfony accepts any token value of 24+ characters as long as the request is provably same-origin (`Origin`/`Referer` header match, with a double-submit cookie fallback). The SPA client generates one random 48-hex-char token per page load and attaches it automatically; non-browser clients must send both headers:

```bash
curl -b cookies.txt -X POST http://localhost:8089/api/test-runs/42/cancel \
  -H "Origin: http://localhost:8089" \
  -H "X-CSRF-Token: 0123456789abcdef0123456789abcdef"
```

### Two-Factor Check

The `/2fa_check` endpoint uses the same mechanism via the firewall (`enable_csrf: true`, `csrf_header: X-CSRF-Token`).

---

## Access Control

### Role Hierarchy

```yaml
role_hierarchy:
    ROLE_ADMIN: ROLE_USER
```

### Route Protection

```yaml
access_control:
    # Public routes (login, home)
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/2fa-setup, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }
    - { path: ^/$, roles: PUBLIC_ACCESS }

    # API: public bootstrap endpoints, everything else authenticated
    - { path: ^/api/login$, roles: PUBLIC_ACCESS }
    - { path: ^/api/me$, roles: PUBLIC_ACCESS }
    - { path: ^/api, roles: ROLE_USER }

    # Admin panel: user-accessible pages first, everything else requires ROLE_ADMIN
    - { path: ^/admin/(profile|test-history), roles: ROLE_USER }
    - { path: ^/admin/?$, roles: ROLE_USER }
    - { path: ^/admin, roles: ROLE_ADMIN }
```

The SPA's router guards mirror these rules client-side for UX, but authorization is always enforced server-side.

### Controller Protection

```php
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
```

---

## User Impersonation

Admins can impersonate users for debugging:

```yaml
switch_user: true
```

Usage:
```
https://example.com?_switch_user=target_username
```

Exit impersonation:
```
https://example.com?_switch_user=_exit
```

---

## Security Headers

Recommended Nginx headers:

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

### Content Security Policy

No CSP is configured out of the box. If you add one (e.g. via Nginx `add_header Content-Security-Policy ...`), note that the SPA shell contains a small inline script for flash-free theme initialization and fonts are self-hosted — no external CDNs are required.

---

## Input Validation

### Entity Constraints

```php
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\NotBlank]
#[Assert\Length(min: 2, max: 100)]
private string $name;

#[Assert\Email]
private string $email;

#[Assert\Regex(pattern: '/^[a-z0-9-]+$/')]
private string $slug;
```

### API Input Validation

API controllers validate decoded JSON input explicitly and respond `422` with a `field => message` map:

```php
$errors = $this->validateEnvironmentData($data);
if (!empty($errors)) {
    return $this->json(['errors' => $errors], 422);
}
```

---

## SQL Injection Prevention

Always use parameterized queries:

```php
// Good - parameterized
$this->createQueryBuilder('u')
    ->andWhere('u.username = :username')
    ->setParameter('username', $username);

// Bad - string concatenation
// $query = "SELECT * FROM users WHERE username = '$username'";
```

---

## XSS Prevention

Vue automatically escapes interpolated output:

```vue
<span>{{ run.errorMessage }}</span>   <!-- Auto-escaped -->
<div v-html="trusted"></div>          <!-- Avoid v-html for user-controlled data -->
```

Where raw HTML rendering is unavoidable (ANSI-colored test output), the markup is sanitized with DOMPurify before insertion (`assets/spa/views/test-runs/utils/ansi.js`).

---

## Sensitive Data

### Environment Variables

Never commit secrets. Use `.env.local`:

```dotenv
APP_SECRET=your-secret-key
DB_DRIVER=pdo_mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=matre
DB_USER=user
DB_PASS=password
```

### .gitignore

```gitignore
.env.local
.env.*.local
*.pem
*.key
```

---

## Security Scanning

### Composer Audit

```bash
composer audit
```

### CI Security Scan

The `security-scan.yml` workflow runs weekly:
- Composer vulnerability check
- OWASP dependency check
- Psalm taint analysis

---

## Checklist

Security best practices:

1. [ ] Strong APP_SECRET (32+ random bytes)
2. [ ] Database credentials not in code
3. [ ] `X-CSRF-Token` header on all mutating API requests (enforced by `ApiCsrfListener`)
4. [ ] Input validation on all user input
5. [ ] Parameterized database queries
6. [ ] Rate limiting on login
7. [ ] 2FA available for sensitive accounts
8. [ ] Regular security updates
9. [ ] HTTPS in production
10. [ ] Proper file permissions
