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

A future-ready inventory system should link recipe ingredients with order preparation.

Example:

Customer orders:

**2 × Cold Coffee Classic**

Recipe requires per drink:

* Coffee: 2 g
* Milk: 110 ml
* Ice Cream: 130 g

Required inventory:

* Coffee: 4 g
* Milk: 220 ml
* Ice Cream: 260 g

The system can deduct inventory automatically when the order reaches a predefined status such as:

**Preparing** or **Completed**.

The exact deduction point should be configurable to prevent inaccurate inventory from cancelled orders.

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

Administrator reports should provide business and operational information.

### Sales Reports

* Daily sales
* Weekly sales
* Monthly sales
* Date-range sales
* Product-wise sales
* Category-wise sales

### Order Reports

* Total orders
* Completed orders
* Cancelled orders
* Pending orders
* Average order value

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
* QR-based menu
* Table ordering
* Dine-in orders
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
