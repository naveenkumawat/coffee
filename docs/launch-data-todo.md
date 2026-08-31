# The88Coffees — Launch data to-do

**Purpose:** Single checklist for supplying **real café information** through existing Administrator screens.  
**Last audited:** 31 Aug 2026 (local database)  
**Rules:** Do not treat demo seed values as launch-ready. Do not invent missing phones, UPI, addresses, URLs, prices, or legal copy.

**Where to enter data:** Administrator → Website Settings, Social Links, Categories, Flavours, Ingredients, Products, Homepage Sections.

**Local snapshot (this machine):** Website Setting *keys* exist but **all values empty**; **0** categories / flavours / products / social links / homepage sections; **7** ProductTags present (New, Top Seller, Featured, Seasonal, Popular, Limited, Recommended).  
`php artisan coffee:catalog-readiness` → Products: 0 | Ready: 0 | Incomplete: 0.

Demo catalog (18 products, fake contact/UPI, sample CMS) exists only in **local/testing seeders** — not production.

---

# Brand & Business

- [ ] Business name — set to **The88Coffees** (Administrator → Website Settings → Business)
- [ ] Home slogan / hero subtitle — set to **Sip. Relax. Enjoy.**
- [ ] Hero title — confirm customer-facing wording (or leave blank if unused)
- [ ] Short about text — real café blurb
- [ ] Customer phone — real number
- [ ] WhatsApp number — real number (also drives WhatsApp social when URL blank)
- [ ] Email — real public email if the café wants one shown
- [ ] Pickup / visit address — real address
- [ ] Opening hours — real hours

*Status:* **NEEDS REAL VALUE** (local values currently empty). Targets for brand + slogan are known; contact fields are not.

---

# Social Media

Platforms expected at launch: Facebook, WhatsApp, Instagram (dynamic Social Links — not hardcoded in the PWA).

- [ ] Ensure Facebook / WhatsApp / Instagram rows exist (seed shells or create in Social Links)
- [ ] Facebook — real page URL; set active when ready
- [ ] Instagram — real profile URL; set active when ready
- [ ] WhatsApp — leave URL blank to use Website Settings WhatsApp, **or** set explicit `wa.me` URL; keep active when WhatsApp is ready
- [ ] Confirm inactive / empty platforms do **not** show an empty footer row
- [ ] Confirm sort order matches desired icon order

*Status:* **NEEDS REAL VALUE** for Facebook/Instagram URLs. WhatsApp can reuse business WhatsApp once that number is set. Local DB currently has **0** social rows (run structural seed or create in Admin).

---

# Payment

Manual UPI — configure in Website Settings → Payment (upload QR requires `storage:link`).

- [ ] Payment display name (e.g. how UPI appears to customers)
- [ ] UPI ID — real café UPI
- [ ] Payment phone — real payment number if used
- [ ] Payment QR image — upload real QR file
- [ ] Payment instructions — real customer steps (pay → upload screenshot / WhatsApp)
- [ ] Payment WhatsApp — number for payment confirmation / screenshots

*Status:* **NEEDS REAL VALUE**. Demo seeder UPI/phones must never be used live.

---

# Fulfilment

Supported modes: **Takeaway** and **Delivery** (third-party; charges paid separately by the customer — no invented café delivery fee).

- [ ] Confirm café will offer Takeaway
- [ ] Confirm café will offer Delivery via third party
- [ ] Delivery disclaimer — review/edit Website Settings → Fulfilment (fallback config text exists for third-party wording)
- [ ] Confirm customer-facing Ready / delivery wording matches café ops

*Status:* **CONFIGURED** (product behaviour). Disclaimer copy: **NEEDS REAL VALUE** review (may keep default third-party wording if café agrees).

---

# CMS

Pages: About · Visit/Contact · FAQ · Terms · Privacy (Website Settings → Pages).

- [ ] About — real story / café copy
- [ ] Visit / Contact — real visit guidance (pairs with phone/address/hours)
- [ ] FAQ — real order/payment/pickup Q&A
- [ ] Terms — **real legal/business terms** (do not invent; supply approved copy)
- [ ] Privacy — **real privacy notice** (do not invent; supply approved copy)

