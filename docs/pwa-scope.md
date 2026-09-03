# Coffee Customer PWA Scope

Last updated: August 30, 2026
Theme reference: `theme/pwa/ombe-bootstrap-pwa.vercel.app`
Canonical backend/API architecture: `Laravel /api/v1 -> existing Services -> Repositories -> Models`

## Purpose

This document defines the target scope for the Coffee customer-facing Progressive Web App.

The PWA is the canonical long-term customer frontend.

It is separate from:

- the internal Administrator Blade panel
- the internal Barista Blade panel
- the temporary customer Blade auth/account/cart/checkout foundation currently present during migration

## Canonical Frontend Architecture

```text
React + Vite + TypeScript PWA
  -> Laravel /api/v1
  -> existing Services
  -> Repositories
  -> Models / Database
```

Rules:

- Administrator remains Laravel Blade.
- Barista remains Laravel Blade.
- Existing customer Blade pages are transitional only.
- Customer Blade pages must not be expanded as the final storefront architecture.
- Customer API and temporary Blade flows may coexist during migration.
- Domain logic must remain in the existing Laravel Services, not duplicated in frontend-specific or `Api*Service` classes.

## Core Product Principle

- Mobile-first design is mandatory.
- Primary target device is the smartphone.
- Touch-first interaction is the default assumption.
- Desktop and tablet layouts remain responsive fallbacks, not the primary design center.

## PWA Delivery Requirements

The customer frontend must support:

- installable behavior on supported Android and iOS browsers where capabilities allow
- web app manifest with Coffee-specific app name, short name, icons, theme color, and background color
- standalone display mode where supported
- service worker for safe static asset and shell caching
- offline fallback shell or offline page
- fast repeat loads
- graceful handling of slow or intermittent mobile networks
- release/update strategy that avoids customers being stuck on stale frontend assets

## Offline and Caching Rules

Safe to cache:

- app shell assets
- static icons
- fonts where licensing allows bundling
- non-sensitive public catalog media with appropriate cache strategy
- low-risk read-only marketing content

Do not treat offline cache as source of truth for:

- authenticated account state
- cart pricing
- stock or availability
- checkout submissions
- payment status
- live order status
- private order history details

Rules:

- backend remains authoritative for authentication, prices, availability, cart totals, checkout validation, payment state, and order state
- offline support must never bypass server validation
- sensitive or rapidly changing customer data must be fetched from the server and refreshed appropriately

## Customer Flows In Scope

The PWA foundation must support these flows over time:

- Home / storefront with administrator-managed merchandising sections (manual product assignment + ordering)
- Product browsing
- Categories and flavours
- Product detail and variant selection
- Customer registration, login, logout
- Forgot/reset password if supported by current backend flow
- Account and profile
- Favourites
- Cart
- Checkout summary and submission
- Payment instructions
- Order confirmation
- Order tracking and order history
- Verified-purchase product ratings and reviews (submit/edit/delete from completed orders; summaries on cards; reviews on detail)
- Reorder later when implemented
- Waiter Mode (role-aware): mobile table dashboard, multi-table dining drafts, round send, bill/payment/close using `/api/v1/waiter/*` (customers never enter Waiter Mode)

## API Dependencies

The current customer API target is `/api/v1`.

PWA features depend on:

- auth/account endpoints (customer + waiter SPA login; role from server)
- catalog endpoints for categories, flavours, products, featured products, product detail, variants, product ratings
- homepage sections endpoint (`GET /api/v1/home`) for dynamic merchandising rails
- cart endpoints for show, add, update quantity, remove, clear, count/totals
- checkout endpoints for summary and submit
- orders endpoints for own list and own detail
- authenticated product rating create/update/delete
- waiter dining endpoints (`/api/v1/waiter/tables`, sessions, drafts, rounds, bill, cash, close)

Future API additions expected:

- richer order timeline/status presentation if needed
- push notification registration endpoints if notifications are introduced later
- realtime private-channel delivery (R1) — foundation uses Laravel Reverb/Echo; persistent operational notification domain (R1.2) with ACK API + store; business event wiring arrives in R1.3+
- loyalty/rewards endpoints if added later

## Mobile UX Requirements

- touch-friendly targets and spacing
- simple navigation with primary actions quickly reachable
- visible cart and order state
- minimal typing during ordering
- mobile-friendly forms and keyboard/input types
- lightweight responsive images
- card and list views preferred over desktop-style dense tables
- sticky bottom actions may be used where they improve ordering flow
- avoid unnecessary modals and heavy multi-step friction

## Proposed Navigation Model

Primary mobile navigation should use a restrained bottom navigation with up to five destinations:

- Home
- Menu
- Cart
- Orders
- Account

Secondary navigation may use:

- top app bar with contextual back navigation
- lightweight drawers or sheets for filters and support content
- inline category chips or tabs for menu browsing

