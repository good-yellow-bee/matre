# SPA Frontend

The MATRE admin UI is a single Vue 3 SPA (Vue Router 4 + Pinia + Tailwind CSS 4) living in `assets/spa/`. Symfony serves one shell page and exposes a JSON API under `/api`; everything else (routing, rendering, state) happens client-side.

## Architecture

```
Browser request (/admin/..., /login, /2fa, ...)
    └── SpaController catch-all (priority -100, GET only)
            └── templates/spa/index.html.twig (shell)
                    └── vite_entry_*_tags('spa') — ViteExtension reads public/build/.vite/manifest.json
                            └── assets/spa/main.js → App.vue → router → views
```

- `SpaController` serves the shell for every GET path except `/api/*`, `/build/`, `/uploads/`, `/_profiler`, `/_wdt`, `/2fa_check`, `/logout` — so URLs are unchanged from the Twig era (`/admin/test-runs/42` still works, deep links included).
- `App\Twig\ViteExtension` provides `vite_entry_script_tags()` / `vite_entry_link_tags()` and resolves hashed filenames from the Vite manifest (no vite-bundle dependency).
- Server-side authorization stays in `security.yaml` `access_control` (`/admin/profile|test-history` and `/admin` root need `ROLE_USER`, the rest of `/admin` needs `ROLE_ADMIN`); the router guards mirror it for UX.

## Directory Layout

```
assets/spa/
├── main.js               # Entry: fonts, app.css, Pinia, router, mount #app
├── App.vue               # Root component
├── api/client.js         # fetch wrapper (CSRF, errors, 401 handling)
├── router/index.js       # All routes + navigation guards
├── stores/               # Pinia: auth.js, toasts.js
├── layouts/              # AdminLayout.vue (sidebar shell), AuthLayout.vue
├── components/
│   ├── shell/            # AppSidebar, AppTopbar, BrandMark
│   └── ui/               # Shared UI kit (see below)
├── composables/          # useConfirm, useRunningActivity, useTheme
├── utils/                # debounce, format, sensitive
├── styles/app.css        # Tailwind 4 + design tokens
└── views/
    ├── auth/             # LoginView, TwoFactorView, TwoFactorSetupView
    ├── dashboard/
    ├── test-runs/        # Views + components/ + utils/ (feature-scoped)
    ├── environments/     # EnvironmentsView, EnvironmentFormView, EnvironmentDetailView, components/
    ├── suites/ users/ cron-jobs/ env-variables/ audit-logs/
    ├── templates/ settings/ profile/ test-history/
    ├── LandingView.vue
    └── NotFoundView.vue
```

Convention: one directory per feature under `views/`, named `{Feature}View.vue` / `{Feature}FormView.vue` / `{Feature}DetailView.vue`, with feature-private components in a nested `components/` directory. Only genuinely shared components go to `components/ui/`.

## Router & Guards

Routes are declared in `assets/spa/router/index.js` with lazy-loaded views and meta flags:

| Meta | Effect |
|------|--------|
| `public: true` | No auth required (landing, 404) |
| `guest: true` | Redirects authenticated users to dashboard (login) |
| `requires2fa: true` | Only reachable while a 2FA challenge is pending |
| `requiresAuth: true` | Requires authenticated session (set on the `/admin` parent) |
| `requiresAdmin: true` | Additionally requires `ROLE_ADMIN` |
| `title`, `section` | Document title + sidebar grouping |

The global `beforeEach` guard calls `auth.bootstrap()` (one `GET /api/me` per app load), then:

1. Pending 2FA → `/2fa`
2. Unauthenticated → `/login?redirect={fullPath}`
3. `enforce2fa` enabled but TOTP not set up → `/2fa-setup`
4. `requiresAdmin` without `ROLE_ADMIN` → dashboard

A global unauthorized handler (registered via `setUnauthorizedHandler`) resets the auth store and redirects to login whenever any API call returns 401.

## Auth Store (`stores/auth.js`)

State is the raw `/api/me` payload; getters derive everything:

- `isAuthenticated`, `twoFactorPending`, `requires2faSetup`, `isAdmin`
- `user`, `settings` (site name, panel title, enforce2fa), `urls` (Allure, noVNC)

Actions:

- `bootstrap(force)` — cached `GET /api/me` (3-state response: anonymous / 2FA-pending / authenticated)
- `login(username, password, rememberMe)` — `POST /api/login` (intercepted by `json_login`), then re-bootstrap
- `verify2fa(code)` — `POST /2fa_check` with JSON `{_auth_code}`
- `logout()` — `POST /logout`, then local reset

