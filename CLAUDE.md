# Claude Code Instructions for MATRE

## Project Overview

MATRE (Magento Automated Test Run Environment) is a test automation orchestration platform for Magento 2.

**Stack:** PHP 8.5 | Symfony 7.4 | Doctrine ORM 3 | Vue 3 | Vite | Tailwind CSS | MariaDB 11

**Purpose:** Execute and manage MFTF and Playwright tests across multiple Magento environments with scheduling, reporting, and notifications.

## Entity Pattern
- Location: `src/Entity/`
- Doctrine attributes (not annotations)
- Table names: `matre_*` prefix
- Timestamps: `createdAt` (immutable), `updatedAt` (nullable with PreUpdate)
- Fluent interface (return `static`)

| Entity | Purpose |
|--------|---------|
| `User` | Auth + 2FA (TOTP), roles, implements UserInterface/TwoFactorInterface |
| `Settings` | Singleton config (site name, enforce2fa, headless mode) |
| `TestEnvironment` | Target Magento instances with credentials |
| `TestSuite` | Reusable test collections with cron scheduling |
| `TestRun` | Execution instance (pending → running → completed) |
| `TestResult` | Individual test outcomes with screenshots |
| `TestReport` | Generated artifacts (Allure, HTML, JSON) |
| `GlobalEnvVariable` | Shared env vars across test environments |
| `CronJob` | Scheduled commands with status tracking |
| `PasswordResetRequest` | Token-based reset with expiration |

## Service Pattern

| Service | Purpose |
|---------|---------|
| `TestRunnerService` | 5-phase pipeline orchestration |
| `MftfExecutorService` | MFTF test execution via Docker |
| `PlaywrightExecutorService` | Playwright test execution |
| `AllureReportService` | Report generation and publishing |
| `ModuleCloneService` | Git operations for test modules |
| `NotificationService` | Slack/email notifications |
| `PasswordResetService` | Reset workflow with token validation |
| `EmailService` | Templated emails (welcome, reset, notifications) |
| `AdminConfigService` | Admin menu/config, role-based access |
| `ArtifactCollectorService` | Test screenshots/HTML collection |
| `FileUploadService` | Flysystem uploads with MIME validation |
| `EnvVariableAnalyzerService` | Parse .env files and analyze MFTF test variable usage |

## Security Pattern

**Authentication:**
- Bcrypt (cost 12), login throttling (5/min), remember me (1 week)
- 2FA: SchebTwoFactorBundle + TOTP, optional enforcement via Settings.enforce2fa
- Flow: login → 2fa_login_check (if enabled) → IS_AUTHENTICATED_FULLY

**Key Files:**
- `config/packages/security.yaml` - Auth rules, role hierarchy
- `config/packages/scheb_2fa.yaml` - TOTP config
- `src/Controller/TwoFactorSetupController.php` - QR code + setup
- `src/EventSubscriber/TwoFactorEnforcementSubscriber.php` - Global 2FA enforcement

## CLI Commands

| Command | Purpose |
|---------|---------|
| `app:create-admin` | Create admin user |
| `app:create-user` | Create regular user |
| `app:test:run` | Run tests (filter, suite, sync/async) |
| `app:cron:run` | Execute cron job manually |
| `app:cron:list` | List scheduled jobs |
| `app:cron:install` | Install crontab entry |
| `app:validate-ssl-config` | Check SSL/Let's Encrypt config |
| `app:check-magento` | Validate Magento connectivity |
| `app:cleanup-tests` | Clean old artifacts |
| `app:import-environments` | Bulk import test targets |
| `app:env:import` | Import .env vars from TEST_MODULE_REPO with test usage analysis |

## Message Queue Pattern

**Queues:** `async`, `test_runner`, `scheduler_test_runner`

| Message | Queue | Retries |
|---------|-------|---------|
| Email/Chat/SMS | async | 3 |
| TestRunMessage | test_runner | 2 |
| ScheduledTestRunMessage | scheduler_test_runner | 2 |

**Config:** `config/packages/messenger.yaml`

## Custom Validators

| Validator | Purpose |
|-----------|---------|
| `ValidCronExpression` | Cron syntax (dragonmantank/cron-expression) |
| `ValidConsoleCommand` | Verifies command exists in kernel |

## Admin Controller Pattern
- Location: `src/Controller/Admin/`
- Route prefix: `/admin/{plural}`
- Security: `#[IsGranted('ROLE_ADMIN')]`
- Methods: index, new, show, edit, delete, toggleActive
- CSRF: Always validate on POST actions

## Vue Island Pattern
- Entry: `assets/vue/{feature}-app.js`
- Component: `assets/vue/components/{Feature}.vue`
- Composable: `assets/vue/composables/use{Feature}.js`
- Mount: `<div data-vue-island="{feature}" data-api-url="...">`
- Register in `vite.config.mjs` rollupOptions.input

## Docker Commands

```bash
docker-compose up -d --build           # Start environment
docker-compose exec php php bin/console <cmd>  # Symfony commands
docker-compose exec php bin/phpunit    # Run tests
npm run dev                            # Frontend HMR
docker-compose logs -f matre_test_worker  # Test worker logs
```

## Testing

```bash
docker-compose exec php bin/phpunit
docker-compose exec php vendor/bin/phpstan analyse
docker-compose exec php vendor/bin/php-cs-fixer fix --dry-run
```

## Commit Format

```
{issue-number} - Brief description
```

Do not include Claude attribution in commits.

## Key Files

| Purpose | File |
|---------|------|
| Docker | `docker-compose.yml` |
| Vite | `vite.config.mjs` |
| Security | `config/packages/security.yaml` |
| 2FA | `config/packages/scheb_2fa.yaml` |
| Messenger | `config/packages/messenger.yaml` |
| Test Runner | `src/Service/TestRunnerService.php` |
| MFTF Executor | `src/Service/MftfExecutorService.php` |
| SSL Validation | `src/Command/ValidateSslConfigCommand.php` |
| Traefik SSL | `docker/traefik/traefik.yml` |

## Artifact Storage

- Test artifacts: `var/test-artifacts/{runId}/`
- MFTF results: `var/mftf-results/`
- Playwright results: `var/playwright-results/`
- Allure results: `var/allure-results/`
- Test modules: `var/test-modules/`

## Dev Mode (Local Module Development)

Skip git clone on each test run by using a local module directory:

```env
# .env
DEV_MODULE_PATH=./test-module  # Relative or absolute path
```

**Behavior:**
- When set: Creates symlink to local module (instant, live edits visible)
- When empty: Normal git clone from `TEST_MODULE_REPO`
- If path invalid: Fails with clear error (no silent fallback)

**Usage:**
1. Clone/place your module in project root (e.g., `./test-module/`)
2. Set `DEV_MODULE_PATH=./test-module` in `.env`
3. Run tests → uses local module, no git clone
4. Edit module files → changes reflected immediately