Avoid:

- overcrowded bottom nav
- mixing internal/admin concepts into customer navigation
- large floating side menus as the primary mobile navigation pattern

## Performance Requirements

- prioritize fast first load
- keep JavaScript payload small
- keep CSS focused on the customer app only
- lazy-load route chunks and heavy views where appropriate
- optimize images and icons
- minimize network requests
- cache static shell assets efficiently
- preserve good Core Web Vitals on mobile networks

Avoid introducing heavy libraries unless they clearly reduce implementation risk or provide sustained value.

## Security and Ordering Integrity

Before accepting checkout or order creation, the backend must continue validating:

- authenticated customer
- cart ownership
- product and variant availability
- current selling price
- selected add-on assignment, activity, quantity limits, and server prices
- totals
- order state
- payment/manual confirmation rules

Product customization (variants and optional paid add-ons) uses a shared bottom sheet; simple single-variant products without add-ons keep one-tap Add.

Cart line identity is server-owned (`configuration_hash` of variant + canonical add-ons). The frontend never treats client prices or fingerprints as authoritative.

Payment confirmation UX must follow server payment/proof state (Cash Pending, UPI Pending, Proof Submitted/Awaiting Review, Proof Rejected, Payment Confirmed) — not generic order status.

The frontend must never:

- trust client-calculated totals as authoritative
- accept or display client-authored add-on prices as truth
- expose recipes, ingredient quantities, costs, margins, internal notes, or staff-only data
- imply payment has been confirmed before Administrator action

## Theme Adoption Guidance

The Ombe theme is a visual and structural reference only.

Use it to guide:

- mobile layout rhythm
- card composition
- sticky action placement
- catalog and order-list presentation
- profile and auth screen framing

Do not copy forward theme features that conflict with Coffee scope, especially:

- delivery-provider marketplace UX (Coffee uses takeaway + third-party delivery coordination without integrating a courier API yet)
- wallet/card storage patterns
- chat and unrelated demo pages
- jQuery plugin assumptions that do not fit the React/Vite architecture

See [pwa-theme-map.md](./pwa-theme-map.md) for the detailed theme inventory and mapping plan.

## Recommended PWA Foundation Deliverables

The first implementation pass should establish:

- React + Vite + TypeScript app scaffold
- app routing and route shells
- Coffee design tokens derived from the theme direction
- bottom navigation and top app bar system
- API client foundation for `/api/v1`
- auth/session bootstrap for first-party Sanctum flow
- manifest and initial icons
- service worker and offline fallback shell
- Home, Menu, Product Detail, Cart, Orders, and Account route skeletons

## Production Hosting and Cutover

Recommended shape:

```text
PWA static HTTPS host  ->  Laravel `/api/v1`
```

Required environment:

- PWA: `VITE_API_BASE_URL`
- Laravel: `COFFEE_PWA_URL`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`, `SESSION_DOMAIN`
- HTTPS for both hosts in production, with cookie domain aligned to the shared parent when using subdomains

Operational rules:

- Cache only the app shell/static assets via the post-build service worker (`customer-pwa/scripts/generate-sw.mjs`).
- Never treat service-worker cache as authoritative for auth, cart, checkout, payment, or orders.
- Exclude `/api/*` (and Sanctum routes) from fetch interception as authoritative responses; navigation falls back to the app shell/`offline.html`.
- Keep customer Blade available until the PWA is verified live, then retire Blade in a separate cutover.

See `customer-pwa/README.md` for the deployment checklist.

## Out of Scope for This Planning Phase

- building the React project now
- replacing the temporary Blade customer frontend now
- implementing a payment gateway
- integrating a third-party courier/delivery provider API
- implementing inventory consumption from orders
- implementing push notifications now
- modifying internal Blade Administrator or Barista surfaces


## Dining flow

- **Normal:** Menu → Cart → Checkout (takeaway / delivery only)
- **Dining:** Table → Session → Rounds → Finish → Bill → Pay → Close
- PWA exposes Dining when `fulfilment.dining_enabled` is true (`/dining?table=CODE` preselect supported).
- Waiter role operates table service; food & beverage catalog uses product type + prep station.

## Mobile ordering journey hardening (C2)

- Server owns commercial/operational truth (prices, cart, dining drafts, payment status, session/table state, order status).
- Guest cart survives login and merges by `configuration_hash` (variants + add-ons); checkout redirect is preserved.
- Transient auth/API failures do not clear valid local state; definitive 401 shows Sign in again.
- Ambiguous checkout/round writes must be reconciled (idempotency / refetch) before treating as failure or clearing drafts.
- Shared `paymentStatePresentation` is the canonical customer payment-state wording (confirmation + payment card).
- Dining Session drafts remain session-scoped; Waiter table switches must not merge drafts.
- React PWA remains preferred Waiter mobile UI; Blade Waiter stays fallback.
