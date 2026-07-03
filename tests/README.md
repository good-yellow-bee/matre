# MATRE Test Suite

## Structure

```
tests/
├── Smoke/                    # Application availability tests
├── Unit/                     # Isolated unit tests (no DB)
│   ├── Entity/               # Entity logic tests
│   └── Service/              # Service tests with mocks
├── Functional/               # HTTP-level tests (with DB)
│   ├── Controller/Admin/     # Admin controller tests
│   ├── Controller/Api/       # API endpoint tests
│   └── Traits/               # Shared test helpers
├── Integration/              # Multi-service workflow tests
└── e2e/                      # Playwright browser tests (SPA)
    ├── .auth/                # Saved storageState (admin.json, gitignored)
    ├── fixtures/             # auth.setup.ts (login + storageState capture)
    └── *.spec.ts             # 10 specs / 27 tests
```

## Running Tests

### PHPUnit (via Docker)

```bash
# All tests
./local.sh test

# By suite
docker exec matre_php vendor/bin/phpunit --testsuite="Smoke Tests"
docker exec matre_php vendor/bin/phpunit --testsuite="Unit Tests"
docker exec matre_php vendor/bin/phpunit --testsuite="Functional Tests"
docker exec matre_php vendor/bin/phpunit --testsuite="Integration Tests"

# Single test
docker exec matre_php vendor/bin/phpunit tests/Unit/Entity/UserTest.php

# With coverage
docker exec matre_php vendor/bin/phpunit --coverage-text
```

### Playwright E2E

10 specs / 27 tests covering the Vue SPA: auth (login/logout/2FA-free flow), navigation, dashboard, and every admin page (test runs incl. detail/artifacts/steps modal, environments, suites, env variables, users, settings, system pages).

```bash
# Requires running app (Docker containers up) with built frontend (npm run build)
npm run test:e2e

# Headed mode (see browser)
npm run test:e2e:headed

# Debug mode (step through)
npm run test:e2e:debug

# UI mode (interactive)
npm run test:e2e:ui

# Single spec
BASE_URL=http://localhost:8089 npx playwright test tests/e2e/auth.spec.ts

# With specific base URL
BASE_URL=https://matre.local npx playwright test
```

**Prerequisites:** `npm install && npx playwright install chromium`

**How auth works:** a `setup` project (`tests/e2e/fixtures/auth.setup.ts`) logs in once through the real login form and saves the session to `tests/e2e/.auth/admin.json`; all specs run with that `storageState`, so individual tests never log in. Credentials default to `admin`/`admin123` and can be overridden via `E2E_ADMIN_USER` / `E2E_ADMIN_PASS`.

**Data-agnostic specs:** tests assert against whatever data the target instance has (e.g. "newest failed run via the status filter" instead of a hardcoded run ID, empty-state *or* rows). Locally they run against your real database; in CI a fixture (`TestRunFixtures`) seeds one failed run so run-detail specs always have a target. `BASE_URL` defaults to `http://localhost:8089`.

## Writing Tests

### PHPUnit Patterns

**Unit tests** — mock all dependencies:
```php
class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        $dep = $this->createMock(SomeDependency::class);
        $service = new MyService($dep);
        $this->assertSame('expected', $service->doThing());
    }
}
```

**Functional tests** — extend `WebTestCase` with `ApiTestTrait`, use real DB:
```php
class MyApiControllerTest extends WebTestCase
{
    use ApiTestTrait; // loginAsUser/loginAsAdmin, jsonRequest, assertJsonResponse, ...

    public function testListRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/my-resources');
        $this->assertApiUnauthenticated($client);
    }

    public function testList(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', '/api/my-resources');
        $data = $this->assertJsonResponse($response, 200);
    }
}
```

`jsonRequest()` sends a valid `X-CSRF-Token` header automatically; use `self::INVALID_CSRF_HEADERS` to assert CSRF rejection. Tests under `tests/Functional/Controller/Admin/` cover the SPA shell + binary artifact routes (auth redirects, role checks).

### Playwright Patterns

**Spec files** — flat specs (no page objects), authenticated storageState applied automatically via the setup project:
```typescript
import { test, expect } from '@playwright/test';

test.describe('my feature', () => {
  test('page loads', async ({ page }) => {
    await page.goto('/admin/my-page');
    await expect(page.locator('h1')).toHaveText('My Page');
  });
});
```

**Wait on the API, not on timeouts** — the SPA loads data after navigation; key on the network response when interacting with filters/forms:
```typescript
const filtered = page.waitForResponse(
  (response) => response.url().includes('/api/test-runs') && response.url().includes('status=failed'),
);
await page.locator('#filter-status').selectOption('failed');
await filtered;
```

**Stay data-agnostic** — accept any valid dataset instead of asserting exact rows:
```typescript
await expect
  .poll(async () =>
    (await page.getByText('No environments yet').count())
    + (await page.locator('.badge').count()))
  .toBeGreaterThan(0);
```

## CI/CD

Tests run automatically on push/PR via `.github/workflows/symfony-ci.yml`:

| Job | Tests |
|-----|-------|
| `phpunit-tests` | Smoke + Unit + Functional + Integration with coverage |
| `playwright-e2e` | All E2E specs (runs after PHPUnit passes) |

The `playwright-e2e` job builds the frontend (`npm run build`), migrates a MySQL 8 service database, loads fixtures (`doctrine:fixtures:load --append`), serves the app with PHP's built-in server, and runs `npx playwright test` with `BASE_URL=http://127.0.0.1:8080` (1 worker, 2 retries). Playwright reports are uploaded as artifacts on every run (14-day retention). `.github/workflows/ci-quality.yml` additionally runs PHPUnit with a coverage threshold, PHPStan, and PHP-CS-Fixer (`--config=.php-cs-fixer.dist.php`).

## Test Data

Fixtures loaded in test environment (`doctrine:fixtures:load --append`):
- `UserFixtures` — admin user (`admin`/`admin123`)
- `SettingsFixtures` — default site settings
- `TestFixtures` — sample environments and suites (dev/test only)
- `TestRunFixtures` — one completed-failed MFTF run with a failed result on the `staging` environment, so e2e run-list/detail specs always have data (dev/test only)
