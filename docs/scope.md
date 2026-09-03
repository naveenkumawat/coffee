# Coffee Café Website & Order Management System

## Project Scope & Software Requirements

## 1. Project Overview

The objective of this project is to develop a complete **Coffee Café Website and Order Management System** that manages café operations from product setup and recipe management through inventory, customer ordering, order preparation, and reporting.

The platform will include both:

* A **customer-facing café website**
* A secure **administration and barista management system**

The application will initially operate without an integrated online payment gateway. Customers will place orders through the website, receive an order number and payable amount, and complete payment externally. Payment confirmation can initially be handled manually by sharing the **order number and payment screenshot through WhatsApp**.

The system should be designed so that online payment, delivery integration, loyalty programs, coupons, and other advanced features can be introduced later without major restructuring.

---

# 2. Technology Stack

### Backend

* Laravel 13
* PHP
* Laravel Authentication & Authorization
* Role-based access control

### Database

* MySQL

### Frontend

* Internal Administrator frontend: Laravel Blade
* Internal Barista frontend: Laravel Blade
* Customer frontend target: React + Vite + TypeScript mobile-first Progressive Web App (PWA)
* React
* Vite
* TypeScript

### Architecture Considerations

* MVC architecture
* Repository/service layer where appropriate
* Role-based permissions
* Internal panels remain Blade-rendered
* Customer storefront is a mobile-first React PWA that consumes Laravel REST APIs
* Existing customer Blade views/controllers are temporary transition foundations and are not the final storefront architecture
* Responsive web design with customer mobile-first priority
* Secure authentication
* Scalable database structure
* Modular development for future enhancements
* Shared domain services reused by both Blade and API transport layers during migration

### Canonical Frontend Architecture

Internal management surfaces:

* Administrator = Laravel Blade
* Barista = Laravel Blade

Customer ordering surface:

* Customer = React + Vite + TypeScript mobile-first PWA
* Laravel provides the `/api/v1` REST API and business backend for the customer app
* The customer PWA consumes the API and should not be treated as a Blade-first architecture
* Existing customer Blade authentication/account/cart/checkout flows are temporary foundation work only and must not be expanded as the long-term storefront
* Existing customer Blade work should not be deleted yet; Blade and API may coexist during migration

Reference planning docs:

* Customer PWA scope: `docs/pwa-scope.md`
* Customer PWA theme mapping: `docs/pwa-theme-map.md`

---

# 3. User Roles

The system will have three main roles.

### 3.1 Administrator

The Administrator will have complete access to the system and will be responsible for:

* System configuration
* User management
* Ingredient management
* Inventory management
* Product management
* Recipes
* Cost calculations
* Orders
* Reports
* Barista management
* Customer management
* Website content
* Business information
* Inventory refill requests
* Operational monitoring

Administrator will generally have permission to:

**Create / View / Update / Delete / Approve / Manage / Export / Report**

depending on the relevant module.

---

### 3.2 Barista

Baristas will use the system primarily for café operations.

They will be able to:

* View incoming orders
* Process orders
* View products
* View product recipes
* View preparation instructions
* View ingredient requirements
* View inventory availability
* Request inventory refills
* Receive low-inventory information
* Update order preparation status
* Mark orders as ready
* Complete/handover orders

Baristas will not have administrative access to sensitive configuration and financial settings unless specifically permitted.

---

### 3.3 Customer

Customers will interact primarily with the public website.

Customers can:

* Browse the café website
* View the menu
* Browse products by category
* View product details
* See major ingredients
* Favourite products
* Register/login
* Add products to cart
* Place orders
* Review order amount
* Receive an order number
* Review previous orders
* Track current order status

Checkout will require authentication.

---

# 4. Administrator Modules

## 4.1 Dashboard

The Administrator Dashboard will provide an operational overview of the café.

Possible dashboard information includes:

* Today's orders
* Pending orders
* Confirmed orders
* Preparing orders
* Ready orders
* Completed orders
* Cancelled orders
* Today's sales
* Monthly sales
* Number of customers
* Number of products
* Low-stock ingredients
* Out-of-stock ingredients
* Inventory refill requests
* Most ordered products
* Recent orders

Graphs and statistics can be included for quick performance monitoring.

---

# 5. User Management

Administrator can manage system users.

### User Types

* Administrator
* Barista
* Customer

### User Information

Possible information includes:

* Name
* Mobile number
* Email
* Password
* Role
* Status
* Profile information
* Created date
* Last activity

### Administrator Functions

* Create user
* Edit user
* Activate/deactivate user
* Reset password
* Assign role
* View order history for customers
* View activity where applicable
* Delete/archive users

Customers will normally register themselves through the public website.

---

# 6. Ingredient Categories

Ingredients should be organized into categories for easier inventory and recipe management.

Example categories:

* Coffee
* Milk
* Ice Cream
* Syrups
* Sauces
* Powders
* Fruits
* Toppings
* Sweeteners
* Tea
* Matcha
* Ice
* Carbonated beverages
* Packaging
* Miscellaneous

### Functions

Administrator can:

* Create category
* Edit category
* Activate/deactivate category
* Delete category
* View ingredients belonging to category

---

# 7. Ingredient Management

Each ingredient will have its own master record.

Example:

**Ingredient:** Davidoff Espresso Coffee
**Category:** Coffee
**Measurement Unit:** Gram
**Purchase Cost:** ₹600
**Purchase Quantity:** 100 g
**Calculated Cost:** ₹6/g

Another example:

**Ingredient:** Vanilla Syrup
**Measurement:** ml
**Bottle Quantity:** 750 ml
**Purchase Cost:** ₹750
**Calculated Cost:** ₹1/ml

### Ingredient Information

* Ingredient name
* Ingredient category
* Brand
* Description
* Measurement unit
* Purchase quantity
* Purchase cost
* Cost per unit
* Current stock
* Minimum stock level
* Reorder level
* Supplier information if required
* Status

