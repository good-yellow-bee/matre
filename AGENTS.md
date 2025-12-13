# Repository Guidelines

## Project Structure & Module Organization

- `src/`: Symfony 7.4 backend (`src/Entity`, `src/Service`, `src/Controller`, `src/Message*`).
- `assets/`: Frontend assets (Vite + Vue + Tailwind); Vue islands live in `assets/vue/`.
- `templates/`: Twig templates (admin UI, emails, auth).
- `config/`: Symfony configuration (`config/packages/`, `config/routes/`).
- `migrations/`: Doctrine migrations.
- `tests/`: PHPUnit tests (`tests/Smoke`, `tests/Unit`, `tests/Functional`).
- `var/`: Runtime + artifacts (`var/*-results`, `var/allure-*`, `var/test-modules`, `var/test-artifacts/{runId}/`).
- `docker/` + `docker-compose.yml`: Local stack (PHP app, DB, Nginx, workers, Allure, Selenium, etc.).
- `docs/`: Product and engineering documentation.

## Build, Test, and Development Commands

- `docker-compose up -d --build`: Start the full local stack.
- `docker-compose exec php composer install`: Install PHP dependencies.
- `docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction`: Apply DB migrations.
- `docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction`: Load dev fixtures.
- `npm ci`: Install frontend dependencies.
- `npm run dev`: Frontend HMR via Vite (run on host machine).
- `npm run build`: Production asset build to `public/build/`.
- `docker-compose logs -f matre_test_worker`: Tail async test runner worker logs.

## Coding Style & Naming Conventions

- PHP: `declare(strict_types=1);`, typed properties, 4-space indentation.
- Doctrine entities: attributes (not annotations); tables use `matre_*`; use `createdAt`/`updatedAt` and fluent setters (`return static`).
- Formatting: PHP-CS-Fixer config is in `.php-cs-fixer.php` (PSR-12 + Symfony rules). Run:
  - `docker-compose exec php vendor/bin/php-cs-fixer fix`
- Static analysis: PHPStan config in `phpstan.neon`. Run:
  - `docker-compose exec php vendor/bin/phpstan analyse`

## Testing Guidelines

- Framework: PHPUnit (config: `phpunit.dist.xml`).
- Run all tests: `docker-compose exec php bin/phpunit`
- Run a suite: `docker-compose exec php bin/phpunit --testsuite "Unit Tests"`
- Keep tests close to intent: unit tests in `tests/Unit`, request/behavior tests in `tests/Functional`, smoke checks in `tests/Smoke`.

## Commit & Pull Request Guidelines

- Commits: prefer `{issue-number} - Brief description` (short, imperative). Don’t add tool/agent attribution in commit messages.
- PRs: include a clear summary, testing notes (commands run), and screenshots for admin/UI changes. Don’t commit secrets; use `.env.example` for new config keys.

## Security & Configuration Tips

- Keep real secrets in `.env` (gitignored); add new keys/defaults to `.env.example`.
- Re-run migrations after pulling schema changes: `docker-compose exec php php bin/console doctrine:migrations:migrate`.
- Useful config entrypoints: `config/packages/security.yaml`, `config/packages/scheb_2fa.yaml`, `config/packages/messenger.yaml`.

## Agent-Specific Instructions

- Prefer semantic search via `mgrep` when available (e.g., `mgrep "where are test suites defined?" tests`).
- Optional: enable repo hooks: `cp .githooks/pre-commit .git/hooks/pre-commit` and `cp .githooks/pre-push .git/hooks/pre-push`.
