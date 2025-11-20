# ReSymf-CMS

Modernized Symfony application for lightweight CMS + admin ops. Runs on Symfony 7 / PHP 8.2+, with Twig-driven pages, Doctrine ORM, and a small Vue/Vite layer for interactive islands.

## Stack
- Symfony 7.3, PHP 8.2+, Doctrine ORM
- Twig layouts with Bootstrap 5 (CDN), Stimulus/Turbo
- Asset Mapper fallback plus Vite build that outputs to `public/build`
- Vue 3 islands (demo widget on the admin dashboard)
- Mailer, Security, Validator, Translation, Flysystem bundles

## Getting Started
Backend:
```bash
composer install
cp .env .env.local          # adjust DATABASE_URL, MAILER_DSN, etc.
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
# optional: php bin/console doctrine:fixtures:load
symfony server:start -d     # or php -S localhost:8000 -t public
```

Frontend (Vite + Vue):
```bash
npm install
npm run dev -- --host 127.0.0.1 --port 5173   # HMR / dev server
npm run build                                  # production build -> public/build
```

The app will keep working without a Vite build because `vite_entry_*` Twig helpers fall back to the existing importmap/asset mapper pipeline, but Vue islands require the Vite output/manifest.

## Frontend architecture
- Entry points: `app` (public base), `admin` (back office), `cms` (public pages), `admin_vue` (dashboard Vue island).
- Twig uses `vite_entry_link_tags()` / `vite_entry_script_tags()` (see `src/Twig/ViteExtension.php`). With no Vite manifest present, it falls back to the previous asset mapper/importmap behavior for existing entries and skips optional ones.
- Vue demo: `assets/vue/components/DashboardHello.vue` mounts into `templates/admin/dashboard.html.twig` via `assets/vue/admin-dashboard-app.js`. Use this as a pattern for future islands (e.g., richer tables or live previews).
- Vite config lives in `vite.config.mjs`; outputs to `public/build/manifest.json`, aliases `@` to `assets/`.

## JSON endpoints (for Vue islands)
- `GET /api/pages` — paginated pages list with optional `q`, `page`, `limit` (requires `ROLE_USER`).
- `GET /api/categories` — quick list with optional `q` (requires `ROLE_USER`).

## Notes
- CSS lives under `assets/styles/`; JS entries under `assets/*.js`; Vue SFCs under `assets/vue/`.
- TinyMCE and Bootstrap continue to load from CDNs; you can move them into Vite if you want to self-host.
- To add a new Vue island: create a new entry under `assets/vue/`, add it to `rollupOptions.input` in `vite.config.mjs`, add a mount point in Twig, and include `vite_entry_script_tags('your_entry')`.

## License
MIT — see `LICENSE`.