### Supported Units

Examples:

* Gram
* Kilogram
* Millilitre
* Litre
* Piece
* Bottle
* Pack

---

# 8. Ingredient Inventory

The system will maintain stock quantities for ingredients.

Administrator can:

* Add stock
* Adjust stock
* Record purchase quantity
* Record purchase cost
* View available inventory
* View consumed inventory
* View low-stock ingredients
* View refill requests
* Record wastage
* Maintain stock adjustment history

### Inventory Transactions

Every stock change should generate an inventory transaction.

Examples:

* Stock Added
* Product Consumption
* Manual Adjustment
* Wastage
* Damage
* Expiry
* Correction

This allows the system to maintain an inventory audit history.

---

# 9. Minimum Inventory & Low Stock Alerts

Each ingredient can have a configurable minimum inventory level.

Example:

| Ingredient      | Available | Minimum |
| --------------- | --------: | ------: |
| Coffee Beans    |     500 g |   300 g |
| Full Cream Milk |       8 L |     5 L |
| Vanilla Syrup   |    350 ml |  200 ml |

When stock reaches or drops below its minimum quantity, the system will generate a warning.

Possible notifications:

* Administrator dashboard alert
* Barista inventory warning
* Low-stock list
* Internal system notification

Future integrations may include:

* Email
* WhatsApp
* Push notification

---

# 10. Inventory Refill Requests

Baristas will be able to request inventory replenishment.

Example:

**Ingredient:** Vanilla Ice Cream
**Available:** 2 kg
**Requested:** 5 kg
**Reason:** Stock running low

### Request Workflow

**Barista Request → Administrator Review → Approved/Rejected → Inventory Updated**

Request information:

* Ingredient
* Current quantity
* Requested quantity
* Requested by
* Request date
* Comment
* Status
* Approved by
* Approval date

Statuses:

* Pending
* Approved
* Rejected
* Completed

---

# 11. Product Categories

Products/menu items will be organized into categories.

Examples:

### Coffee

* Hot Coffee
* Cold Coffee
* Cold Brew
* Espresso

### Frappes

### Shakes

### Mojitos

### Coolers

### Matcha

### Tea

### Seasonal Drinks

### Special Drinks

Administrator can:

* Create category
* Edit category
* Set display order
* Activate/deactivate category
* Assign products
* Upload category image

---

# 12. Flavour Management

Flavours should be maintained independently so that they can be reused across products.

Examples:

* Vanilla
* Hazelnut
* Irish Cream
* Toffee Nut
* Caramel
* Salted Caramel
* Chocolate
* White Chocolate
* Pistachio
* Mint
* Green Apple
* Blue Curaçao
* Lemon

Flavour information can include:

* Name
* Description
* Image
* Status
* Applicable product categories

---

# 13. Product Management

Administrator can create and manage all menu products.

### Product Information

* Product name
* Product category
* Product code/SKU
* Short description
* Detailed description
* Product image
* Available flavours
* Serving size
* Selling price
* Preparation time
* Availability
* Featured item
* New item
* Bestseller
* Vegetarian/non-vegetarian if required
* Customizable options
* Display order

Example:

### Café Latte

**Description:**
A smooth coffee drink prepared using espresso and steamed milk.

**Customer-visible ingredients:**
Coffee + Milk

**Available Sizes:**

* Small
* Large

**Internal Recipe:**
Not visible to customers.

---

# 14. Product Sizes / Variants

The system should support different sizes of the same product.

Example:

### Cold Coffee Classic

| Size  | Volume | Price |
| ----- | -----: | ----: |
| Small | 300 ml |   ₹80 |
| Large | 500 ml |  ₹120 |

Each size can have:

* Different selling price
* Different ingredient quantities
* Different production cost
* Different profit margin

This allows accurate recipe costing.

---

# 15. Product Recipe Management

Recipes will be maintained separately from customer-facing ingredient information.

**Full recipes must never be visible to customers.**

Recipes will only be available to:

* Administrator
* Authorized Barista

### Recipe Information

Each recipe can contain:

* Product
* Product size
* Ingredient
* Ingredient quantity
* Unit
* Preparation sequence
* Preparation instructions
* Mixing/blending time
* Temperature where applicable
* Notes

Example:

### Cold Coffee Classic — 300 ml

| Ingredient        | Quantity |
| ----------------- | -------: |
| Instant Coffee    |      2 g |
| Water             |     7 ml |
| Vanilla Ice Cream |    130 g |
| Full Fat Milk     |   110 ml |

### Preparation

1. Dissolve coffee in hot water.
2. Add milk and vanilla ice cream.
3. Blend for specified duration.
4. Check texture.
5. Pour into serving glass/cup.
6. Add topping if applicable.
7. Serve.

---

# 16. Customer-Visible Ingredients vs Internal Recipe

The application should differentiate between:

### Customer-visible ingredients

Displayed on the website.

Example:

**Latte**

> Coffee + Milk

### Internal Recipe

Visible to Barista/Admin only.

Example:

* Coffee Beans: 14 g
* Milk: 180 ml
* Extraction: 30 seconds
* Milk temperature: 60–65°C
* Preparation instructions

This prevents confidential café recipes and exact quantities from being exposed publicly.

---

# 17. Product Production Cost Calculation

The system should automatically calculate the estimated production cost from recipe ingredients.

Example:

| Ingredient |    Qty | Unit Cost | Recipe Cost |
| ---------- | -----: | --------: | ----------: |
| Coffee     |    2 g |      ₹2/g |          ₹4 |
| Milk       | 110 ml |  ₹0.07/ml |       ₹7.70 |
| Ice Cream  |  130 g |   ₹0.18/g |      ₹23.40 |
| Cup        |      1 |        ₹5 |          ₹5 |

**Production Cost = ₹40.10**

Additional production costs can optionally include:

