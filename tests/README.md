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
└── e2e/                      # Playwright browser tests
    ├── fixtures/             # Auth setup (storageState)
    ├── pages/                # Page Object Model classes
    ├── utils/                # Helpers (Vue hydration, waits)
    └── *.spec.ts             # Test specs
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

```bash
# Requires running app (Docker containers up)
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

**Functional tests** — extend `WebTestCase`, use real DB:
```php
class MyControllerTest extends WebTestCase
{
    use ApiTestTrait; // for API tests

    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/my-page');
        $this->assertResponseIsSuccessful();
    }
}
```

### Playwright Patterns

**Page Object Model** — extend `BasePage`:
```typescript
import { BasePage } from './BasePage';

export class MyPage extends BasePage {
  readonly path = '/admin/my-page';
  readonly heading: Locator;

  constructor(page: Page) {
    super(page);
    this.heading = page.locator('h1');
  }
}
```

**Spec files** — use authenticated storageState (auto via setup project):
```typescript
import { test, expect } from '@playwright/test';

test.describe('My Feature', () => {
  test('page loads', async ({ page }) => {
    await page.goto('/admin/my-page');
    await expect(page.locator('h1')).toBeVisible();
  });
});
```

**Avoiding Symfony debug toolbar conflicts** — scope locators to `main`:
```typescript
const main = page.locator('main');
await expect(main.locator('table').first()).toBeVisible();
```

## CI/CD

Tests run automatically on push/PR via `.github/workflows/symfony-ci.yml`:

| Job | Tests |
|-----|-------|
| `phpunit-tests` | Smoke + Unit + Functional + Integration with coverage |
| `playwright-e2e` | All E2E specs (runs after PHPUnit passes) |

Playwright test reports uploaded as artifacts on every run (14-day retention).

## Test Data

Fixtures loaded in test environment:
- `UserFixtures` — admin user (`admin`/`admin123`)
- `SettingsFixtures` — default site settings
- `TestFixtures` — sample environments and suites (dev/test only)
