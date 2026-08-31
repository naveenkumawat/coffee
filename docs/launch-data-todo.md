# The88Coffees — Launch data to-do

**Purpose:** Single checklist for supplying **real café information** through existing Administrator screens.  
**Last audited:** 31 Aug 2026 (local database)  
**Rules:** Do not treat demo seed values as launch-ready. Do not invent missing phones, UPI, addresses, URLs, prices, or legal copy.

**Where to enter data:** Administrator → Website Settings, Social Links, Categories, Flavours, Ingredients, Products, Homepage Sections.

**Local baseline (31 Aug 2026):** Brand name + slogan set in Website Settings; Facebook / WhatsApp / Instagram social shells exist (**inactive**, blank URLs → not in public footer); remaining Website Settings empty; **0** categories / flavours / products / homepage sections; **7** ProductTags present.  
`php artisan coffee:catalog-readiness` → Products: 0 | Ready: 0 | Incomplete: 0.

Demo catalog (18 products, fake contact/UPI, sample CMS) exists only in **local/testing seeders** — not production. Do not copy those into this baseline.

---

# Brand & Business

- [x] Business name — set to **The88Coffees** (Administrator → Website Settings → Business)
- [x] Home slogan / hero subtitle — set to **Sip. Relax. Enjoy.**
- [ ] Hero title — confirm customer-facing wording (or leave blank if unused) — intentionally blank
- [ ] Short about text — real café blurb — intentionally blank
- [ ] Customer phone — real number — intentionally blank
- [ ] WhatsApp number — real number (also drives WhatsApp social when URL blank) — intentionally blank
- [ ] Email — real public email if the café wants one shown — intentionally blank in Website Settings
- [ ] Pickup / visit address — real address — intentionally blank
- [ ] Opening hours — real hours — intentionally blank

*Status:* Brand + slogan **CONFIGURED**. Contact / about still **NEEDS REAL VALUE**.

---

# Social Media

Platforms expected at launch: Facebook, WhatsApp, Instagram (dynamic Social Links — not hardcoded in the PWA).

- [x] Ensure Facebook / WhatsApp / Instagram rows exist (structural shells; sort 1 / 2 / 3; blank URLs; inactive until configured)
- [ ] Facebook — real page URL; set active when ready
- [ ] Instagram — real profile URL; set active when ready
- [ ] WhatsApp — leave URL blank to use Website Settings WhatsApp, **or** set explicit `wa.me` URL; set active when WhatsApp number is ready
- [ ] Confirm inactive / empty platforms do **not** show an empty footer row (verified with shells inactive → public `social_links: []`)
- [ ] Confirm sort order matches desired icon order (shells: Facebook 1, WhatsApp 2, Instagram 3)

*Status:* Structural shells **CONFIGURED**; platforms are **not** launch-ready until real URLs / WhatsApp contact exist and records are activated.

---

# Payment

Manual UPI — configure in Website Settings → Payment (upload QR requires `storage:link`).

- [ ] Payment display name (e.g. how UPI appears to customers) — intentionally blank in Website Settings
- [ ] UPI ID — real café UPI — intentionally blank
- [ ] Payment phone — real payment number if used — intentionally blank
- [ ] Payment QR image — upload real QR file — intentionally blank
- [ ] Payment instructions — real customer steps (pay → upload screenshot / WhatsApp) — intentionally blank in Website Settings
- [ ] Payment WhatsApp — number for payment confirmation / screenshots — intentionally blank

*Status:* **NEEDS REAL VALUE**. Website Settings payment fields empty (no demo UPI). Note: local/API may still surface generic `COFFEE_*` / `APP_NAME` env fallbacks until production env is cleaned — that is separate from Admin settings.

---

# Fulfilment

Supported modes: **Takeaway** and **Delivery** (third-party; charges paid separately by the customer — no invented café delivery fee).

- [ ] Confirm café will offer Takeaway
- [ ] Confirm café will offer Delivery via third party
- [ ] Delivery disclaimer — Website Settings empty; config fallback third-party wording left as infrastructure fallback (not marked approved business copy)
- [ ] Confirm customer-facing Ready / delivery wording matches café ops

