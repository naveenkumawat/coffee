# Pre-launch integration smoke audit

Date: 20 September 2026 (local, before L-data)  
Scope: `APP_ENV=local`. Laravel via Apache (`http://localhost/coffee/`). Customer PWA Vite `http://localhost:4173/` (`npm run dev`).  
Method: headless Chrome screenshots of real pages; Apache/Sanctum HTTP; `coffee:realtime-health --probe`; PHPUnit / Pint / PWA scripts. **No Dusk.** DevTools Application (IndexedDB / Cache Storage / SW) was **not** opened. Clicks (confirm dialogs, selects, UTR form, staff workflow) were **not** driven.

Legend: **PASS** · **FAIL** · **NOT TESTED** · **BLOCKED**

No application code was changed. Frozen business rules were not modified. `migrate:fresh` was not run. Dining was toggled ON then restored **OFF** (starting local state) during the cache-version check.

---

## 1. Environment / branding

| Check | Result | Notes |
| --- | --- | --- |
| Apache Laravel `http://localhost/coffee/` | **PASS** | HTTP 200, Apache/2.4.62 + PHP 8.4.3. `php artisan serve` not used. |
| Administrator login page | **PASS** | Chrome screenshot 1024×768; Blade login 200. Demo `admin@coffee.local` dashboard 200. |
| Customer PWA `:4173` | **PASS** | Vite HTML 200. |
| `public/storage` symlink | **PASS** | → `storage/app/public` |
| Current logo HTTP | **PASS** | PNG RGBA 1566×1604, `GET /storage/website/52711a99-….png` 200 ~1.6 MB |
| Logo on Home 375 | **PASS** | Cream/taupe page; illustrated PNG lockup; **no white rectangle** behind transparency |
| SVG / PNG **upload** in Admin | **NOT TESTED** | Would replace café branding; not done |
| Display mode | Settings `logo` | 375 used the PNG. 768/1024 Home was covered by the campaign modal; lockup not fully visible |
| Fraunces / Poppins network load | **NOT TESTED** (DevTools fonts) | CSS `@import` present; headings vs body look distinct at 768 |
| Cream surfaces | **PASS** (375/390 Menu/Cart) | Warm cream `#`-family page; Home darkened by closed-hours + campaign overlay |
| Sanctum CSRF | **PASS** | `/sanctum/csrf-cookie` 204; SPA login 200 with `Origin: http://localhost:4173` |
| CORS PWA origin | **PASS** (prior + this session) | |
| `BROADCAST_CONNECTION=reverb` | **PASS** | Reverb listening `:8080` |
| `coffee:realtime-health --probe` | **PASS** | Probe dispatched |
| Cafe hours at audit time | **BLOCKED** for live orders | `/content` `availability.available=false`, `outside_hours` — “Opens today at 8:00 AM” |
| `coffee:launch-readiness` | **BLOCKED** (café data) | Blockers: Manual UPI enabled but **QR image missing**; 2 incomplete active products; launch-menu unconfirmed. Required: FAQ body; draft Rose Latte incomplete |
| Queue | **PASS** empty `jobs` | `failed_jobs` **33** historical (do not treat as this audit’s new failures) |

---

## 2. Public cache

| Check | Result | Notes |
| --- | --- | --- |
| `GET /api/v1/app-bootstrap` | **PASS** | `cache_version` UUID + `dining_enabled` |
| localStorage metadata-only | **NOT TESTED** | No Application tab |
| IndexedDB public JSON | **NOT TESTED** | |
| Cache Storage media/shell | **NOT TESTED** | Vite **dev unregisters SW**; `GET :4173/sw.js` is HTML. Production `dist/sw.js` built this audit |
| Reload reuse / CacheFirst media | **NOT TESTED** | |
| Admin **Refresh Customer Cache** markup | **PASS** | `data-confirm-title` present; **no** `window.confirm` in page HTML |
| Refresh click / PWA IDB purge | **NOT TESTED** | |
| Dining save bumps version | **PASS** (HTTP) | OFF→ON `478c306f-…` → `101652ed-…`, `dining_enabled` true; restore OFF → new UUID, `dining_enabled` false |
| Auth/cart/dining survive refresh | **NOT TESTED** in browser | |

---

## 3. Dining toggle

Local setting after audit: **`fulfilment_dine_in_enabled=0`**.

| Check | Result | Notes |
| --- | --- | --- |
| Dining OFF footer (guest) | **PASS** (Chrome 390/768/1024) | No Dining item. 390: Home / Menu / Cart / Sign in (pixel sample on 4th column). 768/1024: Sign in clearly visible. Grid even; no empty Dining slot |
| Guest `/dining` | **PASS** (screenshot) | Protected → login (not table picker) |
| Dining ON footer / `/dining` start UI | **NOT TESTED** in browser | Toggled ON only via Admin HTTP; PWA not recaptured while ON |
| New session when OFF | **PASS** (API) | `POST /dining/sessions` **422** `errors.dining`; tables message “Dining is disabled.” |
| New session when ON | **NOT TESTED** | Customer session cookies were flaky without `Origin`; cafe **closed** would also block |
| PWA reconcile after version bump | **NOT TESTED** | Focus/IDB not observed |
| Stale `orderingMode=dining` → takeaway | **PASS** (`npm run test:ux`); UI **NOT TESTED** | |
| Active session while Admin OFF | **NOT TESTED** | Would need an open session + browser |

