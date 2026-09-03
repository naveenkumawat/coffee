# Coffee Cafe Scope Progress

Last audited: August 30, 2026
Requirements source: [scope.md](./scope.md)
Architecture source: [architecture.md](./architecture.md)

## Audit Summary

- Completed foundations:
  - Laravel 13 application scaffold
  - MySQL-oriented runtime configuration
  - Administrator and Barista authentication entry points
  - Shared internal panel shell
  - Menu category and menu item architecture foundation
  - Public homepage using cached menu catalog data
  - Base policies, role middleware, parser/transfer/service/repository flow
  - Customer PWA scope and theme planning documentation
  - Customer React/Vite/TypeScript PWA foundation and first catalog slice
  - Customer PWA password-reset URL integration and verified Node toolchain baseline
- Partial foundations:
  - Administrator dashboard
  - Barista dashboard
  - Ingredient master data
  - Product/menu domain coverage
  - Recipe and variant costing
  - Customer backend/domain functionality
  - Temporary customer Blade foundation
  - Customer API/PWA migration
  - Order workflow
  - Security and role segmentation
  - Administrator user management
- Not started:
  - Reporting modules

## Phase 0 - Platform Foundation
Status: Partial

- [x] Laravel 13 application installed
- [x] MySQL runtime configuration prepared
- [x] Shared repository/service/parser/transfer architecture established
- [x] Administrator internal panel bootstrapped
- [x] Barista internal panel bootstrapped
- [x] Public storefront shell started
- [x] Customer API transport foundation finalized
- [x] Customer PWA shell finalized
- [x] Customer PWA scope and theme planning documentation completed
- [ ] Queue-backed workflows implemented
- [x] Customer transactional email notification infrastructure implemented (account + order/payment lifecycle; queue-ready; staff/internal alerts still pending)
- [x] Customer transactional WhatsApp notification channel implemented (Meta Cloud API; feature-flagged; shares E1 dispatcher/logs)
- [x] Staff operational notifications implemented (separate StaffNotificationDispatcher; in-app bell + selective email)
- [ ] Audit trail infrastructure implemented
- [ ] Phase dependency tracker maintained in code-facing documentation

## Phase 1 - Roles, Access, and Authentication
Status: Partial

### User Roles

- [x] Administrator role modeled in code
- [x] Barista role modeled in code
- [x] Customer role modeled in code
- [x] Role-aware access checks exist for Administrator and Barista panels
- [x] Customer-facing authenticated area implemented in temporary Blade foundation
- [x] Customer API auth/account transport implemented to final scope
- [ ] Fine-grained permission management implemented

### Authentication

- [x] Administrator login implemented
- [x] Barista login implemented
- [x] Wrong-role panel login blocked
- [x] Guest redirect behavior implemented for protected internal routes
- [x] Customer registration business flow implemented
- [x] Customer login/logout business flow implemented
- [x] Customer login accepts email or normalized phone (password auth)
- [x] Forgot password and reset business flow implemented
- [x] Customer profile management business flow implemented
- [x] Final PWA auth/account experience implemented

### User Management

- [x] Administrator user listing implemented
- [x] Create user implemented
- [x] Edit user implemented
- [x] Activate/deactivate user implemented
- [x] Reset password implemented
- [x] Assign role implemented
- [ ] Customer order history access from user management implemented
- [x] User archive/delete flow implemented

## Phase 2 - Administrator and Barista Dashboards
Status: Partial

### Administrator Dashboard

- [x] Dashboard route and screen exist
- [x] Basic catalog counters exist
- [x] Recent catalog activity widget exists
- [ ] Today's orders metrics implemented
- [ ] Status-based order metrics implemented
- [ ] Sales metrics implemented
- [ ] Customer metrics implemented
- [ ] Inventory warning metrics implemented
- [ ] Refill request metrics implemented
- [ ] Most ordered products implemented
- [ ] Dashboard graphs/statistics implemented

### Barista Dashboard

