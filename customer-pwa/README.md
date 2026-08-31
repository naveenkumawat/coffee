# Coffee Customer PWA

Customer-facing mobile-first PWA for Coffee Cafe.

Recommended hosting shape:

```text
PWA host (static HTTPS)  ->  Laravel API host `/api/v1`
```

Customer Blade routes stay live until the PWA cutover is verified.

## Runtime Requirement

- Node.js `20.20.2` LTS via `.nvmrc`
- npm `10.x`

This workspace was verified with:

- `node v20.20.2`
- `npm 10.8.2`

## Local Setup

Start Homebrew Apache (document root `/Volumes/Storage/www`, app URL `/coffee`):

```bash
brew services start httpd
# or: sudo /usr/local/bin/apachectl start
# or: sudo /usr/local/bin/apachectl restart
```

If the PWA shows `ERR_CONNECTION_REFUSED` for `/coffee/api/v1` or `/sanctum/csrf-cookie`, Apache is not running.

Then:

```bash
cd customer-pwa
nvm install
nvm use
npm install
cp .env.example .env
```

Example local `.env`:

```dotenv
VITE_API_BASE_URL=http://localhost/coffee/api/v1
```

Root Laravel `.env` companions:

```dotenv
APP_URL=http://localhost/coffee
COFFEE_PWA_URL=http://localhost:4173
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:4173,127.0.0.1,127.0.0.1:4173
CORS_ALLOWED_ORIGINS=http://localhost:4173,http://127.0.0.1:4173
SESSION_DOMAIN=null
```

Vite dev server defaults to `http://localhost:4173` (see `vite.config.ts`).
## Required Production Environment

### PWA build (`customer-pwa`)

| Variable | Purpose |
| --- | --- |
| `VITE_API_BASE_URL` | Absolute API base ending in `/api/v1` (example: `https://api.example.com/api/v1`) |

### Laravel API host

| Variable | Purpose |
| --- | --- |
| `COFFEE_PWA_URL` | Public HTTPS origin of the installed PWA (password-reset and customer links) |
| `SANCTUM_STATEFUL_DOMAINS` | PWA host(s) without scheme, comma-separated (example: `app.example.com`) |
| `CORS_ALLOWED_ORIGINS` | Full PWA origin(s) with scheme (example: `https://app.example.com`) |
| `SESSION_DOMAIN` | Shared parent cookie domain when API and PWA share a registrable domain (example: `.example.com`); keep `null` for localhost |
| `SESSION_SECURE_COOKIE` / HTTPS | Production must serve both apps over HTTPS so Sanctum session cookies are secure and first-party/cross-subdomain capable |
| `APP_URL` | Laravel public URL |

HTTPS/cookie notes:

- Serve the PWA and API on HTTPS only in production.
- Prefer sibling subdomains (`app.` + `api.` or apex + `api.`) with a shared `SESSION_DOMAIN`.
- Include the PWA host in both Sanctum stateful domains and CORS allowed origins.
- Do not rely on service-worker cache for auth, cart, checkout, or orders.

## Common Commands

```bash
npm run typecheck
npm run build
npm audit
npm run preview
```

## PWA Runtime Behavior

- Service worker: custom Workbox-style SW from `scripts/generate-sw.mjs` (after `vite build`) — not `vite-plugin-pwa` codegen
- Navigation / `index.html`: **network-first**, then refresh the shell cache (avoids stale HTML pointing at deleted hashed chunks)
- Hashed `/assets/*`: **cache-first** (immutable filenames); HTML MIME responses are never stored as JS/CSS
- `sw.js` + web manifest: **network-first** (never long-cached by the SW)
- `/api/*`, `/sanctum/*`, and `/storage/*` are never intercepted
- Offline banner + `public/offline.html` explain that live data needs a connection
- Update banner prompts refresh when a new service-worker version is waiting (`SKIP_WAITING` only on user action)
- Lazy route chunk failures: one automatic recovery reload per build (clears shell caches); otherwise branded `RouteErrorPage`
- Manifest: `public/manifest.webmanifest` with standalone display and `any` + `maskable` icons

### Clean LAN / preview rebuild testing

`npm run preview` is production-mode and **will** register the service worker. After frequent rebuilds:

1. Stop preview, rebuild (`npm run build`), start preview again.
2. In the browser (same origin: host + port): DevTools → Application → **Unregister** service workers, then **Clear site data** (or at least Cache Storage + unregister).
3. Hard-refresh / reopen `http://<host>:4173`.
4. Optional check: open app → navigate Menu/Home → rebuild while the tab stays open → navigate again. Expect one silent recovery reload **or** the branded refresh screen — never the React Router “Hey developer” page.

`npm run dev` unregisters any SW on that origin so local HMR is not controlled by a stale production worker.

## Production Deployment / Cutover Checklist

See also: [`docs/pwa-launch-checklist.md`](../docs/pwa-launch-checklist.md) (C6 QA results, blockers, smoke tests).

1. **Build**
   - Set `VITE_API_BASE_URL` to the production Laravel `/api/v1` URL
   - Run `npm run typecheck && npm run build && npm audit`
   - Deploy `customer-pwa/dist` to the PWA HTTPS host
2. **Env**
   - Set Laravel `COFFEE_PWA_URL`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`, `SESSION_DOMAIN`
   - Confirm cookie/HTTPS settings for the shared parent domain (`SESSION_SECURE_COOKIE=true` in production)
3. **HTTPS**
   - Verify PWA and API certificates and HSTS as applicable
4. **CORS / Sanctum**
   - From the PWA origin, confirm CSRF cookie + login + authenticated `/api/v1` calls succeed with credentials
5. **Service worker cache / update**
   - Install/open the PWA once, ship a new build, confirm the in-app “new version” refresh banner appears
   - Confirm offline visit still loads the shell and shows offline messaging
   - Confirm Network panel: API/auth/cart/checkout/orders are not served from Cache Storage as authoritative responses
   - Confirm `/sw.js` and hashed assets are not served as `text/html`
6. **Smoke test**
   - Auth register/login/logout
   - Menu browse + product detail
   - Cart add/update/remove
   - Checkout + payment instructions
   - Orders list/detail refresh while online
7. **Rollback / cutover note**
   - Keep customer Blade routes enabled until PWA smoke tests pass in production
   - Rollback = point `COFFEE_PWA_URL`/customer traffic back to Blade or previous PWA dist; no Blade removal in this hardening pass
8. **Launch gate**
   - Only after verified live PWA auth/cart/checkout/orders, schedule Blade customer storefront retirement separately
