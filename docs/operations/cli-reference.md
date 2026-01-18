# CLI Reference

All MATRE commands. Click links for detailed usage.

---

## Management Scripts

Wrapper scripts for common operations:

| Command | Description |
|---------|-------------|
| `./local.sh start` | Start dev environment with migrations |
| `./local.sh stop` | Stop all containers |
| `./local.sh logs [svc]` | Follow logs |
| `./local.sh shell` | Open PHP shell |
| `./local.sh console <cmd>` | Run Symfony console |
| `./local.sh test` | Run PHPUnit |
| `./local.sh phpstan` | Run PHPStan |
| `./local.sh fix` | Fix code style |
| `./prod.sh start` | Start production |
| `./prod.sh update` | Update production (pull, recreate, migrate) |
| `./prod.sh recreate <svc>` | Recreate single service |
| `./prod.sh status` | Show container status |

---

## Test Operations

| Command | Purpose | Guide |
|---------|---------|-------|
| `app:test:run` | Execute MFTF/Playwright tests | [Test Execution](test-execution.md#cli) |
| `app:test:check-magento` | Pre-flight health check | [Monitoring](monitoring.md#pre-flight) |
| `app:test:cleanup` | Remove old artifacts/reports | [Allure Reports](allure-reports.md#cleanup) |

---

## Scheduling

| Command | Purpose | Guide |
|---------|---------|-------|
| `app:cron:list` | Show scheduled jobs | [Scheduling](scheduling.md#list-jobs) |
| `app:cron:run {id}` | Run job manually | [Scheduling](scheduling.md#manual-run) |
| `app:cron:install` | Add to system crontab | [Scheduling](scheduling.md#system-install) |
| `app:cron:remove` | Remove from crontab | [Scheduling](scheduling.md#system-install) |

---

## Setup

See [Installation Guide](../getting-started/installation.md) for full setup instructions.

| Command | Purpose |
|---------|---------|
| `app:database:setup` | Initialize database (drop, create, migrate, fixtures) |
| `app:create-admin` | Create admin user (interactive) |
| `app:create-user` | Create regular user (interactive) |
| `app:load-fixtures` | Load sample data |

---

## Data Import

| Command | Purpose |
|---------|---------|
| `app:env:import` | Import .env variables with MFTF usage analysis |
| `app:test:import-env` | Bulk import test environments from .env files |

### Environment Variable Import (`app:env:import`)

Import variables from `.env.{environment}` files with automatic MFTF test usage analysis.

```bash
# Clone module and select environment interactively
php bin/console app:env:import --clone

# Import for specific environment (creates env-specific variables)
php bin/console app:env:import stage-us --clone

# Import as global variables (apply to ALL environments)
php bin/console app:env:import stage-us --clone --global

# Preview without saving
php bin/console app:env:import stage-us --dry-run

# Overwrite existing variables
php bin/console app:env:import stage-us --overwrite
```

**Options:**
| Option | Short | Description |
|--------|-------|-------------|
| `--clone` | `-c` | Clone fresh test module from TEST_MODULE_REPO |
| `--global` | `-g` | Import as global variables (apply to all environments) |
| `--dry-run` | | Preview without saving changes |
| `--overwrite` | | Update existing variables |

**Merge Logic:**
- Same name + same value across environments → merges into single record with multiple environments
- Same name + different values → creates separate records
- `--global` flag → sets `environments = null` (applies to all)

---

## Validation

| Command | Purpose |
|---------|---------|
| `app:validate-ssl-config` | Check SSL/Traefik configuration for production |