## API Client (`api/client.js`)

```javascript
import { api } from '../api/client';

const runs = await api.get('/api/test-runs', { params: { status: 'failed' } });
await api.post('/api/test-runs', { environmentId: 1, type: 'mftf' });
await api.put(`/api/test-suites/${id}`, payload);
await api.delete(`/api/users/${id}`);
```

Behavior:

- Sends `X-Requested-With: XMLHttpRequest` and `Accept: application/json` on every request, `credentials: 'same-origin'`.
- **CSRF:** on every non-GET request the client generates one random 48-hex-char token per page load and sends it as `X-CSRF-Token`. The backend (`ApiCsrfListener` + Symfony stateless CSRF) accepts any token ≥ 24 chars as long as the request is same-origin (Origin/Referer check or double-submit cookie). No token fetching or rotation needed.
- Non-2xx responses throw `ApiError` with `status` and the decoded `payload`; the message comes from the server's `error`/`message` field.
- 401 responses (outside `/api/me`, `/api/login`, `/2fa_check`) trigger the global unauthorized handler.

## Design Tokens & Theme

`assets/spa/styles/app.css` is Tailwind 4 CSS-first config (no `tailwind.config.js`):

- `:root` defines light-mode tokens, `.dark` overrides them (dark-first "mission control" theme: graphite surfaces, cyan accent).
- `@theme inline` maps tokens to Tailwind colors: `surface`, `panel`, `edge`, `ink`, `accent`, plus status colors `pass`/`fail`/`broken`/`skip`/`run`/`pend` (`text-ink`, `bg-panel`, `border-edge`, `text-fail`, ...).
- Fonts: Archivo Variable (sans) + JetBrains Mono Variable (mono), self-hosted via `@fontsource-variable`.
- Dark mode uses a custom variant on the `.dark` class (`@custom-variant dark`); preference persists in `localStorage` under `matre_theme` (toggle via the `useTheme()` composable). The shell template applies the class before first paint to avoid flashes, falling back to `prefers-color-scheme`.

## Shared UI Kit (`components/ui/`)

| Component | Purpose |
|-----------|---------|
| `DataTable` | Column-config table with cell slots, loading skeleton, empty slot |
| `Modal` | Dialog with focus trap / a11y |
| `ConfirmDialog` + `useConfirm()` | Promise-based confirm (global host in the layout) |
| `PageHeader` | Title, subtitle, `#actions` slot |
| `StatusBadge` | Run/result status pill (uses status token colors) |
| `Pagination` | Page controls for `meta`-paginated endpoints |
| `Toggle` | Boolean switch |
| `ErrorBanner` | Inline API error display |
| `EmptyState` | Empty list placeholder |
| `ToastHost` + `stores/toasts.js` | `toasts.success('...')` / `.error()` / `.info()`, aria-live |

## Adding a New Page

1. **API endpoint** — add a controller in `src/Controller/Api/` (see [Admin CRUD](admin-crud.md) for the pattern).
2. **View** — create `assets/spa/views/{feature}/{Feature}View.vue`; put feature-only subcomponents in `assets/spa/views/{feature}/components/`.
3. **Route** — register it in `assets/spa/router/index.js` as a child of the `/admin` route:

   ```javascript
   { path: 'widgets', name: 'widgets', component: () => import('../views/widgets/WidgetsView.vue'),
     meta: { title: 'Widgets', section: 'Administration', requiresAdmin: true } },
   ```

4. **Sidebar** — add `{ name: 'widgets', label: 'Widgets', icon: SomeIcon, admin: true }` to the matching section in `assets/spa/components/shell/AppSidebar.vue` (icons from `lucide-vue-next`).
5. **Access control** — admin pages are covered by the existing `^/admin` rule; user-accessible pages must be added to the `ROLE_USER` patterns in `config/packages/security.yaml`.
6. **Build** — `npm run build` (no per-page Vite entries; the router lazy-imports views, so each view becomes its own chunk automatically).

## Build & Dev Workflow

```bash
npm run build           # Production build → public/build (manifest at public/build/.vite/manifest.json)
npx vite build --watch  # Rebuild on change while developing (refresh browser at http://localhost:8089)
npm run dev             # Standalone Vite dev server on :5173 (serves modules only;
                        # the Symfony shell loads built assets from public/build)
```

PHP runs in Docker (`matre_php`); the SPA is served through nginx at `http://localhost:8089`. In production, deploy frontend changes with `./prod.sh frontend`.
