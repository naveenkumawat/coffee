# Coffee Cafe Scope Progress

Last audited: August 29, 2026
Requirements source: [scope.md](./scope.md)
Architecture source: [architecture.md](./architecture.md)

## Audit Summary

- Completed foundations:
  - Laravel 13 application scaffold
  - MySQL-oriented runtime configuration
  - Administrator and Barista authentication entry points
  - Shared internal ZYLM-based panel shell
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
- [ ] Notification infrastructure implemented
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
- [ ] New item / bestseller flags implemented
- [ ] Vegetarian/non-vegetarian flags implemented
- [ ] Customizable options implemented
- [x] Product seed dataset and final naming baseline implemented

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

- [ ] Customer-visible ingredient summary implemented
- [ ] Internal recipe kept separate from public presentation
- [x] Admin/barista-only recipe exposure enforced

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
- Customer React/Vite/TypeScript PWA foundation now exists, with auth/account, cart, checkout, and payment confirmation implemented; orders UI, favourites, and broader customer flows remain pending.

### Website Structure

- [x] Public homepage exists
- [ ] About page implemented
- [ ] Contact page implemented
- [ ] FAQ page implemented
- [ ] Terms and Conditions page implemented
- [ ] Privacy Policy page implemented
- [x] Temporary customer Blade account area implemented
- [x] Final customer PWA account area implemented

### Homepage

- [x] Public homepage data foundation exists
- [x] Customer PWA home shell and featured catalog slice implemented
- [ ] Final mobile-first PWA homepage implemented
- [ ] Cafe branding content management implemented
- [ ] Hero banner management implemented
- [ ] Bestseller section implemented
- [ ] New products section implemented
- [ ] Offers/promotions section implemented
- [ ] Business information section completed
- [ ] WhatsApp contact button implemented

### Menu and Product Discovery

- [x] Catalog/product domain foundation implemented on backend
- [x] Customer catalog API implemented to final scope
- [x] Customer PWA menu page foundation implemented
- [x] Customer PWA product detail and add-to-cart slice implemented
- [x] Customer PWA category navigation foundation implemented
- [ ] Dedicated PWA menu page implemented
- [ ] Category navigation implemented in final PWA
- [ ] Product cards implemented to final PWA scope
- [ ] Product quick view implemented
- [ ] Product filters implemented
- [ ] Customer-visible ingredient summaries implemented

### Favourites, Cart, and Checkout

- [ ] Favourite products implemented
- [x] Cart domain/business flow implemented
- [x] Customer PWA cart count and cart page foundation implemented
- [x] Temporary customer Blade checkout page implemented
- [x] Temporary customer Blade order confirmation/payment-instruction page implemented
- [ ] Customer favourites API implemented
- [x] Customer cart API implemented to final scope
- [ ] Cart item notes implemented
- [ ] Size selection implemented
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
- [x] Order status workflow implemented
- [x] Prepared-by / assigned barista tracking implemented
- [x] Preparation timestamps implemented
- [x] Customer notes stored on orders implemented
- [x] Order history domain support implemented
- [x] Order detail domain support implemented
- [x] Customer order tracking domain support implemented
- [x] Customer orders/tracking API implemented to final scope
- [ ] Final PWA orders/history/tracking experience implemented

## Phase 9 - Inventory-to-Order Automation
Status: Pending

- [ ] Recipe-based inventory consumption from orders implemented
- [ ] Configurable deduction point implemented
- [ ] Product availability based on inventory implemented
- [ ] Barista inventory warnings on unavailable ingredients implemented

## Phase 10 - Reports, Notifications, and Content Management
Status: Pending

### Reports

- [ ] Sales reports implemented
- [ ] Order reports implemented
- [ ] Product reports implemented
- [ ] Inventory reports implemented
- [ ] Customer reports implemented
- [ ] Profitability reports implemented

### Website Content Management

- [ ] Website settings model and management UI implemented
- [ ] Business information management implemented
- [ ] Payment instruction management implemented
- [ ] Static page content management implemented

### Notifications and Search

- [ ] Internal notification system implemented
- [ ] Customer product search implemented
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
- [ ] Final customer PWA installability, manifest, service worker, and offline shell implemented

## Phase 12 - Phase 2 Enhancements
Status: Pending

- [ ] Online payment gateway integration implemented
- [ ] Automated WhatsApp notifications implemented
- [ ] OTP authentication implemented
- [ ] QR menu implemented
- [ ] Table ordering implemented
- [ ] Dine-in and delivery order modes implemented
- [ ] Coupons, offers, loyalty, wallet, and gift cards implemented
- [ ] Ratings and reviews implemented
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
