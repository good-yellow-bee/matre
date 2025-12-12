# Claude Code Instructions for MATRE

## Project Overview

MATRE (Magento Automated Test Run Environment) is a test automation orchestration platform for Magento 2.

**Stack:** PHP 8.5 | Symfony 7.4 | Doctrine ORM 3 | Vue 3 | Vite | Tailwind CSS | MariaDB 11

**Purpose:** Execute and manage MFTF and Playwright tests across multiple Magento environments with scheduling, reporting, and notifications.

## Code Patterns

### Entity Pattern
- Location: `src/Entity/`
- Use Doctrine attributes (not annotations)
- Table names: `matre_*` prefix
- Include: `createdAt` (immutable), `updatedAt` (nullable with PreUpdate)
- Fluent interface (return `static`)
- Reference: `src/Entity/TestRun.php`, `src/Entity/TestEnvironment.php`

### Test Entities
| Entity | Purpose |
|--------|---------|
| `TestEnvironment` | Target Magento instances with credentials |
| `TestSuite` | Reusable test collections with cron scheduling |
| `TestRun` | Execution instance (pending → running → completed) |
| `TestResult` | Individual test outcomes with screenshots |
| `TestReport` | Generated artifacts (Allure, HTML, JSON) |

### Admin Controller Pattern
- Location: `src/Controller/Admin/`
- Route prefix: `/admin/{plural}`
- Security: `#[IsGranted('ROLE_ADMIN')]`
- Methods: index, new, show, edit, delete, toggleActive
- CSRF: Always validate on POST actions
- Reference: `src/Controller/Admin/TestRunController.php`

### Service Pattern
| Service | Purpose |
|---------|---------|
| `TestRunnerService` | 5-phase pipeline orchestration |
| `MftfExecutorService` | MFTF test execution via Docker |
| `PlaywrightExecutorService` | Playwright test execution |
| `AllureReportService` | Report generation and publishing |
| `ModuleCloneService` | Git operations for test modules |
| `NotificationService` | Slack/email notifications |

### Message Queue Pattern
- Location: `src/Message/` and `src/MessageHandler/`
- Transport: `doctrine://default` (configurable)
- Messages: `TestRunMessage`, `ScheduledTestRunMessage`
- Phases: prepare → execute → report → notify → cleanup

### Vue Island Pattern
- Entry point: `assets/vue/{feature}-app.js`
- Component: `assets/vue/components/{Feature}.vue`
- Composable: `assets/vue/composables/use{Feature}.js`
- Mount: `<div data-vue-island="{feature}" data-api-url="...">`
- Register in `vite.config.mjs` rollupOptions.input
- Reference: `assets/vue/test-run-grid-app.js`

## Docker Commands

```bash
# Start environment
docker-compose up -d --build

# Run Symfony commands
docker-compose exec php php bin/console <command>

# Run tests
docker-compose exec php bin/phpunit

# Frontend dev (HMR)
npm run dev

# View test worker logs
docker-compose logs -f matre_test_worker

# View scheduler logs
docker-compose logs -f matre_scheduler
```

## Testing

Run all tests before committing:
```bash
docker-compose exec php bin/phpunit
docker-compose exec php vendor/bin/phpstan analyse
docker-compose exec php vendor/bin/php-cs-fixer fix --dry-run
```

## Commit Format

```
{issue-number} - Brief description

Example: 31 - Add test environment CRUD
```

Do not include Claude attribution in commits.

## Key Files

| Purpose | File |
|---------|------|
| Docker | `docker-compose.yml` |
| Vite config | `vite.config.mjs` |
| Security | `config/packages/security.yaml` |
| Messenger | `config/packages/messenger.yaml` |
| Routes | `config/routes/` |
| Test Runner | `src/Service/TestRunnerService.php` |
| MFTF Executor | `src/Service/MftfExecutorService.php` |
| Playwright Executor | `src/Service/PlaywrightExecutorService.php` |

## Artifact Storage

- Test artifacts: `var/test-artifacts/{runId}/`
- MFTF results: `var/mftf-results/`
- Playwright results: `var/playwright-results/`
- Allure results: `var/allure-results/`
- Test modules: `var/test-modules/`
