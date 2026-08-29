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
- Partial foundations:
  - Administrator dashboard
  - Barista dashboard
  - Product/menu domain coverage
  - Customer storefront
  - Security and role segmentation
- Not started:
  - User management
  - Ingredient, inventory, recipe, order, reporting, and customer account modules

## Phase 0 - Platform Foundation
Status: Partial

- [x] Laravel 13 application installed
- [x] MySQL runtime configuration prepared
- [x] Shared repository/service/parser/transfer architecture established
- [x] Administrator internal panel bootstrapped
- [x] Barista internal panel bootstrapped
- [x] Public storefront shell started
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
- [ ] Customer-facing authenticated area implemented
- [ ] Fine-grained permission management implemented

### Authentication

- [x] Administrator login implemented
- [x] Barista login implemented
- [x] Wrong-role panel login blocked
- [x] Guest redirect behavior implemented for protected internal routes
- [ ] Customer registration implemented
- [ ] Customer login/logout implemented
- [ ] Forgot password and reset flow implemented
- [ ] Customer profile management implemented

### User Management

- [ ] Administrator user listing implemented
- [ ] Create user implemented
- [ ] Edit user implemented
- [ ] Activate/deactivate user implemented
- [ ] Reset password implemented
- [ ] Assign role implemented
- [ ] Customer order history access from user management implemented
- [ ] User archive/delete flow implemented

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
Status: Pending

### Ingredient Categories

- [ ] Ingredient category model and migration implemented
- [ ] Administrator CRUD implemented
- [ ] Activate/deactivate workflow implemented
- [ ] Ingredient-by-category view implemented

### Ingredients

- [ ] Ingredient model and migration implemented
- [ ] Measurement unit support implemented
- [ ] Purchase quantity and purchase cost tracking implemented
- [ ] Calculated unit cost implemented
- [ ] Stock thresholds implemented
- [ ] Supplier metadata support implemented
- [ ] Ingredient CRUD implemented

### Inventory

- [ ] Inventory transaction model and migration implemented
- [ ] Add stock flow implemented
- [ ] Manual adjustment flow implemented
- [ ] Wastage/damage/expiry flow implemented
- [ ] Purchase history implemented
- [ ] Inventory audit history implemented

### Low Stock and Refill Requests

- [ ] Minimum inventory threshold checks implemented
- [ ] Low-stock warning lists implemented
- [ ] Barista refill request workflow implemented
- [ ] Administrator refill approval/rejection flow implemented
- [ ] Inventory update on refill completion implemented

## Phase 4 - Product Master Data
Status: Partial

### Product Categories

- [x] Basic category concept exists through current menu categories
- [x] Category ordering and active flag exist
- [ ] Category image support implemented
- [ ] Category scope verified against final product taxonomy

### Flavours

- [ ] Flavour model and migration implemented
- [ ] Flavour CRUD implemented
- [ ] Flavour-to-category applicability implemented

### Products

- [x] Basic product concept exists through current menu items
- [x] Product availability flag exists
- [x] Featured flag exists
- [x] Product/category relationship exists
- [ ] Product code or SKU implemented
- [ ] Detailed description strategy completed
- [ ] Product image support implemented
- [ ] Serving size implemented
- [ ] Preparation time implemented
- [ ] New item / bestseller flags implemented
- [ ] Vegetarian/non-vegetarian flags implemented
- [ ] Customizable options implemented
- [ ] Product list audited against final scope naming

## Phase 5 - Variants, Recipes, and Costing
Status: Pending

### Product Sizes / Variants

- [ ] Product size model and migration implemented
- [ ] Per-size selling price implemented
- [ ] Per-size recipe support implemented
- [ ] Per-size production cost support implemented
- [ ] Per-size margin support implemented

### Recipe Management

- [ ] Product recipe model and migration implemented
- [ ] Product recipe item model and migration implemented
- [ ] Internal preparation instructions implemented
- [ ] Barista recipe access implemented
- [ ] Customer recipe secrecy enforced in public views and APIs

### Customer-Visible Ingredients vs Internal Recipe

- [ ] Customer-visible ingredient summary implemented
- [ ] Internal recipe kept separate from public presentation
- [ ] Admin/barista-only recipe exposure enforced

### Costing and Margin

- [ ] Automatic production cost calculation implemented
- [ ] Packaging and operational cost support implemented
- [ ] Selling margin calculation implemented
- [ ] Administrator-only profitability visibility implemented

## Phase 6 - Barista Product Operations
Status: Pending

- [ ] Barista product list implemented
- [ ] Barista preparation detail page implemented
- [ ] Recipe/preparation instructions optimized for quick operational reading
- [ ] Ingredient availability visibility for baristas implemented

## Phase 7 - Customer Storefront
Status: Partial

### Website Structure

- [x] Public homepage exists
- [ ] About page implemented
- [ ] Contact page implemented
- [ ] FAQ page implemented
- [ ] Terms and Conditions page implemented
- [ ] Privacy Policy page implemented
- [ ] Customer account area implemented

### Homepage

- [x] Featured products section exists
- [x] Menu category preview exists
- [ ] Cafe branding content management implemented
- [ ] Hero banner management implemented
- [ ] Bestseller section implemented
- [ ] New products section implemented
- [ ] Offers/promotions section implemented
- [ ] Business information section completed
- [ ] WhatsApp contact button implemented

### Menu and Product Discovery

- [ ] Dedicated menu page implemented
- [ ] Category navigation implemented
- [ ] Product cards implemented to final scope
- [ ] Product quick view implemented
- [ ] Product filters implemented
- [ ] Customer-visible ingredient summaries implemented

### Favourites, Cart, and Checkout

- [ ] Favourite products implemented
- [ ] Cart implemented
- [ ] Cart item notes implemented
- [ ] Size selection implemented
- [ ] Flavour selection implemented
- [ ] Checkout authentication requirement implemented
- [ ] Checkout flow implemented
- [ ] Payment instruction presentation implemented

## Phase 8 - Orders and Payment Workflow
Status: Pending

- [ ] Order model and migration implemented
- [ ] Order item model and migration implemented
- [ ] Unique order number generation implemented
- [ ] Pending Payment initial state implemented
- [ ] Manual payment confirmation workflow implemented
- [ ] Order status workflow implemented
- [ ] Prepared-by / assigned barista tracking implemented
- [ ] Preparation timestamps implemented
- [ ] Customer notes stored on orders implemented
- [ ] Order history implemented
- [ ] Order detail page implemented
- [ ] Customer order tracking implemented

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
- [ ] Customer data isolation rules implemented for order/account modules
- [ ] Broader module policy coverage implemented
- [ ] Inventory and order audit trail implemented
- [ ] Full responsive coverage across all scoped screens implemented

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