- [x] Dashboard route and screen exist
- [x] Basic queue preview placeholder exists
- [ ] Live order queue implemented
- [ ] Pending payment confirmation view implemented
- [ ] Inventory warning widgets implemented
- [ ] Refill request summary implemented
- [ ] Frequently ordered products implemented
- [ ] Tablet/mobile operational optimization completed

## Phase 3 - Ingredient and Inventory Foundation
Status: Partial

### Ingredient Categories

- [x] Ingredient category model and migration implemented
- [x] Administrator CRUD implemented
- [x] Activate/deactivate workflow implemented
- [x] Ingredient-by-category view implemented
- [x] Auto-generated slug workflow implemented
- [x] Legacy category sort ordering removed from the master-data flow

### Ingredient Brands

- [x] Ingredient brand model and migration implemented
- [x] Administrator CRUD implemented
- [x] Activate/deactivate workflow implemented
- [x] Ingredient-by-brand view implemented

### Ingredients

- [x] Ingredient model and migration implemented
- [x] Ingredient-to-brand relationship implemented
- [x] Measurement unit support implemented
- [x] Purchase quantity and purchase cost tracking implemented
- [x] Calculated unit cost implemented
- [x] Stock thresholds implemented
- [x] Supplier metadata support implemented
- [x] Ingredient CRUD implemented

### Inventory

- [x] Inventory transaction model and migration implemented
- [x] Add stock flow implemented
- [x] Manual adjustment flow implemented
- [x] Wastage/damage/expiry flow implemented
- [x] Purchase history implemented
- [x] Inventory audit history implemented

### Low Stock and Refill Requests

- [x] Minimum inventory threshold checks implemented
- [x] Low-stock warning lists implemented
- [x] Barista refill request workflow implemented
- [x] Administrator refill approval/rejection flow implemented
- [x] Inventory update on refill completion implemented

## Phase 4 - Product Master Data
Status: Partial

### Product Categories

- [x] Product category model and migration implemented
- [x] Administrator CRUD implemented
- [x] Category ordering and active flag implemented
- [x] Category image path foundation implemented
- [x] Category to products relationship implemented
- [x] Safe referenced archive handling implemented

### Flavours

- [x] Flavour model and migration implemented
- [x] Flavour CRUD implemented
- [x] Flavour-to-category applicability implemented

### Products

- [x] Product model and migration implemented
- [x] Product availability flag implemented
- [x] Featured flag implemented
- [x] Product/category relationship implemented
- [x] Product code or SKU implemented
- [x] Detailed description strategy implemented
- [x] Product image path foundation implemented
- [x] Serving size implemented through variants
- [x] Preparation time implemented
- [x] New item / bestseller flags implemented
- [x] Vegetarian/non-vegetarian flags implemented
- [x] Customizable options implemented
- [x] Product seed dataset and final naming baseline implemented
- [x] Deterministic local/testing demo dataset covers catalog, inventory edge states, recipes, refill requests, favourites, carts, orders/status history, and website CMS content

## Phase 5 - Variants, Recipes, and Costing
Status: Partial

### Product Sizes / Variants

- [x] Product size model and migration implemented
- [x] Per-size selling price implemented
- [x] Per-size recipe support implemented
- [x] Per-size production cost support implemented
- [x] Per-size margin support implemented

### Recipe Management

- [x] Product recipe model and migration implemented
- [x] Product recipe item model and migration implemented
- [x] Internal preparation instructions implemented
- [x] Barista recipe access implemented
- [x] Customer recipe secrecy enforced in public views and APIs

### Customer-Visible Ingredients vs Internal Recipe

- [x] Customer-visible ingredient summary implemented
- [x] Internal recipe kept separate from public presentation
- [x] Admin/barista-only recipe exposure enforced
- [x] Per-recipe-line customer visibility and optional customer label implemented
- [x] Customer API/PWA major-ingredient chips (no quantities/cost/prep) implemented

### Costing and Margin

- [x] Automatic production cost calculation implemented
- [ ] Packaging and operational cost support implemented
- [x] Selling margin calculation implemented
- [x] Administrator-only profitability visibility implemented

## Phase 6 - Barista Product Operations
Status: Pending

