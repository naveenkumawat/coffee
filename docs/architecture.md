# Coffee Architecture

## Canonical Frontend Architecture

Internal surfaces:

- Administrator = Laravel Blade
- Barista = Laravel Blade

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
Administrator / Barista Blade
  -> controllers
  -> existing Services
  -> Repositories
  -> Models / Database
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
- Staff operational notifications: domain events (`ShouldDispatchAfterCommit`) → dedicated `StaffNotificationDispatcher` (not coupled to customer dispatcher) → Laravel database notifications + selective staff email. Covers order ops and inventory/refill ops via `StaffNotificationContext`. Idempotent via `staff_notification_logs` (`unique_key` + `user_id` + `channel`). Stock alerts fire only on `InventoryStockStatus` transitions (not every ledger write); episode keys include inventory transaction id so healthy→low can alert again later. Audience by active `owner`/`manager` or `barista` roles. In-app bell in shared internal header with severity metadata; staff WhatsApp channel enum reserved but not enabled. Delivery failure must not roll back order/payment/inventory workflows.

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

Administrator and Barista use one shared internal design system/shell (`internal.layouts.default`, shared header/sidebar/components, and `public/internal` assets). Role-specific differences must be implemented through permissions, navigation configuration, data visibility, and action authorization — not separate visual systems.

- Shared: page header, breadcrumbs, cards, tables/filters, buttons, badges, notification bell, order detail shell partials under `resources/views/internal/orders/partials/`.
- Role-specific: nav entries, financial totals, payment proof review, invoice actions, create-order, and other policy-gated controls.
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