* Cup
* Straw
* Lid
* Tissue
* Packaging
* Electricity/operational allowance

---

# 18. Selling Margin Calculation

Administrator should be able to see the profitability of every product.

Example:

**Production Cost:** ₹40
**Selling Price:** ₹80
**Gross Profit:** ₹40

### Margin

`Profit = Selling Price - Production Cost`

`Profit Margin % = Profit / Selling Price × 100`

The system can display:

* Ingredient cost
* Packaging cost
* Total production cost
* Selling price
* Profit amount
* Profit margin percentage

This information must be visible only to authorized administration users.

---

# 19. Barista Dashboard

Baristas should receive a simplified operational dashboard.

Dashboard can show:

* New orders
* Pending payment confirmation
* Confirmed orders
* Orders waiting for preparation
* Currently preparing
* Ready orders
* Completed orders
* Inventory warnings
* Refill requests
* Frequently ordered products

The interface should prioritize speed and ease of use during café operations.

---

# 20. Barista Product List

Baristas can browse products without accessing administrative configuration.

Product list can show:

* Product name
* Category
* Size
* Availability
* Selling price
* Recipe access
* Current ingredient availability

Barista can click a product to open preparation details.

---

# 21. Barista Product Preparation View

This should function as a practical preparation guide.

Example:

## Hazelnut Cold Coffee — 300 ml

### Ingredients

* Coffee — 2 g
* Milk — 110 ml
* Vanilla Ice Cream — 130 g
* Hazelnut Syrup — 20 ml

### Preparation

1. Dissolve coffee.
2. Add milk.
3. Add ice cream.
4. Add hazelnut syrup.
5. Blend.
6. Pour into serving cup.
7. Add topping.
8. Serve.

The page should be designed so the recipe can be read quickly while preparing drinks.

---

# 22. Order Management

Orders are a core component of the application.

Each order should contain:

* Order number
* Customer
* Order items
* Product size
* Quantity
* Individual price
* Item subtotal
* Discount if introduced later
* Total amount
* Customer notes
* Order date/time
* Payment status
* Order status
* Assigned/prepared by Barista
* Preparation timestamps

---

# 23. Order Number Generation

Every successful checkout will generate a unique order number.

Example:

**CC-260826-0012**

The number can contain:

* Café prefix
* Date
* Sequential order number

The order number will be used for:

* Payment reference
* Customer communication
* WhatsApp payment screenshot
* Order tracking
* Barista identification
* Support

---

# 24. Order Status Workflow

Recommended workflow:

**Pending Payment → Payment Confirmed → Accepted → Preparing → Ready for Pickup → Completed**

Additional statuses:

* Cancelled
* Rejected
* Payment Failed
* Refunded — future use

### Example

Customer places order:

**Order #CC-260826-0012**
**Amount: ₹280**

Status:

**Pending Payment**

Customer sends the order number and payment screenshot through WhatsApp.

Once payment is confirmed:

**Payment Confirmed**

Barista accepts the order:

**Accepted**

Preparation starts:

**Preparing**

When complete:

**Ready for Pickup**

After handover:

**Completed**

---

# 25. Payment Handling — Initial Version

There will be **no online payment gateway in Phase 1**.

After order creation, the customer will see:

* Order number
* Total amount
* Payment instructions
* WhatsApp contact
* UPI/payment information if configured

Example:

> Your order has been successfully created.
>
> Order Number: **CC-260826-0012**
>
> Total Amount: **₹280**
>
> Please complete the payment and share your payment screenshot along with the order number on WhatsApp.

Administrator/Barista can manually confirm payment.

---

# 26. Customer Website

The customer-facing website should represent the café brand professionally.

Canonical implementation target:

* Mobile-first API-driven PWA for customers
* Installable where browser/device capabilities allow
* Responsive desktop/tablet fallback, with smartphone use as the primary design target
* Laravel Blade customer screens may exist temporarily during transition, but they are not the final customer frontend architecture

Suggested pages:

* Home
* Menu
* About Us
* Contact
* FAQ
* Terms & Conditions
* Privacy Policy
* Login/Register
* My Account
* Favourite Drinks
* Cart
* Checkout
* My Orders
* Order Details

---

# 27. Home Page

The homepage can include:

* Café branding
* Hero banner
* Featured drinks (now administrator-managed homepage sections with manual product assignment)
* Bestseller / New rails (seeded as normal dynamic homepage sections; not hardcoded)
* Product categories
* Current offers
* Why choose us
* About café
* Customer favourites
* Contact details
* WhatsApp ordering/contact button
* Location
* Business timings

---

# 28. Menu Page

The menu should be divided into clearly visible categories.

Example:

**Coffee | Cold Coffee | Frappe | Shakes | Mojitos | Coolers | Matcha**

Products can be displayed as cards containing:

* Product image
* Product name
* Short description
* Starting price
* Favourite icon
* Quick View
* Add to Cart

Filters can later include:

* Category
* Flavour
* Price
* Bestseller
* New
* Vegetarian

---

# 29. Product Quick View

Clicking a menu product should open a quick product information window.

Example:

## Café Latte

Smooth espresso combined with creamy milk.

**Made With:**
Coffee + Milk

**Available Sizes:**
300 ml / 500 ml

**Starting From:**
₹90

Customers should **not** see:

* Exact ingredient quantities
* Production cost
* Recipe
* Preparation instructions
* Profit margin

---

# 30. Favourite Products

Logged-in customers can mark products as favourites.

Features:

* Add to favourites
* Remove from favourites
* View Favourite Drinks page
* Add favourite directly to cart

Favourite data can later help identify popular products.

---

# 31. Shopping Cart

Customers can:

* Add product
* Select size
* Select flavour where available
* Adjust quantity
* Remove item
* Add customer note
* View subtotal
* View total

Example customer note:

> Less sweet

or

> No extra topping

Future enhancements can support detailed product customizations.