- [ ] Barista product list implemented
- [ ] Barista preparation detail page implemented
- [ ] Recipe/preparation instructions optimized for quick operational reading
- [ ] Ingredient availability visibility for baristas implemented

## Phase 7 - Customer Storefront
Status: Partial

Implementation note:

- Backend/customer-domain functionality may be complete for some flows even where the final customer API/PWA frontend is still pending.
- Existing customer Blade pages are transition/foundation work only and must not be treated as the final PWA implementation.
- Customer API/PWA migration is pending.
- Customer React/Vite/TypeScript PWA foundation now exists, with auth/account, cart, checkout (takeaway/delivery), payment confirmation + proof upload, orders/history/tracking, favourites, menu discovery, home CMS/content pages, and production hardening (manifest/SW/offline/update, route splitting, deploy checklist) implemented; remaining storefront polish (offers, quick view, etc.) remains pending.
- [x] Customer PWA UI/UX audit documented in `docs/ui-ux-audit.md` (redesign not started)
- [x] Customer PWA shared design foundation (C1): tokens, sticky CTA, toast feedback, copy/motion/nav clearance
- [x] Customer PWA ordering path polish (C2): Product Detail → Cart → Checkout → Confirmation
- [x] Customer PWA Home + Menu catalog polish (C3): slim header, rails, compact cards, sticky filters
- [x] Customer PWA Orders + Tracking + Account polish (C4): status emphasis, tracker, payment reuse, account sections
- [x] Customer PWA Auth + Content polish (C5): AuthCard, forms, FAQ accordion, Contact/About/Terms/Privacy
- [x] Customer PWA final QA + launch readiness (C6): responsive/PWA/offline fixes, imagery fallbacks, `docs/pwa-launch-checklist.md`
- [x] Customer PWA branding polish (C7): The88Coffees lockup, brand tokens, manifest/meta, Ombe asset cleanup
- [x] Customer PWA product tags + unified ordering (C8): reusable `product_tags`, `ProductTags`, shared `ProductOrderControl` compact/full

### Website Structure

- [x] Public homepage exists
- [x] About page implemented
- [x] Contact page implemented
- [x] FAQ page implemented
- [x] Terms and Conditions page implemented
- [x] Privacy Policy page implemented
- [x] Temporary customer Blade account area implemented
- [x] Final customer PWA account area implemented

### Homepage

- [x] Public homepage data foundation exists
- [x] Customer PWA home shell and featured catalog slice implemented
- [x] Administrator-managed homepage sections with manual product assignment implemented
- [ ] Final mobile-first PWA homepage implemented
- [x] Cafe branding content management implemented
- [x] Hero banner management implemented
- [x] Bestseller section implemented (as a seeded dynamic homepage section)
- [x] New products section implemented (as a seeded dynamic homepage section)
- [x] Offers/promotions section implemented
- [x] Business information section completed
- [x] WhatsApp contact button implemented

### Menu and Product Discovery

- [x] Catalog/product domain foundation implemented on backend
- [x] Customer catalog API implemented to final scope
- [x] Customer PWA menu page foundation implemented
- [x] Customer PWA product detail and add-to-cart slice implemented
- [x] Customer PWA category navigation foundation implemented
- [x] Dedicated PWA menu page implemented
- [x] Category navigation implemented in final PWA
- [x] Product cards implemented to final PWA scope
- [x] Product quick view implemented
- [x] Product filters implemented
- [x] Customer-visible ingredient summaries implemented
- [x] Guest cart (local persist, checkout auth gate, post-login merge) implemented
- [x] Customer login by email or phone implemented

### Favourites, Cart, and Checkout

