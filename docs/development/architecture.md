# Architecture Overview

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Symfony 8.0, PHP 8.5 |
| ORM | Doctrine ORM 3 |
| Frontend | Vue 3 SPA (Vue Router 4, Pinia), Tailwind CSS 4 |
| Build | Vite 7 |
| Database | MariaDB 11 (local) / MySQL 8 (CI) |
| Search | OpenSearch 2.14 (Magento 2.4.8) |
| Server | Nginx, PHP-FPM |
| Container | Docker |

---

## Performance Optimizations

### Database Query Optimization
- **N+1 Prevention:** Eager loading via `JOIN FETCH` in DQL for related entities
- **Batch Processing:** Chunked operations for large datasets
- **Query Caching:** Symfony cache for frequently accessed data (e.g., env variables with 1hr TTL)

### Real-time Output
- **Streaming Responses:** Test execution logs streamed via `StreamedResponse`
- **SSE (Server-Sent Events):** Live status updates without polling

### Request Deduplication
- **Debounced API Calls:** Frontend prevents duplicate requests during rapid interactions
- **Optimistic UI Updates:** Immediate visual feedback while awaiting server confirmation

---

## Directory Structure

```
matre/
├── assets/                 # Frontend assets
│   └── spa/               # Vue 3 single-page application
│       ├── main.js        # Single Vite entry
│       ├── api/           # JSON API client
│       ├── router/        # Vue Router + guards
│       ├── stores/        # Pinia (auth, toasts)
│       ├── components/    # Shared UI kit + shell
│       ├── composables/   # Shared composables
│       ├── layouts/       # Admin/auth layouts
│       ├── styles/        # Tailwind 4 + design tokens
│       ├── utils/         # Format/debounce/sensitive helpers
│       └── views/         # One directory per feature
├── config/                # Symfony configuration
│   ├── packages/          # Bundle configs
│   └── routes/            # Route definitions
├── docker/                # Docker configs
│   └── nginx/             # Nginx config
├── docs/                  # Documentation
├── migrations/            # Doctrine migrations
├── public/                # Web root
│   └── build/             # Vite output (manifest + hashed assets)
├── src/                   # PHP source
│   ├── Controller/        # SpaController, AdminController, SecurityController
│   │   ├── Admin/         # Binary artifact serving (TestRunController)
│   │   └── Api/           # JSON API controllers
│   ├── Entity/            # Doctrine entities
│   ├── EventListener/     # ApiCsrfListener, ApiRateLimitListener, ...
│   ├── Repository/        # Repositories
│   ├── Security/          # User checker, voters, JSON auth handlers
│   └── Twig/              # ViteExtension (manifest-based asset tags)
├── templates/             # Twig templates
│   ├── emails/            # Email templates
│   └── spa/               # SPA shell (index.html.twig)
├── tests/                 # PHPUnit + Playwright tests
├── docker-compose.yml     # Docker services
├── vite.config.mjs        # Vite configuration
└── Dockerfile             # Multi-stage build
```

---

## Frontend Architecture

The entire admin UI is a single Vue 3 SPA (`assets/spa/`, one Vite entry: `spa`). See [SPA Frontend](spa-frontend.md) for the full guide.

### SPA Shell & Catch-All Routing

`SpaController` serves `templates/spa/index.html.twig` for every GET path that is not `/api/*`, `/build/`, `/uploads/`, `/_profiler`, `/_wdt`, `/2fa_check`, or `/logout` (route priority -100). URLs are unchanged from the Twig era — `/admin/*`, `/login`, `/2fa`, `/2fa-setup` all load the shell and Vue Router takes over client-side.

### Vite Integration

`App\Twig\ViteExtension` provides the asset tag helpers and resolves hashed filenames from `public/build/.vite/manifest.json` (produced by `npm run build`):

```twig
{{ vite_entry_link_tags('spa') }}
{{ vite_entry_script_tags('spa') }}
```

