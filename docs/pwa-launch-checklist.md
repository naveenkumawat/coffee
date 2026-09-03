# Customer PWA Launch Checklist

Last updated: September 4, 2026 (L2 launch-data readiness)  
Status after Phase C6 final QA + D1/D2/D3 prep + L2 audit: ready for production smoke test **only after** real café data clears `coffee:launch-readiness`; keep customer Blade until live verification.  
Deployment runbook: `docs/production-deployment.md`.  
Launch data checklist: `docs/launch-data-todo.md`.

## Administrator production content checklist

Configure these in **Administrator** before public launch. Do **not** rely on local demo seed values (`WebsiteSettingSeeder` and demo catalog run only in `local`/`testing`).

**Working to-do (fill real café values here):** [`docs/launch-data-todo.md`](launch-data-todo.md)

### Launch data status (L2 audit — 4 Sep 2026)

Legend: **READY** · **NEEDS REAL VALUE** · **DEMO-ONLY** · **OPTIONAL/DEFERRED**

| Area | Status | Notes |
| --- | --- | --- |
| Brand name / slogan | READY (baseline) / verify on prod | Website Settings targets The88Coffees + Sip. Relax. Enjoy. |
| Business phone / WhatsApp / email / address / hours | NEEDS REAL VALUE | |
| Social Links shells | READY | URLs NEEDS REAL VALUE |
| Payment UPI / phone / QR / instructions | NEEDS REAL VALUE | Manual payment; no gateway |
| Fulfilment Takeaway + Delivery + optional Dine-in | READY (capability) | Confirm ops; dine-in needs real tables before enable |
| CMS About / Visit / FAQ / Terms / Privacy | NEEDS REAL VALUE | Legal copy must be café-approved |
| ProductTags taxonomy | READY | Assignments OPTIONAL |
| Categories / products / prices / recipes | NEEDS REAL VALUE | `launch-menu.md` STOPPED; clean DB has 0 products |
| Inventory opening stock | NEEDS REAL VALUE | Do not invent |
| Product / QR / hero images | NEEDS REAL VALUE | 1:1 product imagery preferred |
| Homepage sections | OPTIONAL/DEFERRED | |
| Staff accounts | NEEDS REAL VALUE | Never promote `*@coffee.local` / `password` |
| Tables | NEEDS REAL VALUE if Dining | Do not invent count |
| Delivery fee in checkout | OPTIONAL/DEFERRED | Null reserved; third-party customer-paid assumed |
| Launch readiness command | READY | `php artisan coffee:launch-readiness` (`--json`; non-zero on blockers) |
| Catalog readiness command | READY | `php artisan coffee:catalog-readiness` |

### Brand & business
- [x] Brand / café name (`business_name`) — **The88Coffees** — CONFIGURED (local baseline)
- [x] Home slogan (`hero_subtitle`) — **Sip. Relax. Enjoy.** — CONFIGURED (local baseline)
- [ ] Social links (Facebook / WhatsApp / Instagram) — shells present; URLs NEEDS REAL VALUE
- [ ] Phone — NEEDS REAL VALUE
- [ ] WhatsApp — NEEDS REAL VALUE
- [ ] Email — OPTIONAL / NEEDS REAL VALUE if shown
- [ ] Pickup / visit address — NEEDS REAL VALUE
- [ ] Opening / business hours — NEEDS REAL VALUE

### Customer pages
- [ ] About — NEEDS REAL VALUE
- [ ] Visit / Contact — NEEDS REAL VALUE
- [ ] FAQ — NEEDS REAL VALUE
- [ ] Terms — NEEDS REAL VALUE (approved legal copy)
- [ ] Privacy — NEEDS REAL VALUE (approved legal copy)

### Payment (manual UPI)
- [ ] Payment display name — NEEDS REAL VALUE
- [ ] UPI ID — NEEDS REAL VALUE
- [ ] Payment phone — NEEDS REAL VALUE
- [ ] Payment QR image — NEEDS REAL VALUE (`storage:link`)
- [ ] Payment instructions — NEEDS REAL VALUE
- [ ] Payment WhatsApp — NEEDS REAL VALUE

### Fulfilment
- [ ] Delivery disclaimer reviewed — NEEDS REAL VALUE (or confirm default third-party wording)
- [ ] Confirm takeaway + delivery match café ops — CONFIGURED capability

