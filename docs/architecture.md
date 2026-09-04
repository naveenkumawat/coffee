# Coffee Architecture

## Canonical Frontend Architecture

Internal surfaces:

- Administrator = Laravel Blade
- Operator = Laravel Blade
- Barista = Laravel Blade
- Chef = Laravel Blade
- Waiter = Laravel Blade

Customer surface:

- Customer = React + Vite + TypeScript mobile-first PWA
- Laravel provides the `/api/v1` REST API transport and business backend for the customer app
- The customer PWA consumes the API and is the canonical long-term storefront architecture
- Existing customer Blade auth/account/cart/checkout flows are transition/foundation work only
- Existing customer Blade customer work must not be expanded as the final storefront architecture, but it should not be deleted yet

## Target Request Flows

Customer PWA:

```text
React + Vite + TypeScript PWA
  -> Laravel /api/v1 API controllers
  -> existing Services
  -> Repositories
  -> Models / Database
```

Internal panels:

```text
Administrator / Operator / Barista / Chef / Waiter Blade
  -> controllers
  -> existing Services
  -> Repositories
  -> Models / Database
```

Role flow (operations):

```text
Customer places order
  -> Administrator reviews payment / config
  -> Operator accepts order + payment ops + dining oversight
  -> Preparation tickets open by station
       -> Barista works BAR queue
       -> Chef works KITCHEN queue
  -> Operator / Waiter complete handoff / dining close-out
```

Transition rules:

- Blade and API may call the same existing Services during migration.
- API controllers are a thin transport layer and must not fork or duplicate domain workflows.
- Do not introduce parallel `Api\*Service` classes that re-implement Product, Cart, Order, Checkout, or similar business logic.
- Reuse existing Product, Cart, Order, Checkout, and related Services as the shared domain layer for both internal Blade and customer API entry points.

## Customer PWA Requirements

- Mobile-first and touch-first UX is the primary requirement.
- Installable PWA behavior should be supported where the browser/device allows it.
- The customer app should use a manifest, service worker, and offline shell.
- The canonical implementation target is React + Vite + TypeScript, not Blade-rendered customer pages.
- Responsive desktop fallback is still required, but it is secondary to smartphone UX.
- Offline support must never treat sensitive or live customer/account/order/payment data as the source of truth.
- The backend remains authoritative for authentication, pricing, availability, cart state, checkout validation, payment state, and order state.

## Customer PWA Foundation Docs

- Scope reference: [pwa-scope.md](./pwa-scope.md)
- Theme mapping reference: [pwa-theme-map.md](./pwa-theme-map.md)

Theme usage rules:

- The Ombe theme under `theme/pwa/ombe-bootstrap-pwa.vercel.app` is a planning and design reference only.
- Do not treat the static Bootstrap/jQuery theme as the target runtime architecture.
- Rebuild the selected patterns as React components over the existing Laravel API.

## Internal architecture patterns