---

## 4. Manual UPI

| Check | Result | Notes |
| --- | --- | --- |
| Method advertised | **PASS** (API) | Checkout `payment_methods.takeaway/delivery` include `manual_upi` `available: true` |
| QR image | **BLOCKED** | Launch-readiness: Manual UPI on, QR path empty. `/content` `payment.qr_image_path` null. Demo UPI id still in content |
| Place Takeaway + UTR UI | **NOT TESTED** | Cafe closed; no checkout click-through |
| UTR lock / reject / verify / accept / Paid vs Pending | **PASS** PHPUnit `ManualUpiTransactionIdTest`; UI **NOT TESTED** | |

---

## 5. Retail

| Check | Result | Notes |
| --- | --- | --- |
| Guest Home | **PASS** (375/768/1024 screenshots) | Closed banner + campaign popup |
| Menu 390 | **PASS** | Search, categories, flavours, list, 4-item footer. Cards visually muted (closed overlay) |
| Product customization sheet | **NOT TESTED** | No click |
| Cart empty 390 | **PASS** | Empty state + footer |
| Checkout login / Takeaway UPI / lifecycle | **NOT TESTED** | Cafe closed |
| Guest cart merge | **PASS** PHPUnit `GuestCartMergeTest`; UI **NOT TESTED** | |
| Server pricing | **PASS** (API) | Authenticated cart line `unit_price` 4.75 |

---

## 6. Dining / realtime

| Check | Result | Notes |
| --- | --- | --- |
| Full table → bill → pay → close | **NOT TESTED** | Dining OFF + cafe closed |
| Copy “Order” not “Round” | **PASS** ux tests; UI **NOT TESTED** | |
| Dining + Takeaway coexist | **PASS** ux tests; UI **NOT TESTED** | |
| Waiter Call Waiter / Served | **NOT TESTED** | |
| Browser Echo / `__COFFEE_REALTIME_DIAGNOSTICS__` | **NOT TESTED** | |
| WS staff→customer events | **NOT TESTED** | |
| REST without depending on WS | **PASS** | APIs worked; health probe OK |
| `PublicCacheInvalidated` on connected PWA | **NOT TESTED** | Version bump **PASS** over REST |

---

## 7. Confirmations, selects, responsive, console

| Check | Result | Notes |
| --- | --- | --- |
| Click Request Bill / Close / Verify / cancel / inventory / cache | **NOT TESTED** | Cache page uses `InternalConfirm` attributes |
| Searchable Select2 keyboard | **NOT TESTED** | |
| Viewports 375 / 390 / 768 / 1024 | **PASS** (screenshots) | No crash; cream shell; footer present |
| 430 | **NOT TESTED** | Chrome job hung; skipped |
| Checkout / Dining Bill / Orders / Order detail / Account (authenticated) | **NOT TESTED** | Account URL is login for guests |
| JS console / 401/419/404/500 / CORS / WS / SW / IDB | **NOT TESTED** | Headless screenshot only |
| HTTP from tools | **PASS** | Laravel 200, CSRF 204, bootstrap/content 200, logo 200 |

---

## Blockers (ops / data, not silent PASS)

1. **Café closed** at audit time (`outside_hours`) — live retail/dining orders cannot complete.
2. **Dining globally OFF** after restore — correct for current settings; ON-path footer was not screenshotted.
3. **Payment QR missing** while Manual UPI is enabled — launch-readiness blocker.
4. **Incomplete catalog / FAQ / launch-menu** — café L-data, do not invent.
5. **Browser matrix incomplete** — no DevTools cache, no Echo, no UTR/staff clicks, no 430, no authenticated PWA session in Chrome.
6. **Production HTTPS / SW origin** still not this environment (Vite dev has no SW).

## Automated results (this audit)

- PHPUnit: `PublicCacheVersionTest`, `DiningSessionTest`, `ManualUpiTransactionIdTest`, `GuestCartMergeTest`, `DiningCloseReopenConfirmationTest`, `CustomerWebsiteContentApiTest` — **39 passed**.
- Pint `--dirty` — **passed** (no PHP edits).
- PWA: `npm run test:ux` **41 passed**, `typecheck` **passed**, `npm run build` **passed**.

---

## Completion pass — 20 September 2026 (later)