- [x] Favourite products implemented
- [x] Cart domain/business flow implemented
- [x] Centralized promotions/discounts engine (automatic + coupons; order snapshots; admin Offers & Promotions; PWA offers UX)
- [x] Customer referral rewards (codes, qualify on payment confirmed, free drink GST preservation, coupon rewards, admin referrals list, PWA account/cart UX)
- [x] Customer PWA cart count and cart page foundation implemented
- [x] Temporary customer Blade checkout page implemented
- [x] Temporary customer Blade order confirmation/payment-instruction page implemented
- [x] Customer favourites API implemented
- [x] Customer cart API implemented to final scope
- [x] Guest cart local persistence and authenticated merge endpoint implemented
- [ ] Cart item notes implemented
- [x] Size selection implemented
- [ ] Flavour selection implemented
- [x] Checkout authentication requirement implemented
- [x] Checkout flow business logic implemented
- [x] Payment instruction presentation/business support implemented
- [x] Customer checkout API implemented to final scope
- [x] Final PWA cart and checkout experience implemented

## Phase 8 - Orders and Payment Workflow
Status: Partial

- [x] Order model and migration implemented
- [x] Order item model and migration implemented
- [x] Unique order number generation implemented
- [x] Pending Payment initial state implemented
- [x] Manual payment confirmation workflow implemented
- [x] Takeaway and delivery fulfilment methods implemented
- [x] Optional dine-in / table ordering implemented (Website Settings toggle default off; admin café tables; active tables API; checkout `cafe_table_id` + `table_name_snapshot`; Ready to Serve wording; staff/customer notifications extended)
- [x] Delivery address snapshot and third-party delivery disclaimer implemented
- [x] Customer payment proof upload (image) implemented
- [x] Administrator payment proof review / replacement request implemented
- [x] Extensible delivery fields reserved (`delivery_provider`, `delivery_fee_amount`, `delivery_tracking_reference`, `delivery_status`) without courier API integration
- [x] Manual payment method (`manual`) with Website Settings UPI/phone/QR/instructions/WhatsApp resolver
- [x] Order status workflow implemented
- [x] Prepared-by / assigned barista tracking implemented
- [x] Preparation timestamps implemented
- [x] Customer notes stored on orders implemented
- [x] Order history domain support implemented
- [x] Order detail domain support implemented
- [x] Customer order tracking domain support implemented
- [x] Customer orders/tracking API implemented to final scope
- [x] Final PWA orders/history/tracking experience implemented

## Phase 9 - Inventory-to-Order Automation
Status: Partial

- [x] Recipe-based inventory consumption from orders implemented (Phase F2: Accepted / dining round → `SALE_CONSUMPTION` once via `OrderInventoryConsumptionService`; prep tickets never deduct)
- [x] Safe early-cancel reversal implemented (`SALE_REVERSAL` when no ticket is Preparing/Ready; never delete ledger history; never recompute from current recipe)
- [ ] Configurable deduction point implemented (fixed at Accepted / dining round creation for F2)
- [ ] Product availability based on inventory implemented
- [ ] Barista inventory warnings on unavailable ingredients implemented

**Deployment boundary:** consumption applies prospectively after deploy — do not backfill historical orders.

## Phase 10 - Reports, Notifications, and Content Management
Status: Partial

### Reports

- [x] Canonical financial reporting + reconciliation implemented (Phase F3.1: `FinancialReportingService`; Admin financial report + CSV; Operator today reconciliation only)
- [x] Snapshot-only revenue rules locked (confirmed Takeaway/Delivery orders; confirmed Dining Sessions; rounds never double-counted)
- [x] Inventory + product analytics implemented (Phase F3.2: `InventoryProductReportingService`; ledger-backed inventory; OrderItem product volume; Admin analytics + CSV; Operator today ops subset)
- [x] Product-wise / category-wise sales volume reports implemented (Phase F3.2; paid eligibility follows F3.1)
- [x] Operational performance analytics implemented (Phase F3.3: `OperationalPerformanceReportingService`; BAR/KITCHEN timing; add-ons excluded from ticket multiplication; mixed-order coordination; dining round/session ops shared for customer+waiter; Admin + Operator + staff live context; C2 idempotency-safe counts)
- [ ] Customer reports implemented
- [ ] Profitability reports implemented

### Website Content Management

- [x] Website settings model and management UI implemented
- [x] Business information management implemented
- [x] Payment instruction management implemented
- [x] Static page content management implemented

### Notifications and Search