- Module-first folder grouping across repositories, services, transfers, and parsers.
- Class and interface pairs grouped together inside the same module folder.
- Thin HTTP controllers and dedicated Form Requests.
- Shared abstract foundations such as abstract request, model, repository, parser, and transfer classes.
- Dedicated providers for each architectural binding concern.
- Parser classes that translate request or model data into transfer objects.
- Transfer objects used as the structured handoff between layers.
- Role-based controllers, routes, and views, with shared business logic living outside role folders.
- Events and listeners used for side effects instead of pushing those concerns into controllers.
- Customer transactional notifications: domain Event (`ShouldDispatchAfterCommit`) → Listener → `CustomerNotificationDispatcher` → Email (`ShouldQueue` notification) and/or WhatsApp (`SendCustomerWhatsAppMessage` job via `WhatsAppNotificationProvider` / `MetaWhatsAppCloudProvider`). Idempotent per `customer_notification_logs` unique key **and channel** (`email` / `whatsapp`). WhatsApp uses Meta Cloud API templates only (`config/services.php` + `WHATSAPP_*` env); public café WhatsApp stays in Website Settings. Channel failures must not roll back business operations or block the other channel. CTAs use `CUSTOMER_APP_URL` / `COFFEE_PWA_URL` (`config('coffee.pwa.url')`).
- Staff operational notifications: domain events (`ShouldDispatchAfterCommit`) → dedicated `StaffNotificationDispatcher` (not coupled to customer dispatcher) → Laravel database notifications + selective staff email. Covers order ops and inventory/refill ops via `StaffNotificationContext`. Idempotent via `staff_notification_logs` (`unique_key` + `user_id` + `channel`). Stock alerts fire only on `InventoryStockStatus` transitions (not every ledger write); episode keys include inventory transaction id so healthy→low can alert again later. Audience by active role cohorts (`owner`/`manager`, `operator`, `barista`, `chef`, `waiter`). In-app bell in shared internal header with severity metadata; staff WhatsApp channel enum reserved but not enabled. Delivery failure must not roll back order/payment/inventory workflows.
- **Realtime (R1):** self-hosted Laravel Reverb + Echo. Broadcasting default may be `log`/`null` when Reverb is down — REST remains authoritative. Private channels authorized in `routes/channels.php` for `user.{id}`, staff `role.*` (administrator = owner|manager), plus R1.6 `dining-session.{id}` / `table.{id}`. Event listeners are registered explicitly in `EventServiceProvider::$listen`; framework listener discovery is disabled (`withEvents(discover: false)`) to prevent double-firing. Shared Blade bootstrap: `resources/views/internal/partials/realtime-bootstrap.blade.php` + Vite `resources/js/realtime.js` + `resources/js/notifications.js`. PWA: singleton `customer-pwa/src/realtime/RealtimeConnection.ts` (StrictMode-safe; disconnect on logout) and `notificationStore` / `api/notifications.ts`. Probe event `RealtimeConnectionProbe` proves auth/delivery with a minimal DTO.
- **Operational notifications (R1.2 + R1.3A + R1.3B + R1.4 + R1.5 + R1.6 + R1.7 + L1.1):** Domain tables `operational_notifications` + `operational_notification_recipients` are distinct from Laravel’s `notifications` table (legacy staff DB bell retained as mark-legacy-read only). R1.3A wires domain events → `OperationalBusinessNotificationPublisher`. R1.3B adds shared UI: Blade `resources/js/notifications/*` (store, reminder, sound, tab leader, drawer/toasts) loaded for all internal panels; waiter PWA bell/drawer foundation. Reminder policy: 30s interval constant; eligible actionable types only; leader tab alone presents sound/reminder toast and POSTs `/reminded`. ACK: delivered on socket, seen when visible, read on open, acknowledge on explicit button — dismiss toast ≠ resolve. Reconnect/online/visibility sync uses REST as authority (R1.5 coalesces duplicate sync triggers; client dedupes socket event ids). **L1.1:** dining Ready-to-Serve resolves on Served / Completed / Cancelled / Rejected (`orders.served_at` / `served_by_user_id` per round; Preparation Ready ≠ Served ≠ Completed). **R1.4** adds customer-facing `customer.*` types. **R1.5** adds advisory staff presence (`presence-ops` + TTL heartbeat; unique-user counts), no-staff escalations for missing Barista/Chef/Waiter online, and inventory/refill operational notifications + `.inventory.ops` role signals. **R1.6** adds scoped `dining-session.{id}` / `table.{id}` channels and `DiningRealtimePublisher` `.dining.ops` signals (including `round.served`). **R1.7** hardens ops: `coffee:realtime-health`, client `__COFFEE_REALTIME_DIAGNOSTICS__`, reconnect soft-reload for station/order/dining/inventory Blade pages, runbook + smoke matrix; Web Push still deferred.

## How Coffee follows them