*Status:* Behaviour **CONFIGURED**. Disclaimer copy not yet café-approved in Website Settings.

---

# CMS

Pages: About · Visit/Contact · FAQ · Terms · Privacy (Website Settings → Pages).

- [ ] About — real story / café copy — intentionally blank
- [ ] Visit / Contact — real visit guidance (pairs with phone/address/hours) — intentionally blank
- [ ] FAQ — real order/payment/pickup Q&A — intentionally blank
- [ ] Terms — **real legal/business terms** (do not invent; supply approved copy) — intentionally blank
- [ ] Privacy — **real privacy notice** (do not invent; supply approved copy) — intentionally blank

*Status:* **NEEDS REAL VALUE**. Demo CMS text was not copied.

---

# Catalog

Enter **real** menu items in Administrator. Do **not** assume demo products belong in production.

**Blocked (31 Aug 2026):** No confirmed real menu source in repo/docs/DB. Catalog intentionally remains empty. Fill [`docs/launch-menu.md`](launch-menu.md) with café decisions before creating categories/products.

### Decide first (see launch-menu.md)
- [ ] Final list of launch categories
- [ ] Final list of launch flavours (optional per café)
- [ ] Final list of launch products (name, description, category, sizes, prices)
- [ ] Which demo names (if any) are intentionally kept vs discarded

### Structure entry (only after decisions confirmed)
- [ ] Confirmed categories created
- [ ] Confirmed flavours created
- [ ] Confirmed products created (draft / inactive)
- [ ] Confirmed variants/prices created

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
- [ ] Create real categories (active + sort order) — catalog kept empty until menu decisions
- [ ] Create real flavours if used
- [ ] Create each launch product as draft (`inactive`) until ready
- [ ] Add variants/sizes + **real** prices
- [ ] Assign flavours / customizable flags as needed
- [ ] Assign ProductTags only when marketing decision is made (optional)
- [ ] Activate only products that pass readiness (see Recipes / Images)

*Local catalog:* **0 products** — nothing READY for sale yet (intentional).

---

# Recipes

For each **intended launch** product / variant:

- [ ] Recipe lines present for every active size
- [ ] Ingredient records exist (brands/categories as needed)
- [ ] Major ingredients / customer-facing ingredient summary reviewed
- [ ] Run `php artisan coffee:catalog-readiness` and fix **configuration** failures (do not fake recipes just to go green)

*Status:* Deferred until real products exist.

---

# Inventory

- [ ] List ingredients required for launch recipes
- [ ] Enter opening stock only when real quantities are known
- [ ] Treat “stock concern” separately from “incomplete configuration”
- [ ] Review refill / low-stock process with staff (ops, not a code task)

*Status:* Deferred — do not invent opening stock numbers.

---

# Product Images

- [ ] Photograph / source real images for each launch product
- [ ] Upload via Administrator product form (public media / `storage:link`)
- [ ] Optional: category images, hero image
- [ ] Confirm payment QR uploaded (see Payment)

*Status:* Deferred — do not generate fake product photos.

---

# Homepage

Homepage rails come from **Homepage Sections** (not ProductTags).

- [ ] Decide section titles / order for launch (e.g. pickup picks, new items — use café wording)
- [ ] Assign only products intended to appear
- [ ] Set `max_items` and sort order
- [ ] Keep inactive / empty sections off
- [ ] Re-check after products become READY

Demo sections (seed only): “Pickup-ready picks”, “New on the menu”, “Bestsellers” — **not** created in this baseline (wait for real products).

*Local:* **0** homepage sections (intentional).

---

## Suggested order of work

1. ~~Brand name + slogan~~ ✅  
2. Business contact + WhatsApp  
3. Payment (UPI / QR / instructions)  
4. Social URLs + activate shells  
5. CMS pages (Terms/Privacy with approved copy)  
6. Categories → products (draft) → variants/prices → recipes → images → activate when READY — **blocked** until [`docs/launch-menu.md`](launch-menu.md) is filled by the café
7. Inventory opening stock  
8. Homepage sections  
9. `php artisan coffee:catalog-readiness` + customer preview  

When this list is complete, return to deployment (`docs/production-deployment.md`) with real HTTPS hosts.