### Product add-ons (Phase C1 locks)

* Add-ons are first-class catalog entities with optional recipe lines and per-product assignment (price override + max quantity).
* Cart/order identity is `configuration_hash` (variant + canonical add-on selection), so the same variant can exist as separate lines when add-ons differ.
* Prices are server-authoritative; client-sent add-on prices are ignored.
* Free-drink referral rewards waive **base drink price only**; add-on charges remain payable.
* Inventory consumption counts base recipe lines and add-on recipe lines separately (`source_type` = `base_recipe` | `add_on`).
* Preparation station inherits the parent item for this phase (BAR add-ons stay BAR; KITCHEN stay KITCHEN).
* Promotions use the merchandise subtotal that already includes selected add-ons unless a future scope explicitly excludes them.
* OrderItem add-ons are immutable commercial snapshots; invoices/API never recalculate from live catalog.


---

# 32. Customer Authentication

Customers can browse the website without logging in.

Login will be required before checkout.

Long-term delivery model:

* Customer authentication/account endpoints should be provided through the customer API
* Temporary Blade customer auth/account screens may remain during transition
* Final customer authentication UX should live in the PWA, not in Blade as the permanent storefront

Authentication functionality:

* Registration
* Login
* Logout
* Forgot Password
* Password Reset
* Profile
* Mobile number
* Email address

Future development may include:

* OTP login
* Google Login
* Apple Login

---

# 33. Checkout

Checkout will require a logged-in customer.

Architecture note:

* The final customer checkout flow should be delivered through the customer PWA consuming Laravel API endpoints
* Temporary Blade checkout work may continue as a foundation during transition, but it should not define the long-term frontend architecture

The customer confirms:

* Products
* Quantity
* Price
* Total amount
* Customer information
* Pickup information
* Notes

After confirmation:

1. Order is created.
2. Unique order number is generated.
3. Order starts in **Pending Payment** state.
4. Total payable amount is displayed.
5. Payment instructions are displayed.
6. Customer sends payment screenshot through WhatsApp.

---

# 34. Customer Order History

Customers can view previous and current orders.

Order history can display:

* Order number
* Order date
* Products
* Total amount
* Payment status
* Order status

Clicking an order opens complete order details.

---

# 35. Customer Order Tracking

Customers can view the current progress of an order.

Final customer order history and tracking should be exposed through the customer API for the PWA while the backend remains authoritative for authentication, pricing, availability, cart ownership, checkout state, payment state, and order state.

Example:

**Order #CC-260826-0012**

✓ Order Placed
✓ Payment Confirmed
✓ Accepted
● Preparing
○ Ready for Pickup
○ Completed

This reduces the need for customers to repeatedly contact café staff regarding order status.

---

# 36. Inventory Consumption from Orders

Orders and dining rounds are the inventory consumption authority. Preparation tickets are operational routing only and never deduct stock.

**When (Phase F2):**

* Takeaway / Delivery: consume when the order reaches **Accepted** (preparation commitment), before tickets are created.
* Dining: each **round** consumes when the round is created/accepted for preparation (pay-at-end does not change stock timing).
* Final dining bill, payment, and session close do **not** consume again.

**How:**

* For each order item: `ordered qty × recipe line quantity` using the variant recipe snapshotted on the item (`recipe_id`) and existing `IngredientUnit` normalization.
* Ledger types: `sale_consumption` (decrease) and `sale_reversal` (increase). Durable identity in `order_inventory_consumptions` (unique per order item + ingredient).
* Promotions, referral coupons, free drinks, and GST never change physical consumption — free drink still consumes the full recipe.

**Cancellation:**

* If **no** preparation ticket is `preparing` or `ready`: create compensating `sale_reversal` rows from **original** consumption quantities (never recalc from current recipe). Keep original consumption rows.
* If **any** ticket is already preparing/ready (including mixed BAR/KITCHEN): do **not** auto-restore — staff may adjust wastage manually.
* Restoration depends on preparation state, not payment/refund state.

**Deployment:** prospective only after release — no automatic backfill of historical orders onto live stock.

---

# 37. Product Availability Based on Inventory

The application can automatically identify whether a drink can be prepared from available inventory.

Example:

Hazelnut syrup is unavailable.

Products requiring hazelnut syrup can be:

* Marked unavailable automatically
* Shown with an inventory warning to Barista
* Manually kept available by Administrator if desired

This feature can be implemented progressively.

---

# 38. Reports

Administrator reports provide business and operational information from **transactional snapshots only**.

### Financial reporting authority (Phase F3.1)

* **Retail revenue** = confirmed Takeaway/Delivery orders (`payment_status = confirmed` and status not Cancelled/Rejected), using `payment_confirmed_at` and stored `orders.*` money/tax/discount snapshots.
* **Dining revenue** = confirmed Dining Session snapshots only (`dining_sessions.payment_status = confirmed`, `paid_at`, session totals). Dining **round orders** are operational/preparation records and must never be double-counted as revenue.
* Pending/unpaid is not revenue. Historical reports never recalculate from current Website Settings (prices, GST, promotions, rewards).
* Canonical service: `FinancialReportingService` (Admin financial report + CSV export; Operator today reconciliation only — no cost/margin/long-range analytics).

### Inventory + product analytics authority (Phase F3.2)

* **Inventory analytics** = persisted inventory ledger (`inventory_transactions`) only. Never recompute historical consumption from current recipes, orders, or live stock assumptions.
* Supported movement types already on the ledger (e.g. `sale_consumption`, `sale_reversal`, restock/refill additions, adjustments, wastage) — do not invent unsupported transaction types.
* **Product quantity analytics** = canonical `order_items` snapshots. Physical units are independent of promotions/referrals/free drinks (1 prepared item = 1 unit; inventory still full recipe).
* **Paid product sales / attributable revenue** reuse F3.1 eligibility: confirmed Takeaway/Delivery (not Cancelled/Rejected); Dining only when the Dining Session is payment-confirmed. Dining rounds never contribute revenue; unpaid Dining consumption remains visible for inventory/physical volume.
* BAR vs KITCHEN in F3.2 is **volume only** (snapshotted `preparation_station`). Prep-time / delay / staff SLA analytics belong to F3.3.
* Canonical service: `InventoryProductReportingService` (Admin Inventory & Product Analytics + CSV exports; Operator today operational subset only — no cost/margin/valuation).

