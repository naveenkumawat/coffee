# Production Deployment Runbook — The88Coffees / Coffee Café

Last updated: August 31, 2026  
Status: prepare only — do **not** deploy until smoke tests on real HTTPS hosts pass.  
Customer Blade remains available until an explicit retirement task.

See also: `docs/pwa-launch-checklist.md`

---

## A. Host model (env-driven)

| Surface | Example shape | Notes |
| --- | --- | --- |
| Customer PWA | `https://app.example.com` | Static `customer-pwa/dist/` |
| Laravel | `https://api.example.com` | DocumentRoot → `public/` |
| Customer API | `https://api.example.com/api/v1` | |
| Sanctum CSRF | `https://api.example.com/sanctum/csrf-cookie` | Same Laravel origin as API |
| Administrator | `https://api.example.com/administrator` | |
| Barista | `https://api.example.com/barista` | |

Do **not** hardcode domains in application code. Configure via `.env` / PWA build env.

**Host consistency:** list every Origin/host used in one auth flow in both `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS`. Do not mix `www` / bare domain, or localhost / LAN IP, unless all variants are explicitly configured.

---

## B. Prerequisites

- PHP 8.4+, Composer, MySQL, Apache with `mod_rewrite` + `mod_headers` (+ `mod_ssl` for HTTPS)
- Node.js suitable for Vite (see `customer-pwa/package.json`)
- TLS certificates for PWA and Laravel hosts
- Backups of DB + `storage/` before first production migrate

---

## C. Backend environment

Copy `.env.example` → `.env` on the server. Production essentials:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_SEND_HSTS=true          # only after HTTPS is exclusive

SESSION_DRIVER=database
SESSION_DOMAIN=.example.com # shared parent when using sibling subdomains; else null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com

FILESYSTEM_DISK=local       # private payment proofs
COFFEE_MEDIA_DISK=public
COFFEE_PWA_URL=https://app.example.com

TRUSTED_PROXIES=*           # or comma-separated proxy IPs behind a load balancer

QUEUE_CONNECTION=database   # listeners are sync today; keep for future jobs
CACHE_STORE=database
LOG_LEVEL=warning
```

Optional Owner bootstrap (not demo catalog):

```bash
ADMIN_NAME="Cafe Owner"
ADMIN_EMAIL=owner@example.com
ADMIN_PASSWORD=...          # set only in server .env — never commit
```

Never commit secrets. Prefer Website Settings for café UPI/contact content; keep `COFFEE_*` as infrastructure fallbacks.

---

## D. Backend deploy

```bash
cd /path/to/coffee
git pull   # or rsync release

composer install --no-dev --optimize-autoloader

# Fresh server only (once). Never rotate APP_KEY after live data exists.
# php artisan key:generate --force

php artisan migrate --force

# Production seed: structural taxonomy + social shells + optional ADMIN_* owner.
# Does NOT load demo customers/orders/ratings when APP_ENV=production.
php artisan db:seed --force

php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After caching, verify with a quick request (`/api/v1/content`, `/sanctum/csrf-cookie`).  
Do **not** leave a local `config:cache`/`route:cache` in place when running PHPUnit — tests clear those caches automatically so phpunit.xml env values win.

Writable (web server user/group — **not** `0777`):

```bash
# Example only — replace www-data with your Apache user/group
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

---

## E. Database

- Always: `php artisan migrate --force`
- **Never** on production: `migrate:fresh`, `db:wipe`, `migrate:refresh`
- Backup MySQL before migrations
- Seed: `php artisan db:seed --force` with `APP_ENV=production` (IngredientCategory + SocialLink + optional ADMIN_*)

---

## F. Storage / media

```bash
php artisan storage:link
```

| Content | Disk | Path |
| --- | --- | --- |
| Product / category / website / payment QR | `public` (`COFFEE_MEDIA_DISK`) | `storage/app/public/...` → `/storage/...` |
| Payment proofs | `local` (private) | `storage/app/private/payment-proofs/...` — **not** symlinked publicly |

Confirm Apache DocumentRoot is `public/` so `.env`, `vendor/`, `storage/app/private`, and docs are not web-reachable.

---

## G. Apache (Laravel)

Prefer DocumentRoot = `.../coffee/public`.

Checklist:

- [ ] `AllowOverride All` (or equivalent) for `.htaccess`
- [ ] `mod_rewrite` enabled
- [ ] HTTPS vhost + redirect HTTP → HTTPS
- [ ] `/storage` symlink works for public media
- [ ] `/administrator`, `/barista`, `/api/v1`, `/sanctum/csrf-cookie` reachable
- [ ] Security headers from `public/.htaccess` / app middleware
- [ ] HSTS enabled in SSL vhost (or `APP_SEND_HSTS=true`) only after HTTPS cutover

Root `.htaccess` in the repo supports a local subdirectory layout (`/coffee`); production should use `public/` as the vhost root instead of that rewrite shim.

---

## H. PWA build / deploy

```bash
cd customer-pwa
cp .env.example .env   # or CI secrets
# Production:
# VITE_API_BASE_URL=https://api.example.com/api/v1

npm ci
VITE_ENFORCE_PRODUCTION_API=1 npm run typecheck
VITE_ENFORCE_PRODUCTION_API=1 npm run build
```

Deploy **`dist/`** to the PWA document root.

SPA fallback (Apache example — place in PWA vhost / docroot):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]
```

Must **not** intercept:

- hashed `/assets/*`
- `/sw.js`
- `/manifest.webmanifest`
- Laravel API (separate host)

CSRF continues to derive from `VITE_API_BASE_URL` origin → `/sanctum/csrf-cookie`.

---

## I. Queue / scheduler

**Queue:** domain events (e.g. menu cache flush) run synchronously today. No Supervisor worker is required for launch. Keep `QUEUE_CONNECTION=database` ready; if you add `ShouldQueue` jobs later, run:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
# after each deploy: php artisan queue:restart
```

**Scheduler:** no application schedules are registered yet (`routes/console.php` has only the sample `inspire` command). Optional cron (harmless no-op until jobs exist):

```cron
* * * * * cd /path/to/coffee && php artisan schedule:run >> /dev/null 2>&1
```

Timezone: application `config('app.timezone')` is `UTC` unless changed.

---

## J. Sanctum / CORS / session verification

1. From the PWA origin, `GET {backend}/sanctum/csrf-cookie` returns `204` with `Access-Control-Allow-Credentials: true` and matching `Access-Control-Allow-Origin`.
2. Login + authenticated `/api/v1/*` with `credentials: 'include'` and `X-XSRF-TOKEN`.
3. Cookies are `Secure` on HTTPS; `SameSite=lax`.
4. CORS origins are an explicit list — never `*`.

---

## K. HTTPS smoke test

Follow the detailed sequence in `docs/pwa-launch-checklist.md` (catalog → cart → auth → checkout → payment proof → admin/barista → rating → SW update).

---

## L. Rollback basics

1. Restore previous application release directory / git tag
2. Restore previous PWA `dist/`
3. Restore MySQL dump taken **before** the migration (preferred over `migrate:rollback` for production)
4. Run `php artisan optimize:clear` then re-cache config/routes/views
5. Never casually roll back destructive migrations; prefer forward fixes

---

## M. Customer Blade

Keep customer Blade routes enabled until production PWA + Sanctum + cookie smoke tests pass. Retirement is a **separate explicit task**.