- Repository interfaces and implementations are grouped by module in `app/Repositories/Menu`.
- Service interfaces and implementations are grouped by module in `app/Services/Menu` and `app/Services/Auth`.
- Transfer interfaces and implementations are grouped by module in `app/Transfers/Menu`.
- Parser interfaces and implementations are grouped by module in `app/Parsers/Menu`.
- Requests are grouped by module in `app/Http/Requests/MenuCategory` and `app/Http/Requests/MenuItem`, preferring module grouping over role-grouped request folders.
- Shared abstract foundations now exist in `app/Models/AbstractModel`, `app/Http/Requests/AbstractRequest`, `app/Repositories/AbstractRepository`, `app/Parsers/AbstractParser`, and `app/Transfers/AbstractTransfer`.
- Separate providers now register repositories, services, parsers, and transfers.

## Module Grouping Convention

Related contracts and implementations must live together by business concern, not in flat global contract folders.

Example:

```text
app/
├── Http/
│   └── Requests/
│       ├── MenuCategory/
│       │   ├── MenuCategoryCreateRequest.php
│       │   └── MenuCategoryUpdateRequest.php
│       └── MenuItem/
│           ├── MenuItemCreateRequest.php
│           └── MenuItemUpdateRequest.php
├── Repositories/
│   └── Menu/
│       ├── MenuCategoryRepositoryInterface.php
│       ├── MenuCategoryRepository.php
│       ├── MenuItemRepositoryInterface.php
│       └── MenuItemRepository.php
├── Services/
│   └── Menu/
│       ├── MenuCatalogServiceInterface.php
│       ├── MenuCatalogService.php
│       ├── MenuCategoryServiceInterface.php
│       ├── MenuCategoryService.php
│       ├── MenuItemServiceInterface.php
│       └── MenuItemService.php
├── Transfers/
│   └── Menu/
│       ├── MenuCategoryTransferInterface.php
│       ├── MenuCategoryTransfer.php
│       ├── MenuItemTransferInterface.php
│       └── MenuItemTransfer.php
└── Parsers/
    └── Menu/
        ├── MenuCategoryParserInterface.php
        ├── MenuCategoryParser.php
        ├── MenuItemParserInterface.php
        └── MenuItemParser.php
```

Future modules such as `Ingredient`, `Inventory`, `Recipe`, `Order`, `Customer`, and `Payment` should follow the same grouping.

## Role-Specific vs Shared Responsibility

- Role-specific:
  - controllers
  - routes
  - views
  - route middleware application
  - panel entry and response orchestration
- Shared:
  - repositories
  - services
  - transfers
  - parsers
  - events and listeners
  - domain exceptions

Administrator and Barista may call the same shared business layer, but they should not receive duplicated role-specific business services if the underlying rule set is the same.
The customer API should follow the same shared business-layer rule rather than creating a second domain layer for mobile endpoints.

## Internal Button/UI Convention

- All Administrator and Barista page, form, filter, and toolbar actions must use the shared internal button system.
- Use `resources/views/components/internal/button.blade.php` for standalone internal buttons.
- Use `resources/views/components/internal/button-group.blade.php` for related actions such as `Search + Reset`, `Save + Cancel`, `Back + Edit`, and single-toolbar create actions when they should stay visually consistent with grouped controls.
- Keep table row actions inside the shared `x-internal.action-dropdown` component. Do not replace row actions with visible button groups.
- Semantic variants are fixed:
  - `success` for positive primary actions such as create, save, update, and search
  - `dark` for neutral actions such as back, cancel, and reset
  - `danger` for destructive actions
- Internal button styling must stay centralized in shared internal assets such as `public/internal/assets/css/custom.css`. Do not introduce module-specific button CSS or inline button styles.
- If a new internal screen needs a button pattern that does not fit the shared component API, update the shared component first instead of hand-coding a one-off button implementation in the view.

## Internal Role Naming Convention