### Operational performance analytics authority (Phase F3.3)

* **Operational analytics** = persisted workflow timestamps only (`order_preparations.*_at`, order lifecycle timestamps, dining session opened/bill/paid/closed). Never infer durations from current status alone when timestamps exist; never recalculate history from mutable SLA settings.
* **BAR/KITCHEN performance** = preparation tickets. Queue wait = created→accepted; start delay = accepted→preparing; prep = preparing→ready; total = created→ready. Missing timestamps are excluded (not zero-filled).
* **Add-ons** = parent OrderItem customization context only — they do not create independent preparation tickets or multiply ticket-count analytics.
* **Mixed order completion** = latest required station `ready_at`. Station gap = latest − earliest station ready. One station ready does not complete the order.
* **Dining preparation** = round-level (customer-created and Waiter-created rounds share one analytics model). **Dining service/billing performance** = session/table-level. No waiter ranking or employee leaderboards.
* Do not mix operational timing with financial authority (F3.1) or inventory authority (F2/F3.2).
* No invented business SLA values. Relative live backlog metrics only. Canonical service: `OperationalPerformanceReportingService` (Admin historical report + CSV; Operator today ops subset; Barista/Chef live queue age only; Waiter contextual dining timing only).
* C2 idempotent retries (checkout / Send Order / bill request) must never double-count tickets, rounds, or sessions.

### Sales Reports

* Daily / weekly / monthly / custom date-range paid sales (business timezone)
* Channel breakdown: Takeaway, Delivery, Dining
* Payment reconciliation: cash collected, UPI confirmed, pending, rejected proofs

### Order Reports

* Paid transaction counts and average value
* Cancelled / rejected operational counts (excluded from paid revenue)
* Paid cancellation exceptions surfaced separately (no invented refund accounting)

### Product Reports

* Most ordered products
* Least ordered products
* Product revenue
* Product profitability

### Inventory Reports

* Current inventory
* Low-stock ingredients
* Ingredient consumption
* Stock adjustment
* Inventory purchases
* Wastage

### Customer Reports

* New customers
* Returning customers
* Customer order history
* Most active customers

---

# 39. Profitability Reports

Since recipes contain ingredient quantities and ingredient costs, the system can calculate approximate profitability.

Example:

### Cold Coffee Classic

**Units Sold:** 100
**Revenue:** ₹8,000
**Estimated Production Cost:** ₹4,100
**Estimated Gross Profit:** ₹3,900

Reports can show:

* Product revenue
* Production cost
* Estimated profit
* Margin percentage

---

# 40. Website Content Management

Administrator should manage basic website information without code changes.

Content may include:

* Café name
* Logo
* About Us
* Address
* Contact number
* WhatsApp number
* Email
* Business timings
* Social media links
* Payment instructions
* UPI ID
* Homepage banners
* Terms & Conditions
* Privacy Policy

---

# 41. Notifications

Initial internal notifications can include:

* New order
* Payment awaiting confirmation
* New inventory refill request
* Low inventory
* Out-of-stock ingredient
* Order cancelled

Future integrations can extend these notifications through:

* WhatsApp
* Email
* SMS
* Mobile push notifications

### R1 — Realtime delivery (self-hosted)

Realtime is an additive delivery path for operational alerts. It does **not** replace REST/API authority.

**R1.1 Foundation (done):** Laravel Reverb + Echo, private channels, authenticated connection state for PWA + internal Blade panels. No business event wiring yet.

**R1.2 Persistent operational notifications (done):** Reusable notification domain with lifecycle tracking. Tables `operational_notifications` + `operational_notification_recipients` (separate from Laravel’s DB `notifications` used by the staff bell). Supports user-specific and role/team audiences (role expands to active users). Shared resolve may close one notification for everyone while preserving per-recipient history. Lifecycle meanings (do not fake delivery):

* `created_at` — notification row persisted
* `broadcast_at` — server attempted realtime dispatch for that recipient
* `delivered_at` — client explicitly ACKed receipt
* `first_seen_at` — first time visible to the user
* `read_at` — user opened/read it
* `acknowledged_at` — user explicitly acknowledged an action-required alert
* `resolved_at` — underlying required business condition resolved (notification-level)
* Response delays (delivery / first-seen / acknowledge / action-start / action-completion / resolution) are **computed** from timestamps — never stored as authority

Authenticated API: `GET /api/v1/notifications`, `GET /api/v1/notifications/action-required`, `POST .../{recipient}/delivered|seen|read|acknowledge` (own recipient only; idempotent first-write timestamps). Generic broadcast event `OperationalNotificationBroadcasted` (`.operational.notification`) on `private-user.{id}` with minimal DTO. Persist first; broadcast after commit; websocket failure must not break persistence. PWA + Blade share notification client/store foundation (no final bell/reminder UI yet).

**R1.3A Business event wiring (done):** Domain events → `OperationalBusinessNotificationPublisher` → `OperationalNotificationService` (not from controllers). Dedup via unique `idempotency_key` (`type:Subject:id:lifecycle`). Empty active audience persists shell row with `metadata.no_active_recipients` and does not fail the business flow.