- [x] Customer transactional email notifications implemented (welcome, password reset/changed, order/payment/status lifecycle)
- [x] Customer transactional WhatsApp notifications implemented (order/payment lifecycle via Meta Cloud API; password flows remain email-only; preparing off by default)
- [x] Internal/staff operational notifications implemented (Administrator/Barista in-app + high-value email; staff WhatsApp deferred)
- [x] Inventory/refill operational alerts implemented (stock state transitions + refill workflow via StaffNotificationDispatcher)
- [x] Customer product search implemented
- [ ] Internal search for orders/customers/products/ingredients/refill requests implemented

## Phase 11 - Security, Audit, and Responsive Completion
Status: Partial

- [x] Role middleware exists
- [x] Policies exist for current menu entities
- [x] Custom non-debug production exception views exist
- [x] Structured request/exception logging exists
- [x] Shared internal responsive shell exists
- [x] Customer data isolation rules implemented for order/account modules
- [ ] Broader module policy coverage implemented
- [ ] Inventory and order audit trail implemented
- [ ] Full responsive coverage across all scoped screens implemented
- [x] Final customer PWA installability, manifest, service worker, and offline shell implemented
- [x] Production deployment prep (D3): env/Sanctum/CORS/Apache runbook, rate limits, security headers, `docs/production-deployment.md` (deploy not executed; customer Blade retained)

## Phase 12 - Phase 2 Enhancements
Status: Pending

- [ ] Online payment gateway integration implemented
- [x] Automated WhatsApp transactional notifications implemented (Cloud API; credentials/templates still required in production)
- [ ] OTP authentication implemented
- [ ] QR menu implemented
- [ ] Table ordering implemented
- [ ] Dine-in order mode implemented
- [x] Delivery order mode implemented (third-party arrangement; no courier API yet)
- [ ] Coupons, offers, loyalty, wallet, and gift cards implemented
- [x] Ratings and reviews implemented
- [ ] Order scheduling implemented
- [ ] Advanced purchasing, suppliers, expenses, tax/GST, barcode, printer, and display integrations implemented

## Phase 13 - Phase 3 Enhancements
Status: Pending

- [ ] Mobile apps implemented
- [ ] Multi-branch support implemented
- [ ] Franchise or centralized pricing support implemented
- [ ] Supplier purchase orders implemented
- [ ] Employee attendance and shift management implemented
- [ ] Advanced accounting implemented
- [ ] AI forecasting and recommendation features implemented

## Immediate Next Build Order

- [x] Finalize scope document path normalization and keep `docs/scope.md` as the canonical requirements file
- [ ] Add user-management foundations needed by downstream order and customer modules
- [ ] Implement ingredient categories and ingredients
- [ ] Implement inventory transactions and low-stock thresholds
- [ ] Expand menu into full product categories, products, flavours, and variants
- [ ] Implement recipes and costing before order fulfillment
- [ ] Build orders, checkout, payment confirmation, and barista operational flows
- [ ] Build the mobile-first customer PWA on top of the implemented customer API surface


## Dining flow

- **Normal:** Menu → Cart → Checkout (takeaway / delivery only)
- **Dining:** Table → Session → Rounds → Finish → Bill → Pay → Close
- PWA exposes Dining when `fulfilment.dining_enabled` is true (`/dining?table=CODE` preselect supported).
- Waiter role operates table service; food & beverage catalog uses product type + prep station.

- [x] Product add-ons (Phase C1): catalog assignment, cart configuration hash merge, server pricing, free-drink base-only waiver, inventory base+add-on consumption, admin CRUD, Dining drafts/rounds, PWA customization + payment-state UX, invoice nesting
- [x] Waiter mobile PWA (Phase C1.1): SPA waiter auth, table dashboard display states, multi-table independent drafts, menu/add-on ordering into dining drafts, idempotent round send, bill/payment/close via existing permissions (Blade Waiter retained)
- [x] Mobile ordering journey audit & hardening (Phase C2): guest-cart login merge with add-ons, auth session recovery, checkout fulfilment/error recovery, shared payment-state presentation, waiter draft/send/bill/close safety, Ready to Serve prominence