- Coffee's canonical internal management convention is `Administrator`, not `Admin`.
- Coffee must keep role-specific PHP namespaces, controller folders, Blade folders, and route files under the `Administrator` convention.
- Do not create a parallel `Admin` namespace or Blade tree beside the existing `Administrator` structure.
- The `admin` auth guard name is intentional and may remain different from the folder name because it is an authentication identifier, not an architectural namespace.
- The internal URL prefix also intentionally remains `/administrator`.

## Request, Parser, Transfer, Service Flow

The default write flow should be:

```text
Controller
  -> Form Request
  -> Parser
  -> Transfer
  -> Service
  -> Repository
  -> Model / Database
```

For current menu writes:

- the controller receives a validated request
- the parser converts the validated array into a transfer object
- the service owns the transaction and business orchestration
- the repository performs the persistence operation
- events/listeners or the service handle cache side effects

## Repository and Service Boundaries

- Repositories own reusable query composition, option lists, persistence helpers, and delete/write operations shared by services.
- Services own transactions, business workflows, and side effects that matter to application correctness.
- Controllers should not execute direct persistence or repeated query lookups when those belong in repositories or services.
- Models should keep casts, relationships, and scopes, not workflow logic.

## Dependency Injection and Bindings

- `RepositoryServiceProvider` binds repository contracts.
- `DomainServiceProvider` binds business service contracts.
- `TransferServiceProvider` binds transfer contracts.
- `ParserServiceProvider` binds parser contracts.
- Controllers, listeners, and dependent services should type-hint contracts rather than concrete implementations wherever the contract exists.

## Current Coffee Foundations Applied

- Menu category and menu item writes now pass through parser and transfer layers.
- Menu request classes are organized by module, not by role.
- Current menu controllers are thinner and do not directly delete records.
- Menu form option lookups remain centralized in repositories.
- Shared internal UI remains separate from the customer storefront.
- Existing event/listener cache invalidation remains intact.

## Internal panel UI system

Administrator, Operator, Barista, Chef, and Waiter share one internal design system and shell (`internal.layouts.default`, shared header/sidebar/components, and `public/internal` assets). Role wrappers (`administrator.layouts.default`, `operator.layouts.default`, `barista.layouts.default`, `chef.layouts.default`, `waiter.layouts.default`) only set the panel context.

**Invariant:** same feature + same role-allowed data ⇒ same UI component/layout/interaction. Role differences come from permissions, navigation, allowed data, and allowed actions — not duplicate visual implementations.

- Shared primitives: page header, breadcrumbs, metric cards, filter bars, buttons/button groups, status badges, action/invoice dropdowns, empty states, notification bell, order detail shell (`resources/views/internal/orders/partials/`), dining primitives (`resources/views/internal/dining/partials/`), preparation queue cards (`resources/views/internal/preparation/`).
- Role-specific: nav entries; Administrator financial/config/user/promotion/referral controls; Operator order/dining/payment ops + preparation overview; Barista BAR preparation queue (+ optional catalog/inventory visibility); Chef KITCHEN preparation queue; Waiter dining ops (tables/sessions/bill/cash/invoice) without admin config surfaces.
- Never treat UI hiding as the security boundary; middleware and policies remain authoritative.

## Naming Conventions

- Interface names end with `Interface`.
- Requests use `CreateRequest` and `UpdateRequest` naming by module.
- Transfers use `<Entity>Transfer` and `<Entity>TransferInterface`.
- Parsers use `<Entity>Parser` and `<Entity>ParserInterface`.
- Repositories use `<Entity>Repository` and `<Entity>RepositoryInterface`.
- Services use `<Entity>Service` and `<Entity>ServiceInterface`.

## Order abuse / fake-order protection

Checkout protection is layered server-side (CAPTCHA / Turnstile intentionally deferred until production bot abuse is evidenced):