| Type | Create | Audience | Resolve |
|------|--------|----------|---------|
| `order.requires_attention` | Cash retail `OrderPlaced` while `pending_payment` | Owner/Manager/Operator | Accepted / Rejected / Cancelled / PaymentConfirmed |
| `order.requires_acceptance` | Retail → `payment_confirmed` | Owner/Manager/Operator | Accepted / Rejected / Cancelled |
| `order.payment_proof_review` | `OrderPaymentProofReceived` (new key per upload stamp) | Owner/Manager/Operator | PaymentConfirmed / proof rejected / order terminal / prior open review on resubmission (`proof_resubmitted`) |
| `preparation.ticket_pending` | Ticket → `pending` | Barista (BAR) or Chef (KITCHEN) | First meaningful station action: **Accepted** (also Preparing/Ready/Cancelled). Not held open until Ready. |
| `dining.ready_to_serve` | Dining round when **all** active tickets Ready (once) | Waiter + Operator | **Served** (L1.1 Delivered-to-table) / Completed / Cancelled / Rejected |
| `order.cancelled` / `order.rejected` | Order terminal | Owner/Manager/Operator | Informational |
| `preparation.ticket_cancelled` | Ticket cancelled | Matching station role | Informational |
| `dining.round_cancelled` | Dining order Cancelled/Rejected | Waiter + Operator | Informational |

UPI retail place does **not** create actionable attention (wait for proof / payment confirm). Mixed BAR+KITCHEN orders create independent station notifications. Customer realtime status is R1.4.

**R1.3B Realtime notification UI + reminder engine (done):** Shared operational notification client for all internal Blade panels (Administrator/Operator/Barista/Chef/Waiter) and waiter PWA foundation.

* **Bell + center:** Shared header bell opens responsive drawer (Action required / Recent). Badges for unread + action-required. Toast alerts for incoming items; critical gets stronger persistent styling.
* **ACK meanings (unchanged server first-write):** socket receive → `delivered`; visible toast/drawer → `seen`; open/detail → `read`; Acknowledge button → `acknowledge`. Delivery alone does **not** mark read.
* **Reminders:** `ActionReminderManager` with configurable `REMINDER_INTERVAL_MS = 30000`. Eligible types only (`order.requires_attention|requires_acceptance`, `order.payment_proof_review`, `preparation.ticket_pending`, `dining.ready_to_serve`). Informational cancel/reject = no repeat. Continues until notification **resolved** (ack dismisses toast only). Presented reminders POST `/api/v1/notifications/{recipient}/reminded` (atomic `reminder_count` / `last_reminded_at`; no-op if resolved/non-eligible/foreign recipient).
* **Sound:** bundled local chime; unlock after first gesture; failures ignored; visual path always works.
* **Multi-tab:** BroadcastChannel + localStorage leader election — only leader plays sound / shows reminder toasts / increments reminder_count. All tabs upsert state.
* **Reconnect:** authoritative `GET` list + action-required on bootstrap, reconnect, online, and visibility after absence. Socket is fast path only.
* **Dining Ready-to-Serve resolution (L1.1):** `dining.ready_to_serve` resolves on **Served** / Completed / Cancelled / Rejected. Preparation Ready ≠ Served; Served ≠ session/order Completed.

**R1.4 Customer realtime notifications + live order tracking (done):** Customer-owned order/session updates on `private-user.{id}` via the same operational notification domain (no separate customer role channel).

* **Types:** `customer.order.placed|accepted|preparing|ready|completed|cancelled|rejected`, `customer.payment.proof_received|confirmed|rejected`, `customer.dining.round_updated|ready|bill_requested|session_closed`. Audience = authenticated order/session owner only; walk-in dining with null `customer_id` creates none.
* **Payload:** customer-safe title/message/priority/action_url/subject ids + public status metadata only — no recipe, cost, margin, staff, other-customer, or payment secrets.
* **UX:** Shared PWA bell/drawer/toasts for authenticated customers (same stack as Waiter). No 30s repeating reminders; one-time strong alert+sound for Ready / payment rejected / cancel/reject. Optional foreground `Notification` only if permission already granted (no Web Push/VAPID — R1.7).
* **Live tracking:** Socket is signal only; order list/detail and dining session soft-refetch canonical REST on customer notification, reconnect, online, and visibility resume.
* **ACK:** delivered on socket; seen when drawer visible; read on open. Multi-tab leader election reused from R1.3B for sound/toast ownership.
* **Boundary:** Staff R1.3A/B notifications unchanged. Background push deferred to R1.7.

**R1.5 Realtime reliability, presence, inventory/refill (done):**

* **Presence (advisory only):** Echo `presence-ops` for staff roles; TTL heartbeats via `POST /api/v1/realtime/presence/heartbeat` (unique user counts, not tabs). Customers denied. Admin/Operator header shows role online summaries. Presence never blocks workflows.
* **No-staff escalation:** When BAR/KITCHEN ticket pending or dining ready-to-serve is created and the target role has no online presence → Operator/Admin actionable `escalation.no_*_online` (deduped per work lifecycle). Resolves when staff heartbeats online or underlying work resolves.
* **Reliability:** Client event-id/uuid dedupe (bounded session memory); coalesced REST sync on reconnect/online/visibility; connection states remain subtle; failure never clears notification state or blocks ops.
* **Inventory/refill realtime:** Domain events → `OperationalInventoryNotificationPublisher` → operational notifications + minimal `.inventory.ops` role-channel signals (Admin/Operator/Barista). Low = informational; out-of-stock + pending refill = actionable/reminder-eligible. Soft-reload inventory/refill Blade pages on signal. No cost/profit in payloads. Legacy StaffNotificationDispatcher email/bell retained.

**R1.6 Waiter/Dining table-session scoped realtime (done):**