Scope: remaining browser smoke only. Café hours temporarily 00:00–23:59 and Dining ON via `CafeAvailabilityService::syncWeeklyHours` + `WebsiteSettingService::update`; originals restored (Dining **OFF**, hours 08:00–23:50, `outside_hours` at ~02:30 IST). Smoke dining session **11** (table T1) closed; pre-existing open sessions 2,5,6,7,8,9,10 left intact. `migrate:fresh` not run. No launch QR/data entered. Temporary CORS `:4174` patched then restored from `.env` backup.

Method: real Chrome (puppeteer-core + system Chrome) against Vite `:4173` and Admin/Barista Apache. Production `npm run preview` attempted on **127.0.0.1:4174** after `customer-pwa` `npm run build` (`dist/sw.js` generated).

Legend only: **PASS** · **FAIL** · **NOT TESTED** · **BLOCKED**

| Area | Result | Notes |
| --- | --- | --- |
| Authenticated retail (guest cart → login → checkout Takeaway → Orders) | **FAIL** | Guest add **PASS** (Cappuccino, cart badge 1). Login as `empty@coffee.local` showed toast “Signed in, but your guest cart could not be merged yet.” Cart empty; footer still Sign in. Checkout/Takeaway/order **NOT TESTED** after that. |
| Manual UPI lifecycle | **FAIL** | Never reached UTR UI (blocked by retail login/merge). PHPUnit not counted as browser PASS. QR still **BLOCKED** (launch-readiness, no invented QR). |
| Dining full table → bill → pay → auto-close | **FAIL** | Dining ON table picker **PASS** (430, T1 selected, Account footer). Session 11 started then closed on restore. Place order CTA not reached (drafts not on session). Bill/pay/auto-close **NOT TESTED**. |
| Dining OFF during active session | **NOT TESTED** | Did not reach Admin Dining OFF while a customer session stayed open in Chrome. |
| Realtime WS → REST refetch | **FAIL** | `__COFFEE_REALTIME_DIAGNOSTICS__` `connection_state=disconnected` (also `idle` earlier). Focus/reload without WS **NOT TESTED** as a deliberate isolated check. |
| Production SW / IDB / Cache Storage / offline | **FAIL** | `vite preview :4174` returned **HTTP 404** for `/` and `/index.html` despite `dist/index.html` present. Application tab **NOT TESTED**. Vite **dev still unregisters SW** on 4173. Admin Refresh Customer Cache **click PASS** (see confirmations). Auth/cart survive refresh **NOT TESTED**. |
| Confirmations | **PASS** | Chrome: Cache Management Cancel (no mutation) then Confirm; inventory movement Cancel. `#internalConfirmModal`, not `window.confirm`. Request Bill / UPI Verify / Dining Close / order cancel **NOT TESTED** (flows not reached). |
| Searchable selects | **PASS** | Admin Select2: ingredient, product category, flavour, customer, product filter — type/Enter. Waiter table SearchableSelect **FAIL** (waiter login `input[name=login]` timeout). Staff user select **NOT TESTED** as a dedicated users-form control. |
| Responsive 375/390/430 + 768/1024 | **PASS** | Screenshots of Menu/Checkout/Orders/Account/Dining at 375/390/430 and Menu 768/1024. Authenticated checkout/order-detail/bill **NOT TESTED**. Campaign overlay still covers first menu paint. |
| Console/network | **FAIL** | Staff notifications requested `http://localhost/api/v1/notifications` (404) under Apache `/coffee` — **fixed** in `resources/js/notifications/api.js` to use `window.__COFFEE_OPS_NOTIFICATIONS__.apiBase`. Remaining: `/build/assets/notification-chime-*.wav` **404** (missing `/coffee` prefix); `/coffee/_boost/browser-logs` **500**; guest `auth/me` **401** expected. |
| Restore | **PASS** | Dining OFF; hours 08:00–23:50; env CORS without 4174; session 11 closed. |

### Defects fixed this pass

- Internal ops notification client ignored Blade `apiBase` and called origin-root `/api/v1/notifications` (404 on subdirectory Apache). Now uses `__COFFEE_OPS_NOTIFICATIONS__.apiBase`.

### Automated (this pass — not browser PASS)

- Pint `--dirty` passed.
- PHPUnit `OperationalNotificationLifecycleTest::test_pwa_and_blade_notification_client_foundation_exists`, `StaffOperationalNotificationTest::test_administrator_header_shows_operational_notification_bell` passed.
- PWA `npm run test:ux` 45 passed; `typecheck` passed; `customer-pwa` `npm run build` passed (`sw.js` cache `8afecd035bc4`).

### Remaining blockers

1. Live retail/UPI/dining Chrome lifecycles incomplete (guest-cart merge toast; UTR/staff not clicked).
2. Manual UPI QR missing (do not invent).
3. Production preview on 4174 404 — SW/offline **FAIL** until preview serves `dist`.
4. Echo disconnected in the PWA tab used for diagnostics.
5. Notification chime 404 under `/coffee` subdirectory.
6. Café closed again after hours restore (`outside_hours`).