1. Authenticated checkout (Sanctum / session)
2. Account ordering permission (`ordering_blocked`)
3. Checkout attempt + successful-order rate limits
4. Café availability (manual override → scheduled closure/holiday → weekly hours)
5. Open unpaid / pending-payment order limit
6. `checkout_token` idempotency + short-window duplicate fingerprint
7. Normal checkout validation
8. Order creation (notifications only for successful creates)

Limits live in Website Settings → Order Security. Trusted cash (`cash_takeaway_allowed`) is independent and never bypasses these controls.

## Promotions / discounts

`PromotionService` is the sole discount engine. Cart, checkout, order creation, invoices, and the PWA must consume its results — never recompute discounts in React or Blade.

Canonical pricing sequence (merchandise only; delivery fee is never discounted in the initial scope):

1. Eligible merchandise line subtotals
2. Promotion discounts (automatic + optional coupon)
3. Taxable amount → `TaxCalculator` (inclusive or exclusive GST from Website Settings)
4. Delivery fee (undiscounted when present)
5. Payable / café total

### Stacking policy

- Duplicate application of the same promotion is never allowed.
- If every selected candidate is `stackable = true`, all apply (priority, then amount).
- Otherwise the customer receives a single best discount by amount (then priority). Coupons compete in the same candidate set as automatic offers.
- Default for new promotions: `stackable = false`.

### Eligibility & usage

Eligibility uses café/business timezone (`CafeAvailabilityService` / `business_timezone`), not the browser. Coupons normalize to uppercase exact match. Usage counts `order_promotions` joined to orders excluding cancelled/rejected; checkout locks promotions when enforcing limits. Historical amounts live on `orders.discount_total` and `order_promotions` snapshots — editing or deleting a promotion never rewrites past orders.

Administrator manages offers under **Offers & Promotions**. Barista may see customer-facing discount lines on orders/invoices but cannot manage promotions.

## Customer referrals / rewards

`ReferralService` owns referral codes, qualification, and reward snapshots. Website Settings → **Customer referrals** configures the program (`referral_*` keys). Changing reward settings only affects **newly earned** rewards — existing `customer_rewards` rows keep product/coupon snapshots.

Lifecycle:

1. Customer receives a unique `users.referral_code` (generated on register / account summary).
2. Friend registers with `?ref=` / `referral_code` → `customer_referrals` + `referred_by_user_id` (immutable).
3. Friend’s first qualifying paid purchase (`OrderStatusChanged` → `PaymentConfirmed`, `OrderCashReceived` when payment becomes confirmed after Accept, or paid `DiningSession`) → referrer earns one `customer_rewards` row (idempotent). Dining qualifies **once per session**, never per round. Anonymous/walk-in dining does not qualify.
4. Cart applies at most **one** referral reward (free drink **or** coupon).
5. Checkout redeems atomically into `order_reward_redemptions` and marks the reward redeemed. Failed checkout leaves the reward available.

Rewards snapshot `expires_at = earned_at + referral_reward_redemption_duration_days` at earn time (settings changes do not rewrite existing expiry). Active customer Rewards lists only `available` and unexpired rewards — server revalidates on add-to-cart and checkout (no cron required). Expired rewards in a cart are cleared without consuming them.

### Free drink GST rule

- GST basis = merchandise after normal promotions **and** referral coupons (not reduced by free drink).
- Payable merchandise = GST basis − free drink benefit.
- Exclusive: café total = payable + tax(on GST basis). Inclusive: free drink benefit = line − inclusive tax component; café total = payable.
- `discount_total` on the order includes promo + referral coupon only; free drink benefit is a separate redemption line (GST still payable).

Administrator **Referrals** index is read-only (`canManageWebsiteSettings`). Barista cannot manage referrals.

## Café availability / operating hours

`CafeAvailabilityService` is the single source of truth for whether **new** orders may be placed.

Priority:

