# Pre-launch integration smoke audit

Date: 19 September 2026  
Scope: local machine (`APP_ENV=local`). Apache DocumentRoot Laravel, Vite PWA on `:4173`.  
Method: HTTP/config/process checks, existing automated tests. **No Dusk/browser tools were available.** Viewport, click-through, IndexedDB/Cache Storage, and console inspection were **not** exercised in a real browser.

Legend: **PASS** · **FAIL** · **NOT TESTED** · **BLOCKED**

No application code was changed. Frozen business rules were not modified. `migrate:fresh` was not run.

---

## 1. Environment

| Check | Result | Notes |
| --- | --- | --- |
| Apache Laravel `http://localhost/coffee/` | **PASS** | HTTP 200, `text/html`, Apache/2.4.62 + PHP 8.4.3 |
| Administrator login page | **PASS** | HTTP 200 |
| Customer PWA `http://localhost:4173/` | **PASS** | Vite `npm run dev` serving HTML 200 |
| `php artisan serve` | Not used | Per project rule |
| `public/storage` symlink | **PASS** | Points at `storage/app/public` |
| Primary logo media | **PASS** (HTTP) | `GET /storage/website/….png` → 200 `image/png` ~1.6 MB |
| Sanctum CSRF | **PASS** (cookie set) | `GET /sanctum/csrf-cookie` → 204; `XSRF-TOKEN` + session cookie, `SameSite=lax`, not Secure (correct for local HTTP) |
| Full login / authenticated API round-trip | **PASS** (HTTP SPA, local demo) | `customer@coffee.local` + `waiter@coffee.local` via Sanctum CSRF + `/api/v1/auth/login` (DemoUserSeeder). `/auth/me` 200. Admin Blade `admin@coffee.local` dashboard 200. Not a production credential check. |
| CORS PWA origin | **PASS** | OPTIONS from `http://localhost:4173` → `Access-Control-Allow-Origin` + credentials |
| Sanctum stateful / CORS list | **PASS** (config) | localhost, 127.0.0.1, LAN `:4173` present |
| `BROADCAST_CONNECTION=reverb` | **PASS** (config) | Credentials present (values not recorded here) |
| Reverb process | **PASS** after start | Was **down** at first (`localhost:8080` connection refused). Started `php artisan reverb:start --host=0.0.0.0 --port=8080` for this audit |
| `coffee:realtime-health --probe` | **PASS** after start | Dispatch succeeded once Reverb was listening |
| Queue worker | **PASS** (started for this audit) | `php artisan queue:work` drained `jobs` to **0**. `failed_jobs` **28** historical `Driver [email] not supported` (current `mail.default=log`). Keep a worker running for notifications. |
| PWA `VITE_API_BASE_URL` | **PASS** (present) | `http://192.168.29.175/coffee/api/v1` (LAN). Apache verified via `localhost`. Client may rewrite host to the address bar |
| `VITE_REVERB_HOST=localhost` | Watch | Opening the PWA on the LAN IP can mismatch Reverb host; keep one host family |
| Service worker on Vite dev | **PASS** (expected) | `GET /sw.js` on `:4173` is `text/html` (SPA fallback). Dev **unregisters** SW to avoid MIME errors |
| Production `dist/sw.js` | **PASS** (build) | `npm run build` generated SW; **not** served as the live origin during this audit |
| `coffee:launch-readiness` | **BLOCKED** (café data) | Not ready: no payment methods enabled; 3 incomplete products; launch-menu unconfirmed. Required: FAQ body, GSTIN if tax on |

---

## 2. Responsive PWA

**NOT TESTED.** Widths 375 / 390 / 430 / 768 / 1024+ were not opened. Overflow, lockup, cream surfaces, bottom nav, sheets, overlays, keyboard, and sticky bars were not visually confirmed.

Automated UX strings still assert cream tokens, brand lockup, dining shell, and bottom-nav structure (`npm run test:ux`). That is not a substitute for viewport PASS.

---

## 3. Customer / retail flow

| Step | Result |
| --- | --- |
| Guest → Home → Menu → customize → cart → login at checkout | **NOT TESTED** in browser. **PASS** API: catalog 200, authenticated cart add 201 (`unit_price` 4.95 from server), cart show 200 |
| Cart survives auth | **PASS** (PHPUnit `GuestCartMergeTest`); UI merge **NOT TESTED**. Guest cart is client-side until merge (no guest cart HTTP API) |
| Server-authoritative pricing | **PASS** (PHPUnit + live cart line totals) |
| Takeaway / Delivery UI | **NOT TESTED** |
| Payment methods only if executable | **PASS** (API) / **BLOCKED** (café flags) | Checkout summary `payment_methods` takeaway/delivery/dine_in are **empty arrays**. Cash and Manual UPI remain disabled. `/content` still exposes demo UPI copy for display fields. |

---

## 4. Dining flow

Browser click-through of the full table lifecycle: **NOT TESTED**.

REST on Apache (local demo customer/waiter, existing open session `DS-260904-0001` / table T3):