* **Channels:** `private-dining-session.{id}` (session owner customer OR dining/order staff); `private-table.{id}` (dining/order staff only); keep `private-user.{id}` for personal notifications; Waiter/Operator also receive `.dining.ops` on role channels.
* **Signals:** Domain dining/order/prep events → `DiningRealtimePublisher` → `DiningOpsSignalBroadcasted` (`.dining.ops`) with safe payload only: `event_id`, `type`, `session_id`, `table_id`, optional `order_id`/`state`, `updated_at`. Never money, recipes, costs, payment secrets, or private customer fields.
* **Clients:** Socket = signal; REST = authority. Waiter PWA table dashboard + active session soft-refetch via coalesced `useDiningOpsSync`; customer dining session subscribes to session channel; Blade waiter/operator dining pages soft-reload on role `.dining.ops`. Event-id dedupe + coalesce avoid REST storms.
* **Multi-Waiter:** Concurrent Waiters share session/table signals; duplicate bill/close remain idempotent/canonical via REST; sockets never grant Operator payment powers.
* **Ready-to-serve → Served (L1.1):** Preparation Ready ≠ Served. Waiter/Operator/Admin mark a dining **round** Served (`orders.served_at` / `served_by_user_id`) after all required stations are Ready. Resolves `dining.ready_to_serve` and stops 30s reminders immediately. Does not close the session, freeze the bill, or block further rounds. Realtime: `DiningRealtimePublisher` emits `round.served` (`.dining.ops`); REST remains authority.

**R1.7 Realtime operational hardening & runbooks (done):**

* **Audit:** End-to-end chain remains commit → domain event → publisher → persisted operational notification (when applicable) → broadcast signal → client dedupe → REST reconcile. Sockets never business authority.
* **Failure isolation:** Reverb down/restart must not block REST; dining/notification publishers swallow broadcast failures; clients reconnect and reconcile (Blade soft-reload for prep/orders/dining/inventory pages; PWA sync + dining/order hooks).
* **Dedupe:** `withEvents(discover: false)` keeps a single intended listener set; client event-id dedupe + sync coalesce; notification idempotency_keys unchanged.
* **Diagnostics:** `php artisan coffee:realtime-health` (`--probe`, `--metrics`, `--json`); client `__COFFEE_REALTIME_DIAGNOSTICS__` (connection/reconnect/last event/reconcile/presence heartbeat — no sensitive payloads).
* **Docs:** `docs/realtime-runbook.md`, `docs/realtime-smoke-test.md`.
* **Deferred:** Background Web Push/VAPID remains future work (not part of R1.7 hardening).

Channel model (authorization-ready):
* `private-user.{id}` — the authenticated user only
* `private-role.administrator` — owner/manager
* `private-role.operator|barista|chef|waiter` — matching staff role only
* `private-dining-session.{id}` — session owner customer or dining/order staff
* `private-table.{id}` — dining/order staff only
* Customers never subscribe to staff role channels

Constraints: self-hosted Reverb only (no Pusher/Ably/Firebase). Broadcast DTOs only — never raw Eloquent models. Realtime failure must never block REST.

---

# 42. Search

Search functionality should be available where appropriate.

### Customer

Search products by:

* Product name
* Category
* Flavour

### Administrator/Barista

Search:

* Orders
* Customers
* Products
* Ingredients
* Inventory requests

---

# 43. Security & Permissions

Role-based authorization will be implemented.

### Administrator

Full system access.

### Barista

Operational access only.

Barista should not be able to access areas such as:

* Product profitability
* System configuration
* Sensitive reports
* Administrative permissions

unless permission is explicitly provided.

### Customer

Can only access:

* Their profile
* Their favourites
* Their cart
* Their orders

Customers must never access another customer's order or personal data.

---

# 44. Audit Trail

For important administrative and inventory activities, basic history should be maintained.

Examples:

* Inventory quantity changed
* Order status changed
* Payment confirmed
* Product price changed
* Refill request approved
* Ingredient cost modified

Records can capture:

* Action
* User
* Previous value
* New value
* Date/time

---

# 45. Responsive Design

The entire application should work properly on:

* Desktop
* Laptop
* Tablet
* Mobile

Special attention should be given to the mobile customer interface because customers are expected to primarily browse the menu and place orders through their phones.

Barista order screens should also be optimized for tablets/mobile devices where practical.

---

# 46. Suggested Database Modules

The initial database architecture may include tables such as:

* users
* roles
* permissions
* ingredient_categories
* ingredients
* ingredient_inventory_transactions
* inventory_refill_requests
* product_categories
* flavours
* products
* product_sizes
* product_flavours
* product_recipes
* product_recipe_items
* favourites
* carts
* cart_items
* orders
* order_items
* order_status_history
* payments/payment_confirmations
* notifications
* website_settings
* pages
* audit_logs

Exact database design will be finalized during technical architecture development.

---

# 47. Main System Workflow

## Administrator

**Setup Categories → Add Ingredients → Add Inventory → Create Products → Configure Recipe → Calculate Product Cost → Set Selling Price → Publish Menu**

## Customer

**Visit Website → Browse Menu → View Product → Favourite/Add to Cart → Login → Checkout → Receive Order Number → Make Payment → Send Screenshot → Track Order**

## Barista

**Receive Order → Verify/Wait for Payment Confirmation → Accept Order → View Recipe → Prepare Product → Update Status → Mark Ready → Handover → Complete Order**

## Inventory

**Order Preparation → Recipe Ingredients Consumed → Inventory Updated → Minimum Threshold Checked → Low Stock Alert → Barista Refill Request → Administrator Refill**

---

# 48. Recommended Phase 1 Scope

The first production release should concentrate on the essential café workflow.

### Administration

* Authentication
* Role management
* User management
* Ingredient categories
* Ingredients
* Inventory
* Minimum inventory
* Product categories
* Flavours
* Products
* Product sizes
* Recipes
* Recipe costing
* Selling margin
* Orders
* Order status
* Inventory refill requests
* Basic reports
* Website settings

### Barista

* Dashboard
* Orders
* Order status updates
* Product list
* Recipe/preparation details
* Inventory view
* Refill requests
* Low-stock alerts

### Customer Website