1. Manual Out of Service (indefinite or `closed_until`, evaluated live — no cron required to reopen)
2. Active scheduled closure / holiday window
3. Recurring weekly operating hours (multiple intervals/day supported)

Closing ordering never cancels or pauses existing orders. Menu browsing, carts, account, and invoices stay available. Customer-safe status is exposed on `GET /api/v1/content` (`availability`) and `GET /api/v1/cafe-availability`. Business timezone defaults to `Asia/Kolkata` (`business_timezone` / `config('coffee.timezone')`) — never the browser timezone.

- Coffee does not use a global root interface and factory graph because the current Laravel container can resolve the same seams more simply.
- Coffee has not added empty `Factories`, `Integrations`, `Tools`, `Jobs`, or domain-specific exception trees solely for structural parity; those should be added when a real shared use case arrives.
- Coffee keeps Laravel-native model factories and provider registration rather than wrapping all construction in a custom root object.

## Rules for Future Agents and Developers

- Do not add new business modules directly through controllers or large models.
- Do not create flat global `Interfaces/` folders.
- Group related classes and interfaces by module.
- Add Form Requests for write actions.
- Add parser and transfer classes when a module introduces a business-layer boundary.
- Put repeated queries and option sourcing in repositories.
- Put transactions and workflows in services.
- Add new providers only when a new architectural layer needs bindings.
- Keep public storefront behavior separate from internal panel architecture.
- Do not treat current customer Blade views/controllers as the final storefront architecture.
- Treat the customer API/PWA migration as pending work until the PWA replaces temporary customer Blade screens.


## Dining / table service

Separate from takeaway/delivery checkout:

1. Customer or waiter opens a **dining session** on a cafe table.
2. Items are collected in a **session draft** (not the takeaway cart), then placed as **rounds** (orders with `fulfilment_method=dine_in`, `dining_session_id`, `dining_round_number`).
3. Kitchen/bar starts on **Accepted** rounds without requiring payment first.
4. At the end: request bill → session payment (cash or UPI proof) → close session (frees table).

**Financial authority (Phase F1):** Dining **rounds** are preparation/operational units only (item price snapshots, station tickets). The **dining session** is the sole billing, payment, and revenue unit (`dining_sessions.*_amount`, `payment_status`). Dining automatic promotions apply **once** at final bill (`dining_session_promotions`), never per round. After `bill_generated_at`, displays/invoices/payment use session snapshots — `runningBill()` is open-session preview only. Round `payment_status` is subordinate/non-revenue; do not treat rounds as independent paid sales.

**Inventory authority (Phase F2):** Order / dining **round** acceptance consumes recipe ingredients exactly once (`OrderInventoryConsumptionService` → `sale_consumption` ledger + `order_inventory_consumptions`). Preparation tickets never consume or restore stock. Early cancel/reject before any ticket is Preparing/Ready writes `sale_reversal` from original quantities; mixed-station partial prep blocks auto-restore. Dining bill/payment/close do not affect inventory. Historical orders are not backfilled.

**Dining cancellation / exceptions (L1.2):** `DiningRoundCancellationPolicy` is the single matrix for dining rounds. Pre-material-prep cancel may reverse stock (F2). After Preparing/Ready timestamps, privileged Operator/Admin cancel with structured reason does **not** auto-restore stock. Served rounds cannot use Cancel (served_at/by preserved); void/comp/refund/wastage adjustment remain deferred. Bill-frozen and payment-confirmed sessions block ordinary round cancellation. Capabilities (`can_cancel`, `cancel_requires_reason`, `can_void`, `cancellation_blocked_reason`) are server-derived on Waiter session resources.

**Reporting authority (Phase F3.1):** Retail revenue = confirmed Takeaway/Delivery orders (not Cancelled/Rejected). Dining revenue = confirmed Dining Session snapshots only. Dining rounds never contribute to revenue totals. All money/GST/discount figures come from stored transactional snapshots — never current Website Settings. `FinancialReportingService` is the single reporting domain; Admin gets financial reports + CSV export; Operator gets today operational reconciliation only (no cost/margin/long-range analytics). Date ranges resolve in `business_timezone` via `CafeAvailabilityService`.

