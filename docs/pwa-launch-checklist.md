# Customer PWA Launch Checklist

Last updated: August 31, 2026  
Status after Phase C6 final QA + D1/D2/D3 prep: ready for production smoke test on real HTTPS hosts; keep customer Blade until live verification.  
Deployment runbook: `docs/production-deployment.md`.

## Administrator production content checklist

Configure these in **Administrator** before public launch. Do **not** rely on local demo seed values (`WebsiteSettingSeeder` and demo catalog run only in `local`/`testing`).

**Working to-do (fill real café values here):** [`docs/launch-data-todo.md`](launch-data-todo.md)

### Launch data status (local audit — 31 Aug 2026)

Legend: **CONFIGURED** = Admin/system ready · **NEEDS REAL VALUE** = café must supply · **DEMO-ONLY** = seed sample, not launch · **OPTIONAL** = nice-to-have

| Area | Status | Notes |
| --- | --- | --- |
| Brand name / slogan targets | CONFIGURED (local baseline) | Website Settings: **The88Coffees** + **Sip. Relax. Enjoy.** (content-driven; not hardcoded in PWA). |
| Business phone / WhatsApp / email / address / hours | NEEDS REAL VALUE | Local empty. Demo seeder phones/email/address are **DEMO-ONLY**. |
| Social Links admin + footer | CONFIGURED | Dynamic CRUD. Local shells: Facebook/WhatsApp/Instagram (inactive, blank URLs — not public). URLs still **NEEDS REAL VALUE**. |
| Payment UPI / phone / QR / instructions | NEEDS REAL VALUE | Admin supports all. Demo UPI is **DEMO-ONLY**. |
| Fulfilment Takeaway + Delivery model | CONFIGURED | Third-party delivery; no invented fee. Disclaimer text: review (**NEEDS REAL VALUE** or keep default if approved). |
| CMS About / Visit / FAQ / Terms / Privacy | NEEDS REAL VALUE | Local empty. Do not invent legal Terms/Privacy. |
| ProductTags taxonomy | CONFIGURED | New, Top Seller, Featured, Seasonal, Popular, Limited, Recommended present. Assignments **OPTIONAL** / business decision. |
| Categories / flavours / products / prices | NEEDS REAL VALUE | Local: **0** products. Demo menu (18 items) is **DEMO-ONLY** — do not assume for production. |
| Recipes / major ingredients | NEEDS REAL VALUE | After real products exist; do not fake readiness. |
| Inventory opening stock | NEEDS REAL VALUE | Separate stock concern vs config incomplete. Do not invent quantities. |
| Product / QR / hero images | NEEDS REAL VALUE | PublicMedia + `storage:link`. Photography is a content task. |
| Homepage sections | NEEDS REAL VALUE | Local: **0** sections. Demo rails are **DEMO-ONLY**; driven by Admin sections, not tags. |
| Catalog readiness command | CONFIGURED | `php artisan coffee:catalog-readiness` — local: 0 products. |

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
| `COFFEE_PWA_URL` | Laravel | Public HTTPS PWA origin (reset links, customer URLs) |
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

## Production smoke-test checklist

1. Build PWA with production `VITE_API_BASE_URL`; deploy `dist/`; confirm `/manifest.webmanifest`, icons, `/sw.js` MIME types.
2. CSRF cookie + login + authenticated `/api/v1` from the PWA origin (`credentials` + `X-XSRF-TOKEN`).
3. Open PWA fresh → browse catalog → multi-select categories/flavours.
4. Guest add-to-cart → register/login → guest-cart merge → authenticated cart.
5. Checkout Takeaway + Delivery; manual payment details; payment proof upload.
6. Admin: view/confirm proof → accept order; Barista: prepare → Ready → Completed.
7. Customer order tracking + rating/review; logout/login again; PWA refresh/reopen + update banner.
8. Verify images, social/footer links, About/Visit/FAQ/Terms/Privacy, dynamic homepage sections.
9. Force offline: shell messaging; cart/checkout/orders do not succeed from cache.
10. Install / Add to Home Screen (Android Chrome; iOS Safari where supported).
11. Only after the above: schedule Blade customer storefront retirement separately.

## Remaining launch blockers / out of scope

- **Real café data entry** — track in [`docs/launch-data-todo.md`](launch-data-todo.md) (brand/contact, payment, CMS, catalog, media, homepage).
- Real product photography (components ready; assets not final).
- Live production HTTPS cutover + cookie domain validation on the real hosts (environment-specific) — D4 blocked until hosts/access exist.
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
