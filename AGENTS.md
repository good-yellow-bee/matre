# Repository Guidelines

## Project Structure & Module Organization
- Symfony app code lives in `src/` (controllers, entities, services); Twig views in `templates/`.
- HTTP entrypoint is `public/index.php`; routing/config lives under `config/`.
- Frontend assets: `assets/` (JS/CSS), Vue islands in `assets/vue/`, Vite config in `vite.config.mjs`, built output in `public/build/`.
- Database migrations reside in `migrations/`; tests in `tests/` (PHPUnit, BrowserKit).
- Docker: definitions in `docker-compose.yml`, Dockerfile at repo root, Nginx vhost in `docker/nginx/default.conf`.

## Build, Test, and Development Commands
- Start stack: `docker compose up -d --build` (Nginx: 8088, MySQL: 33066, MailHog UI: 8030/SMTP 1030).
- Shell into PHP container: `docker compose exec php sh`.
- Run Symfony console: `docker compose exec php php bin/console about`.
- DB setup: `docker compose exec php php bin/console doctrine:migrations:migrate`.
- Frontend build (already run by `frontend-build` service): `docker compose run --rm frontend-build npm run build`.

## Coding Style & Naming Conventions
- Follow PSR-12 for PHP; services/controllers in PascalCase, Twig blocks in snake_case; keep routes/action methods explicit.
- Prefer constructor injection and Symfony autowiring; avoid static helpers.
- Twig: keep layout fragments under `templates/_partials/`; name Vue entry files `assets/vue/<feature>-app.js`.
- Tools: PHP-CS-Fixer (`php vendor/bin/php-cs-fixer fix`) and PHPStan (`php vendor/bin/phpstan analyse`) mirror project config (`.php-cs-fixer.php`, `phpstan.neon`).

## Testing Guidelines
- Default test env uses `.env.test`; run `docker compose exec php php bin/phpunit` (adds SQLite or MySQL per env vars).
- Feature/browser tests live in `tests/`; name tests `*Test.php` and mirror namespace of code under test.
- Aim for coverage of new services, controllers, and Twig extensions; include fixtures or factories for DB-driven tests.

## Commit & Pull Request Guidelines
- Commit messages: concise, present-tense (“Add dashboard widget”), group related changes; reference ticket IDs when applicable.
- PRs: include summary of intent, main changes, migrations/env updates, and screenshots for UI tweaks.
- Keep PRs small and focused; ensure CI commands above pass before requesting review.

## Security & Configuration Tips
- Never commit secrets; override defaults in `.env.local` / `.env.test.local`.
- If exposing externally, change dev secrets in `docker-compose.yml` (DB password, `APP_SECRET`) and restrict MailHog ports.***