**Inventory + product analytics (Phase F3.2):** Inventory analytics read the persisted inventory ledger only — never recompute history from current recipes. Product quantity analytics use canonical `order_items` (Dining round items are a valid physical/volume source). Paid product sales eligibility follows F3.1 (confirmed retail; confirmed Dining Session only). BAR/KITCHEN reporting here is volume only. `InventoryProductReportingService` is the single inventory/product reporting domain; Admin gets full analytics + CSV; Operator gets today operational subset (no cost/margin/valuation).

**Operational performance analytics (Phase F3.3):** Timing analytics use persisted workflow timestamps only. BAR/KITCHEN performance comes from preparation tickets; C1 add-ons never multiply ticket workload; mixed-order completion uses the latest required station `ready_at`; dining preparation is round-level (customer + Waiter rounds share one model); dining service/billing timing is session-level. Operational timing must not be mixed with financial (F3.1) or inventory (F2/F3.2) authority. `OperationalPerformanceReportingService` is the single operational performance domain; Admin gets historical analytics + CSV; Operator gets today ops subset; Barista/Chef see live station queue age only; Waiter sees contextual dining timing only (no financial leakage; no waiter leaderboards).
Roles: **Waiter** panel for tables/sessions and dining cash; **Operator**/administrators confirm/reject dining UPI proof; **Barista**/**Chef** prepare station tickets; administrators manage catalog/config.
Catalog: products have `product_type` (beverage/food) and `preparation_station` (bar/kitchen).

## Waiter mobile PWA (C1.1)

* React PWA is the preferred mobile Waiter interface; Blade Waiter remains available as fallback.
* SPA auth (`/api/v1/auth/login`) allows **customer** or **waiter** only; role is server-owned (`/auth/me`). Other staff roles stay on Blade panels.
* Waiter API (`/api/v1/waiter/*`) reuses `DiningSessionService` + policies — no parallel order model.
* Table dashboard `display_state` is derived from session status + preparation tickets (`available` / `active` / `preparing` / `ready_to_serve` / `bill_requested` / `payment_pending`); session status remains authority. Ready-to-serve display ignores already-served rounds (`orders.served_at`).
* Independent per-session drafts; round placement supports idempotency keys; Waiter cannot confirm/reject UPI proof.
* **L1.1 Served:** Per dining round Mark Served after all required stations Ready (`can_mark_served`). Does not complete the session or bill; further rounds remain allowed. Customer-facing resource may show Served / Delivered to table without requiring customer confirmation.
- **L1.2 Cancel:** Per-round cancel via dining session routes/API under `DiningRoundCancellationPolicy`. Waiter may cancel only before material prep; Operator/Admin may cancel Ready/unserved with reason. Served → blocked (no void yet).

## Launch data readiness (L2)

* Production seed remains structural only (`DatabaseSeeder`); `DemoSeeder` hard-blocked outside `local`/`testing`.
* Real café catalog/payment/hours/CMS/staff/tables are **not** invented by the app — supply via Administrator after `docs/launch-menu.md` decisions.
* Read-only gate: `php artisan coffee:launch-readiness` (`--json`; non-zero on blockers). Product config: `coffee:catalog-readiness`.
* `LaunchCatalogSeeder` refuses until launch-menu is confirmed (no speculative import platform).
* Delivery fee stays unset at checkout (`delivery_fee_amount` null) under the third-party customer-paid assumption — confirm before changing.
* Do not deploy until HTTPS hosts, DB access, and launch-readiness blockers are cleared (`docs/production-deployment.md`, `docs/launch-data-todo.md`).

## Development completion audit (L3)

* Canonical matrix: `docs/development-completion-audit.md`.
* Phase-1 agreed launch-model software is treated as complete enough for real-data loading; café data + production smoke remain separate phases.
* Delivery model stays third-party / café does not collect delivery fee; disclaimer is config-driven.
* Do not mark the project launch-ready from L3 alone.

## Phase-1 freeze (L4)

* Phase-1 agreed launch-model software is **DEVELOPMENT COMPLETE / FROZEN** after demo acceptance.
* **NOT PRODUCTION READY** until real café data (`docs/launch-data-todo.md`) and production smoke (`docs/production-deployment.md`).
* Freeze record: `docs/development-completion-audit.md` → Frozen baseline (L4).

## Behaviour tracking & personalisation foundation (P2.1–P2.5)

* Append-only `customer_behaviour_events` + `customer_visitor_identities` (first-party; no fingerprinting).
* Client ingest: `POST /api/v1/behaviour/events`; merge: `POST /api/v1/behaviour/merge`.
* Server-authoritative `order_completed` from `OrderStatusChanged` (clients cannot submit).
* **P2.2:** derived `personalisation_profiles` (customer XOR visitor) rebuilt deterministically from behaviour events + canonical completed orders; purchase scoring ignores behaviour `order_completed` rows.
* Async `RebuildPersonalisationProfileJob`; `coffee:personalisation-profiles-rebuild`; internal `profilePayloadFor*` for engines (not exposed as full history to PWA).
* **P2.3:** `RecommendationService` strategy pipeline; `GET /api/v1/recommendations`; PWA recommendation rails; `recommendation_impression` / `recommendation_clicked`.
* **P2.4:** `campaigns` + `campaign_impressions`; Admin campaign CRUD; `CampaignEligibilityService` (placement/audience/frequency); `GET /api/v1/campaigns/eligible`; PWA popup controller; `campaign_impression` / `campaign_clicked` / `campaign_dismissed`.
* **P2.5:** `audience_segments`; shared `TargetingRule*` vocabulary; `SegmentService` dynamic membership; campaigns may use `segment_matches` / `segment_not_matches`; Admin Audience Segments + safe preview.
* Config: `coffee.behaviour.*` including `profile`, `recommendations`, `campaigns`, and `segments`.
* Detail: `docs/personalisation-architecture.md`. P2.6 analytics / conversion feedback next.

## Mobile ordering journey hardening (C2)

* Guest cart merge preserves add-on `configuration_hash` lines; login returns to intended checkout path.
* Auth bootstrap treats only definitive 401 as logout; transient failures expose Retry / Sign in again.
* Checkout clears fulfilment-scoped validation on method switch; place-order errors scroll to feedback and keep form state.
* Canonical payment-state presentation (`paymentStatePresentation`) drives customer confirmation/payment card wording.
* Waiter send-round keeps draft until confirmed success (or reconciled empty draft); bill request is idempotent when already awaiting payment; `close_blocked_reason` explains invalid close.
* Ready to Serve remains derived from station tickets **and** unserved rounds; Waiter PWA shows Mark Served when `can_mark_served`; live Ready age from ticket `ready_at` only (no historical F3.3 dashboard in mobile Waiter Mode).


## Product add-ons (C1)

* `AddOn` + `AddOnRecipeLine` + `product_add_on` pivot; cart/order snapshots via `cart_item_add_ons` / `order_item_add_ons`.
* Line identity: `configuration_hash = sha256(product_variant_id + canonical add_ons)`.
* Domain services: `AddOnService`, cart/order/checkout/inventory integrations; public catalog observer invalidates on add-on changes.
* Free drink benefit uses `base_unit_price` / `base_line_subtotal` when present.
* Preparation station for this phase inherits the parent OrderItem station (no cross-station add-ons).
* Product-level promotions apply to merchandise including selected add-ons (same cart/order subtotal path).
* Dining drafts use the same `configuration_hash` + `dining_round_draft_add_ons`; snapshots land on round OrderItems at accept.