---

## API Endpoints

The SPA talks exclusively to the JSON API under `/api` (controllers in `src/Controller/Api/`):

| Resource | Base Path |
|----------|-----------|
| Auth & session bootstrap | `/api/login`, `/api/me` |
| Test runs (create/cancel/retry/retry-failed/resend-notification/live-output/steps/output) | `/api/test-runs` |
| Test suites | `/api/test-suites` |
| Test environments (incl. per-env variables) | `/api/test-environments` |
| Global env variables | `/api/env-variables` |
| Users | `/api/users` |
| Cron jobs | `/api/cron-jobs` |
| Audit logs | `/api/audit-logs` |
| Notification templates | `/api/notification-templates` |
| Settings | `/api/settings` |
| Dashboard stats | `/api/dashboard` |
| 2FA setup | `/api/2fa-setup` |
| Test history | `/api/test-history` |
| Test discovery | `/api/test-discovery` |
| Profile (notification preferences) | `/api/profile` |

All API endpoints require `ROLE_USER` or higher (`/api/login` and `/api/me` are public); admin resources require `ROLE_ADMIN`. Binary artifacts (screenshots, HTML dumps) are served outside `/api` at `/admin/test-runs/{id}/artifacts/{filename}`.

---

## Admin Panel

### URL Structure

Client-side routes (Vue Router), served by the SPA shell:

- `/admin` - Dashboard
- `/admin/test-runs`, `/admin/test-history` - Test execution
- `/admin/test-environments`, `/admin/test-suites`, `/admin/env-variables` - Test configuration
- `/admin/users`, `/admin/notification-templates` - Administration
- `/admin/settings`, `/admin/cron-jobs`, `/admin/audit-logs` - System
- `/admin/profile/notifications` - Per-user preferences

### Controller Pattern

Each admin feature pairs a JSON API controller with SPA views (see [Admin CRUD](admin-crud.md)):
- `list()` / `grid()` - Collection endpoints (GET)
- `get()` / `show()` - Single resource (GET)
- `create()` - POST with JSON body, 422 + `errors` map on validation failure
- `update()` - PUT
- `delete()` - DELETE
- `toggleActive()` - POST status toggle

---

## Security

### Authentication
- JSON login (`json_login`) at `POST /api/login` with login throttling (5 attempts/minute)
- Session bootstrap via `GET /api/me` (anonymous / 2FA-pending / authenticated)
- Two-factor authentication (TOTP) — JSON challenge at `POST /2fa_check`
- Remember me (1 week)
- JSON-aware entry point: 401 JSON for API requests, redirect to `/login` for browsers

### Authorization
- `ROLE_USER` - Dashboard, test history, profile
- `ROLE_ADMIN` - Everything else under `/admin` and the admin API resources
- Enforced server-side via `access_control` + `#[IsGranted]`; mirrored client-side by router guards

### CSRF Protection

Stateless CSRF (`config/packages/csrf.yaml`). `App\EventListener\ApiCsrfListener` validates the `X-CSRF-Token` header on every mutating `/api` request (except `/api/login`); the SPA client sends a random ≥24-char token and Symfony asserts same-origin. Controllers do not validate tokens themselves.

---

## Database

### Naming Conventions
- Tables: `matre_*` prefix (e.g., `matre_users`)
- Columns: snake_case
- Entities: PascalCase

### Entities
- `User` - Authentication and profile (2FA, roles, notification preferences)
- `Settings` - System configuration (singleton)
- `TestEnvironment`, `TestSuite`, `TestRun`, `TestResult`, `TestReport` - Test orchestration
- `GlobalEnvVariable` - Shared env vars across environments
- `CronJob` - Scheduled commands
- `NotificationTemplate` - Customizable Slack/email templates
- `AuditLog` - Entity change tracking
- `PasswordResetRequest` - Token-based password reset

### Migrations
```bash
# Generate migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate
```