| Step | Result |
| --- | --- |
| Dining tables + availability | **PASS** | `/dining/tables` 14 rows; T1 `available` |
| Start new session | **PASS** (validation) | 422 “already have an open dining session” — correct safeguard |
| Draft + Place Order | **PASS** | draft 200, place 201, `status=open` |
| Waiter Accept | **PASS** | `/waiter/sessions/{id}/rounds/{order}/accept` 200 |
| Preparation tickets / Ready / Served | **NOT TESTED** | Barista/Chef are Blade; not hit |
| Request Bill | **PASS** | 200; session → `awaiting_payment` |
| Payment + auto-close + table free | **BLOCKED** | no executable payment methods |
| Dining + Takeaway coexist | **PASS** (PWA `test:ux`); UI **NOT TESTED** |
| Customer copy “Order” not “Round” | **PASS** (ux tests); UI **NOT TESTED** |
| Close / Reopen click | **NOT TESTED** | PHPUnit still **PASS** |

---

## 5. Realtime

| Item | Result |
| --- | --- |
| Config + `/broadcasting/auth` registered | **PASS** |
| Socket process | **PASS** after starting Reverb |
| Probe dispatch | **PASS** |
| Browser Echo connection / `window.__COFFEE_REALTIME_DIAGNOSTICS__` | **NOT TESTED** |
| HTTP `/broadcasting/auth` without Echo `socket_id` | Expected 500 without a websocket client; Echo **NOT TESTED** |
| Reverb TCP | **PASS** | Process listening; plain HTTP GET `/` → 404 (no upgrade) |
| Dining order → staff update → customer reconcile | **NOT TESTED** (REST accept **PASS**; socket fan-out **NOT TESTED**) |
| Service-request bell | **NOT TESTED** |
| `PublicCacheInvalidated` on connected PWA | **NOT TESTED** in browser. Admin refresh **PASS** (version UUID changed) |
| REST without websocket | **PASS** (health command + Apache API while Reverb was initially down) |

---

## 6. Client cache / offline

**NOT TESTED** in DevTools (IndexedDB, Cache Storage, localStorage metadata-only, CacheFirst images, Admin Refresh Customer Cache → PWA invalidate, offline shell).

| Item | Result |
| --- | --- |
| `GET /api/v1/app-bootstrap` | **PASS** | Returns opaque `cache_version` UUID (catalog/content/media currently same) |
| SW cache in **dev** | N/A | Unregistered by design |
| Public cache PHPUnit | **PASS** (`PublicCacheVersionTest`) |
| CMS/content API | **PASS** HTTP 200 `/api/v1/content` |

---

## 7. Confirmations, selects, branding

| Item | Result |
| --- | --- |
| Shared PWA `ConfirmDialog` / Blade `InternalConfirm` | **PASS** (code + PHPUnit/ux tests) |
| Click: Cancel / once / Working / reason / no `window.confirm` | **NOT TESTED** in UI. Markup **PASS**: Advanced cache forms have `data-confirm-title`; inventory movement create has `data-confirm` + Select2 |
| Select2 / PWA `SearchableSelect` keyboard/positioning | **NOT TESTED** in UI. Select2 present on Advanced settings + inventory create HTML |
| Current logo | **PASS** HTTP; **NOT TESTED** visual (Admin preview, Home lockup, cream vs white rectangle) |
| Upload ~2.5 MB SVG primary logo | **NOT TESTED** | Limit allows 5120 KB. Current stored file is **PNG ~1.6 MB**, not a new SVG. Sanitizer covered by unit/feature tests. Artwork was not replaced |

---

## 8. Console / network (browser)

**NOT TESTED.** No JS exception, 401/419, duplicate catalog, or Select2 console pass/fail.

HTTP from curl: Laravel 200, CSRF 204, bootstrap 200, content 200, logo 200, CORS preflight 204.

---

## Blockers to treat as launch/ops, not silent PASS

1. **Payment methods disabled** in Website Settings — live checkout/dining payment cannot advertise executable methods until café enables Cash and/or Manual UPI (real UPI values, not demo).
2. **Queue worker** must stay running in local/prod; historical `failed_jobs` used invalid mail driver `email` (now `log`).
3. **Launch readiness NOT READY** — incomplete products, FAQ, GSTIN, unconfirmed launch menu (café data; do not invent).
4. **Browser matrix not run** — responsive, overlays, IndexedDB/offline, Echo, visual logo, production SW, confirm *click* (cancel/once/loading).
5. **Production HTTPS / cookie domain** still not a live host (see `docs/pwa-launch-checklist.md` D4).
6. Demo customer session `DS-260904-0001` was left **awaiting_payment** after Request Bill (no pay path).

## Continuation (19 Sep 2026 evening)

Started `queue:work`. Exercised Apache HTTP (not a browser): Sanctum login, cart, checkout methods empty, dining draft/place, waiter accept, request bill, admin cache refresh + inventory confirm/select markup. Reverb still running. No application code changes. No `migrate:fresh`.

## Automated results (this audit)

- PHPUnit (narrow set): `PublicCacheVersionTest`, `DiningCloseReopenConfirmationTest`, `GuestCartMergeTest`, `CustomerCheckoutTest`, `AdministratorWebsiteSettingTest`, `CafeAvailabilityTest`, `BrandLogoSvgSanitizerTest`, `CustomerWebsiteContentApiTest`, `PublicMediaTest` — **62 passed**.
- Pint: not required (no PHP edits).
- PWA: `npm run test:ux` **38 passed**, `typecheck` **passed**, `npm run build` **passed**.