### Catalog & homepage
- [ ] Real categories — NEEDS REAL VALUE
- [ ] Real flavours — NEEDS REAL VALUE / OPTIONAL
- [ ] Real products (drafts OK while incomplete) — NEEDS REAL VALUE
- [ ] Variants/prices — NEEDS REAL VALUE
- [ ] Recipes — NEEDS REAL VALUE
- [ ] Product images — NEEDS REAL VALUE
- [ ] Major ingredients — NEEDS REAL VALUE
- [ ] ProductTags — OPTIONAL
- [ ] Inventory quantities — NEEDS REAL VALUE
- [ ] Homepage sections — NEEDS REAL VALUE
- [ ] `php artisan coffee:launch-readiness` exits 0 on production DB
- [ ] No incomplete product publicly active — enforce via readiness
- [ ] Customer catalog manually reviewed

### Catalog readiness notes
- Inactive incomplete drafts can be saved freely.
- Activating a product requires launch-ready configuration (category, image, active variants with prices + recipes).
- “Unavailable / paused” (`is_available=false`) is separate from configuration Incomplete.
- Stock concern notes are informational; they do not invent new automatic sellability rules.

### Media ops note
- Public product / website / payment QR files use the `public` disk under `products/`, `categories/`, `website/`.
- Run `php artisan storage:link` on each deploy host.
- PWA resolves absolute API media URLs and falls back when an image is missing or broken.
- Install titles in `manifest.webmanifest` / `index.html` are build-time; rebuild PWA if the install name must change.

## Completed QA (C6)

- Responsive shell checked for common phone widths (320–430), tablet, and desktop fallback: overflow clipping, sticky CTA vs bottom nav/safe-area, long text wrapping, compact bottom-nav labels on very small screens.
- Journey coverage reviewed against seeded statuses: browse → product → cart → auth redirect → checkout → confirmation → tracking; favourites/account/auth; Pending Payment / Ready / Cancelled / Rejected treatments from C4.
- PWA installability: `manifest.webmanifest` (standalone, theme `#04764e`, background `#f6f1eb`), `any` + `maskable` icons, Apple touch meta, SW registration in production only (dev unregisters to avoid MIME/`text/html` for `/sw.js`).
- Offline/network: offline banner + `offline.html`; API/`/sanctum` never treated as cache authority; SW refuses to cache HTML as static assets; update banner + `SKIP_WAITING` / `controllerchange` reload.
- Performance: route lazy-loading retained; product/hero images lazy (eager only for LCP hero/detail); catalog media resolver ready for real Laravel storage URLs; no new dependencies.
- Accessibility: skip link, focus styles, form alerts, FAQ accordion semantics, reduced-motion, status badges with text.
- Imagery readiness: shared `ProductImage` with aspect ratio, object-fit, broken-image fallback to placeholder — final photos not sourced yet.

## Required production environment

Full runbook: **`docs/production-deployment.md`**.

