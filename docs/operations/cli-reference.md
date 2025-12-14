# CLI Reference

All MATRE commands. Click links for detailed usage.

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

---

## Validation

| Command | Purpose |
|---------|---------|
| `app:validate-ssl-config` | Check SSL/Traefik configuration for production |