* Home
* About
* Menu
* Product quick view
* Product categories
* Favourites
* Customer registration/login
* Cart
* Checkout
* Order creation
* Order number
* Payment instructions
* Order history
* Order tracking
* Contact/WhatsApp information

---

# 49. Recommended Phase 2 Enhancements

After café operations stabilize, the following can be introduced:

* Razorpay/UPI online payment
* Automated WhatsApp notifications
* OTP authentication
* ~~QR-based menu~~ (deferred: architecture ready for later `/menu?table=T4` convenience deep-link; not security)
* ~~Table ordering / Dine-in orders~~ (implemented: admin-toggleable `fulfilment_dine_in_enabled`, café tables CRUD, PWA checkout + table snapshot; default off)
* Takeaway orders
* Delivery orders
* Coupon system
* Offers and promotions
* Loyalty points
* Customer wallet
* Gift cards
* ~~Product ratings/reviews~~ (implemented: verified-purchase 1–5 ratings, optional review, catalog aggregates, admin moderation)
* Order scheduling
* Scheduled pickup
* Advanced inventory purchasing
* Supplier management
* Expense management
* Tax/GST invoices
* Barcode support
* Kitchen/barista display system
* Printable order tickets
* Thermal printer integration

---

# 50. Recommended Phase 3 Enhancements

Long-term features can include:

* Android/iOS customer application
* Barista mobile application
* Multiple café branches
* Branch-specific inventory
* Centralized product pricing
* Franchise management
* Supplier purchase orders
* Employee attendance
* Shift management
* Advanced accounting
* AI-based sales forecasting
* AI-based inventory forecasting
* Personalized drink recommendations
* Customer behaviour analytics
* Automatic product availability prediction

---

# 51. Important Business Rules

1. Customers may browse without authentication, but **login is mandatory for checkout**.
2. Exact product recipes and quantities must **never be visible to customers**.
3. Customers may see only simplified ingredient information such as **Coffee + Milk + Vanilla**.
4. Product cost and profit margin are **Administrator-only information**.
5. Baristas can access recipes and preparation instructions.
6. Every completed checkout must generate a **unique order number**.
7. Phase 1 orders will initially be created as **Pending Payment**.
8. Payment will initially be confirmed manually.
9. Baristas can update operational order statuses.
10. Inventory should support minimum-stock levels and refill requests.
11. Ingredient cost changes should automatically affect calculated production cost.
12. Different product sizes can have different recipes and costs.
13. Order and inventory history should not be permanently lost when records are updated.
14. Products should be capable of being temporarily marked unavailable without deletion.

---

# 52. Project Goal

The final system should provide a single platform through which the café can manage:

**Customers → Menu → Products → Flavours → Recipes → Ingredient Cost → Inventory → Orders → Barista Preparation → Payment Confirmation → Pickup → Sales & Profit Reporting**

The platform should make café operations easier while also providing customers with a modern, simple, mobile-friendly ordering experience.

The architecture should remain modular so the café can gradually evolve from a small takeaway/order-management platform into a complete **Café ERP, POS, inventory and digital ordering ecosystem** without rebuilding the core application.

Target application flow:

* Customer PWA -> Laravel API -> existing Services -> Repositories -> Models
* Administrator/Barista Blade -> existing Services -> Repositories -> Models

Customer API coverage should ultimately include:

* auth/account
* catalog/products/variants
* favourites
* cart
* checkout
* orders/tracking


## Dining flow

- **Normal:** Menu → Cart → Checkout (takeaway / delivery only)
- **Dining:** Table → Session → Rounds → Finish → Bill → Pay → Close
- PWA exposes Dining when `fulfilment.dining_enabled` is true (`/dining?table=CODE` preselect supported).
- Waiter role operates table service; food & beverage catalog uses product type + prep station.
- React PWA is the preferred mobile Waiter interface (`/waiter`); Blade Waiter remains fallback until production PWA verification.
- Waiter ordering reuses canonical Table → Dining Session → Dining Draft → Round Order → Preparation Tickets (no waiter-specific order architecture).
- Each Dining Session has an independent server-persisted draft; switching tables must not merge drafts.
- C1 customization/add-ons are shared between Customer and Waiter; prices remain server-owned.
- Phase C2 locks: guest cart survives login; ambiguous writes reconcile before retry; shared payment-state presentation is canonical; server owns commercial/operational truth.
- **L1.1 Served / Delivered-to-table:** Per dining **round** (canonical Order), after all active prep tickets are Ready. `POST /api/v1/waiter/sessions/{session}/rounds/{order}/served` (+ Blade Waiter/Operator/Admin). Idempotent. Resolves Ready-to-Serve alerts. Customer may see “Delivered to table”; customer does not confirm. Served does not imply payment or session close.
- **L1.2 Dining cancellation / exception matrix:** Server-owned `DiningRoundCancellationPolicy`. History is never deleted.

| Round / session state | Cancel? | Who | Reason | Inventory (F2) |
| --- | --- | --- | --- | --- |
| Accepted, no Preparing/Ready timestamps | Yes (normal) | Waiter / Operator / Admin | Optional note | `sale_reversal` allowed |
| Accepted/Preparing with material prep started | Yes (privileged) | Operator / Admin | **Required** structured reason | **No** auto-restore |
| Ready, not Served | Yes (privileged) | Operator / Admin | **Required** | **No** auto-restore |
| Served (`served_at` set) | **Blocked** | — | — | Never auto-restore; do not clear served metadata |
| Bill requested / finalized / awaiting payment | **Blocked** | — | — | — |
| Payment confirmed / session closed | **Blocked** | — | — | Refunds deferred |

Capabilities on Waiter session rounds: `can_cancel`, `cancel_requires_reason`, `can_void` (always false until void/comp), `cancellation_blocked_reason`. Endpoint: `POST .../rounds/{order}/cancel`.

**Deferred (not L1.2):** void / adjustment / comp, refunds, wastage ledger automation, post-payment financial correction.