| Variable | Where | Notes |
| --- | --- | --- |
| `APP_ENV=production` / `APP_DEBUG=false` | Laravel | Never debug in production |
| `APP_URL` | Laravel | HTTPS backend origin |
| `VITE_API_BASE_URL` | PWA build | Absolute `https://…/api/v1` baked at build time; use `VITE_ENFORCE_PRODUCTION_API=1` for real releases |
| `CUSTOMER_APP_URL` | Laravel | Public HTTPS PWA origin for email CTAs / password reset (preferred) |
| `COFFEE_PWA_URL` | Laravel | Fallback PWA origin if `CUSTOMER_APP_URL` unset |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` | Laravel | SMTP (or other Laravel mailer) — never store in Website Settings |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Laravel | From identity for transactional mail |
| `WHATSAPP_NOTIFICATIONS_ENABLED` | Laravel | Default `false`; enable only after Meta setup |
| `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_ACCESS_TOKEN` / `WHATSAPP_API_VERSION` | Laravel | Meta Cloud API infrastructure — never Website Settings |
| `WHATSAPP_TEMPLATE_*` / `WHATSAPP_TEMPLATE_LANGUAGE` | Laravel | Approved template name mappings |
| `SANCTUM_STATEFUL_DOMAINS` | Laravel | PWA host(s), no scheme |
| `CORS_ALLOWED_ORIGINS` | Laravel | Full PWA origin(s) with scheme — never `*` |
| `SESSION_DOMAIN` | Laravel | Shared parent cookie domain when applicable; `null` on single-host setups |
| `SESSION_SECURE_COOKIE` / HTTPS | Laravel | `true` in production; both hosts on HTTPS |
| `SESSION_SAME_SITE=lax` | Laravel | Default for SPA cookie auth |
| `TRUSTED_PROXIES` | Laravel | `*` or proxy IPs behind a load balancer |
| `FILESYSTEM_DISK=local` | Laravel | Private payment proofs |
| `COFFEE_MEDIA_DISK=public` | Laravel | Catalog/website/QR via `storage:link` |
| `COFFEE_*` payment / WhatsApp / delivery disclaimer | Laravel `.env` | Infrastructure fallbacks; prefer Website Settings for live café values |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Laravel `.env` | Optional Owner bootstrap on seed — not demo content |
| `APP_SEND_HSTS` | Laravel | `true` only after exclusive HTTPS |

**Host consistency:** do not mix www/non-www or localhost/LAN within one auth flow unless every variant is listed in Sanctum + CORS.

Keep customer Blade routes enabled until the live smoke checklist below passes. Retirement is a later explicit task.

## Apache / PWA hosting (summary)

- Laravel DocumentRoot → `public/` (`mod_rewrite`, `AllowOverride`, HTTPS redirect)
- `php artisan storage:link`; private payment proofs stay on the `local` disk
- PWA: `npm ci` → `VITE_ENFORCE_PRODUCTION_API=1 npm run build` → deploy `dist/` with SPA fallback to `index.html` (do not fall through assets / `sw.js` / manifest)
- Writable: `storage/`, `bootstrap/cache/` (group-writable; not `0777`)
- Deploy caches: `config:cache`, `route:cache`, `view:cache` after `optimize:clear`
- DB: `php artisan migrate --force` only — never `migrate:fresh` on production
- Backups: MySQL + `storage/` before migrations; restore procedure in runbook

## Transactional email checklist

- [ ] `MAIL_FROM_ADDRESS` configured
- [ ] `MAIL_FROM_NAME` configured
- [ ] SMTP credentials configured (`MAIL_MAILER` / host / port / username / password / encryption)
- [ ] `CUSTOMER_APP_URL` configured (HTTPS PWA origin; no localhost/LAN in production)
- [ ] Welcome email tested
- [ ] Password reset tested
- [ ] Order confirmation tested
- [ ] Payment received / confirmed tested
- [ ] Ready email tested (pickup vs delivery wording)
- [ ] Completed email tested
- [ ] Mobile email rendering checked
- [ ] SPF configured (DNS)
- [ ] DKIM configured (DNS)
- [ ] DMARC reviewed/configured (DNS)

## Transactional WhatsApp checklist (Meta Cloud API)

**Live Meta connection status (31 Aug 2026): STOPPED — not configured.**  
Local `.env` has **no** `WHATSAPP_*` values. Cloud API credentials, WABA/phone number IDs, approved template names, and an approved test destination were not provided. Outbound remains `WHATSAPP_NOTIFICATIONS_ENABLED=false`. Unit/feature tests with `Http::fake()` do **not** satisfy these live checks.

- [ ] Meta Business Portfolio / account ready
- [ ] WhatsApp Business Account (WABA) ready
- [ ] Sending phone number ready
- [ ] Phone number ID configured (`WHATSAPP_PHONE_NUMBER_ID`)
- [ ] WABA ID configured (`WHATSAPP_BUSINESS_ACCOUNT_ID`)
- [ ] Permanent/system-user access token configured (`WHATSAPP_ACCESS_TOKEN`) — never commit; do not use temporary tokens in production
- [ ] Required templates approved in Meta (order placed, proof received, payment confirmed, proof rejected, accepted, ready pickup, ready delivery, completed, cancelled/rejected)
- [ ] Template mappings configured (`WHATSAPP_TEMPLATE_*`) to **approved** names only
- [ ] Template language configured (`WHATSAPP_TEMPLATE_LANGUAGE`) to match approved language
- [ ] Controlled provider test sent (Graph auth + template accepted + `provider_message_id` logged)
- [ ] Order placed WhatsApp received (approved test number only)
- [ ] Payment proof received WhatsApp received (wording does **not** claim payment confirmed)
- [ ] Payment confirmed WhatsApp received
- [ ] Order accepted WhatsApp received
- [ ] Pickup-ready WhatsApp received (`order_ready_pickup`)
- [ ] Delivery-ready WhatsApp received (`order_ready_delivery`)
- [ ] Completed WhatsApp received
- [ ] Cancellation/rejection WhatsApp received
- [ ] Idempotency verified (`unique_key` + `channel=whatsapp`; no duplicate after success)
- [ ] Failure isolation tested (order/email OK; WhatsApp failed/skipped; no secrets in logs)
- [ ] WhatsApp notifications enabled (`WHATSAPP_NOTIFICATIONS_ENABLED=true`) only after the above
- [ ] Queue worker running for `SendCustomerWhatsAppMessage`
- [ ] Confirm Website Settings café WhatsApp (public contact) remains separate from API credentials

**Later (not required for basic Cloud API send):** Meta delivery-status webhooks (`sent` / `delivered` / `read` / `failed`). Application currently records API acceptance as submitted/`sent`, not customer “delivered”.

**When credentials exist**, put them only in server `.env` (never docs/git), keep enabled=false until a controlled provider test against an approved owner/test WhatsApp number succeeds, then enable and clear config cache (`php artisan config:clear` / `config:cache` as appropriate).

Body variable order expected by the app (match Meta template placeholders `{{1}}`…):

| Mapping key | Body params (in order) |
| --- | --- |
| `order_placed` | name, order number, total, fulfilment, business |
| `payment_proof_received` | name, order number, business |
| `payment_confirmed` | name, order number, business |
| `payment_proof_rejected` | name, order number, reason, business |
| `order_accepted` | name, order number, business |
| `order_ready_pickup` | name, order number, pickup address, business |
| `order_ready_delivery` | name, order number, delivery address summary, business |
| `order_ready_dine_in` | name, order number, table label, business (leave env empty until Meta approval) |
| `order_completed` | name, order number, order URL, business |
| `order_cancelled` | name, order number, status label, reason, business |

## Production smoke-test checklist

1. Build PWA with production `VITE_API_BASE_URL`; deploy `dist/`; confirm `/manifest.webmanifest`, icons, `/sw.js` MIME types.
2. CSRF cookie + login + authenticated `/api/v1` from the PWA origin (`credentials` + `X-XSRF-TOKEN`).
3. Open PWA fresh → browse catalog → multi-select categories/flavours.
4. Guest add-to-cart → register/login → guest-cart merge → authenticated cart.
5. Checkout Takeaway + Delivery (+ Dine-in only if enabled with active tables); manual payment details; payment proof upload.
6. Admin: view/confirm proof → accept order; Barista: prepare → Ready → Completed. Confirm staff notification bell for new order / proof review (Administrator) and payment-confirmed (Barista). Dine-in orders must show table prominently for staff.
7. Customer order tracking + rating/review; logout/login again; PWA refresh/reopen + update banner.
8. Verify images, social/footer links, About/Visit/FAQ/Terms/Privacy, dynamic homepage sections.
9. Force offline: shell messaging; cart/checkout/orders do not succeed from cache.
10. Install / Add to Home Screen (Android Chrome; iOS Safari where supported).
11. Only after the above: schedule Blade customer storefront retirement separately.

## Remaining launch blockers / out of scope

- **Real café data entry** — track in [`docs/launch-data-todo.md`](launch-data-todo.md) (brand/contact, payment, CMS, catalog, media, homepage).
- Real product photography (components ready; assets not final).
- Live production HTTPS cutover + cookie domain validation on the real hosts (environment-specific) — D4 blocked until hosts/access exist.
- Live Meta WhatsApp Cloud API connection — blocked until WABA credentials, approved templates, mappings, and an approved test destination are provided (`WHATSAPP_NOTIFICATIONS_ENABLED` stays false).
- Optional later UX (explicitly not in C6): pull-to-refresh, “I’m on my way”, offers, reorder, quick view.
- Device lab sign-off on physical iOS/Android install flows still recommended before Blade retirement.

## D4 production deployment attempt (31 Aug 2026)

**Status: STOPPED — not deployed.** Live HTTPS smoke checks below were **not** executed (no production host/access provided). Customer Blade remains available.

### Local pre-deploy gate (verified)

- `php artisan test` — 201 passed
- Pint — passed
- `customer-pwa` `npm run typecheck` + `npm run build` — passed
- Demo seed remains gated to `local`/`testing`; production seed = structural + optional `ADMIN_*`
- `php artisan coffee:catalog-readiness` runs (local DB had 0 products)
- Production API enforcement available via `VITE_ENFORCE_PRODUCTION_API=1` (strict HTTPS build not run here — no real backend URL)

### Not recorded (unknown / not provided)

- Backend host / `APP_URL`
- PWA host / `COFFEE_PWA_URL` / `VITE_API_BASE_URL`
- Deployed revision on any production server
- Production MySQL / storage backup paths
- SSH or other server access
- Production `.env` values (Sanctum/CORS/session/DB/mail)

### Required before D4 can continue

1. Production Laravel HTTPS origin (e.g. `https://…`)
2. Production PWA HTTPS origin (if separate)
3. Server access (SSH user/host, deploy path, or approved deploy channel)
4. Ability to take MySQL + `storage/` backups before migrate
5. Confirmation that production `.env` will be set with `APP_ENV=production`, `APP_DEBUG=false`, and real Sanctum/CORS/session values (agent must not invent these)
6. Optional: `ADMIN_EMAIL` / `ADMIN_PASSWORD` for Owner bootstrap (server `.env` only)
7. Real Website Settings / catalog / social URLs to configure (or confirmation they are already set in Admin)

No smoke-test checklist items above were marked complete against production.