*Status:* **NEEDS REAL VALUE**. Local page bodies empty. Demo seeder copy is not launch-ready (especially Terms/Privacy).

---

# Catalog

Enter **real** menu items in Administrator. Do **not** assume demo products belong in production.

### Decide first
- [ ] Final list of launch categories
- [ ] Final list of launch flavours (optional per café)
- [ ] Final list of launch products (name, description, category, sizes, prices)
- [ ] Which demo names (if any) are intentionally kept vs discarded

### Demo-only reference (local seeder — not production-ready)

| Category (demo) | Demo products |
| --- | --- |
| Hot Coffee | Cafe Latte, Cappuccino, Flat White, Americano, Hazelnut Mocha, Seasonal Spice Latte (paused demo) |
| Cold Coffee | Iced Vanilla Latte, Iced Americano, Cold Brew, Caramel Iced Latte |
| Frappes | Mocha Frappe, Vanilla Bean Frappe, Caramel Crunch Frappe |
| Tea & Matcha | Classic Masala Chai, Matcha Latte, Iced Matcha Latte |
| Pastries | Butter Croissant, Chocolate Muffin |

Demo flavours: Vanilla, Hazelnut, Caramel, Mocha, Honey.  
Demo sizes typically Regular/Large (pastries: Single) with **sample** prices — replace with real prices.

### Admin entry checklist
- [ ] Create real categories (active + sort order)
- [ ] Create real flavours if used
- [ ] Create each launch product as draft (`inactive`) until ready
- [ ] Add variants/sizes + **real** prices
- [ ] Assign flavours / customizable flags as needed
- [ ] Assign ProductTags only when marketing decision is made (optional)
- [ ] Activate only products that pass readiness (see Recipes / Images)

*Local catalog:* **0 products** — nothing READY for sale yet.

---

# Recipes

For each **intended launch** product / variant:

- [ ] Recipe lines present for every active size
- [ ] Ingredient records exist (brands/categories as needed)
- [ ] Major ingredients / customer-facing ingredient summary reviewed
- [ ] Run `php artisan coffee:catalog-readiness` and fix **configuration** failures (do not fake recipes just to go green)

*Status:* **NEEDS REAL VALUE** once products exist. Do not activate incomplete products.

---

# Inventory

- [ ] List ingredients required for launch recipes
- [ ] Enter opening stock only when real quantities are known
- [ ] Treat “stock concern” separately from “incomplete configuration”
- [ ] Review refill / low-stock process with staff (ops, not a code task)

*Status:* **NEEDS REAL VALUE**. Do not invent opening stock numbers.

---

# Product Images

- [ ] Photograph / source real images for each launch product
- [ ] Upload via Administrator product form (public media / `storage:link`)
- [ ] Optional: category images, hero image
- [ ] Confirm payment QR uploaded (see Payment)

*Status:* **NEEDS REAL VALUE**. PWA placeholders are fine until real photos exist — do not generate fake product photos.

---

# Homepage

Homepage rails come from **Homepage Sections** (not ProductTags).

- [ ] Decide section titles / order for launch (e.g. pickup picks, new items — use café wording)
- [ ] Assign only products intended to appear
- [ ] Set `max_items` and sort order
- [ ] Keep inactive / empty sections off
- [ ] Re-check after products become READY

Demo sections (seed only): “Pickup-ready picks”, “New on the menu”, “Bestsellers” — **not** auto-created in production.

*Local:* **0** homepage sections.

---

## Suggested order of work

1. Brand + business contact + WhatsApp  
2. Payment (UPI / QR / instructions)  
3. Social URLs  
4. CMS pages (Terms/Privacy with approved copy)  
5. Categories → products (draft) → variants/prices → recipes → images → activate when READY  
6. Inventory opening stock  
7. Homepage sections  
8. `php artisan coffee:catalog-readiness` + customer preview  

When this list is complete, return to deployment (`docs/production-deployment.md`) with real HTTPS hosts.
