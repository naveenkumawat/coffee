# The88Coffees — Launch data to-do

**Purpose:** Actionable checklist to move from demo/test data to real café production data.  
**Last audited:** 4 Sep 2026 (L2 launch readiness)  
**Rules:** Do not invent missing phones, UPI, addresses, URLs, prices, recipes, stock, or legal copy. Demo seed ≠ launch data.

**Commands:**
- `php artisan coffee:launch-readiness` — full launch audit (exits non-zero on blockers; `--json` supported)
- `php artisan coffee:catalog-readiness` — product configuration completeness

**Import strategy (when menu is confirmed):** Prefer Administrator entry for day-one. Optional future `LaunchCatalogSeeder` is intentionally refused until `docs/launch-menu.md` is filled (`database/seeders/LaunchCatalogSeeder.php`). Do not run inventing imports. Never `migrate:fresh` in production.

**Local/demo vs production seed:**
- Always: IngredientCategory + ProductTag + SocialLink shells + optional `ADMIN_EMAIL`/`ADMIN_PASSWORD` Owner
- local/testing only: `DemoSeeder` (full café simulation)
- production: structural only — DemoSeeder hard-blocked

---

## Classification legend

| Status | Meaning |
| --- | --- |
| READY | System capability or confirmed baseline present |
| DEMO ONLY | Exists in local DemoSeeder — not for production |
| MISSING REAL DATA | Café must supply |
| OPTIONAL/DEFERRED | Nice-to-have or post-launch |

---

# BLOCKERS (must clear before production go-live)

Confirm with `php artisan coffee:launch-readiness` (must exit 0).

- [ ] **Brand name** in Website Settings (`business_name`) — local baseline may already be The88Coffees; verify on production DB
- [ ] **UPI ID** (`payment_upi_id`) — real café UPI
- [ ] **Payment QR image** uploaded + file present on public disk (`storage:link`)
- [ ] **Terms** + **Privacy** pages — café-approved legal copy only
- [ ] **Opening hours** — Website Settings text and/or Cafe Operating Hours (7-day schedule)
- [ ] **`docs/launch-menu.md` confirmed** — categories, products, sizes, prices filled by café (currently STOPPED)
- [ ] **Sellable catalog** — at least one **active** product that passes `coffee:catalog-readiness` (category, image, priced variants, recipes, station)
- [ ] **No `*@coffee.local` users** on production
- [ ] **Real staff accounts** with strong passwords: Owner/Admin, Operator, Barista, Chef, Waiter (if those panels are used)
- [ ] If **Dining enabled**: at least one **active** café table (real count/labels from café — do not invent)

---

# REQUIRED BEFORE PRODUCTION

- [ ] Customer phone + WhatsApp + pickup/visit address
- [ ] Short about text
- [ ] Payment instructions + payment WhatsApp
- [ ] Payment display name
- [ ] Delivery disclaimer approved in Website Settings (or explicit accept of config fallback wording)
- [ ] About / Visit / FAQ CMS pages
- [ ] Social URLs activated (Facebook / Instagram / WhatsApp) when café wants them public
- [ ] Confirm Takeaway + third-party Delivery still match ops
- [ ] GST/tax policy confirmed (enable + percent + GSTIN only with café values — do not invent)
- [ ] Product images (1:1 WebP/JPEG, ~50–150KB where practical) for every active product
- [ ] Recipes for every active variant/add-on that prepares food/drink (ingredients, units, qty > 0, station)
- [ ] Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real Sanctum/CORS/session/mail hosts — see `docs/production-deployment.md`
- [ ] `ADMIN_EMAIL` / `ADMIN_PASSWORD` only for Owner bootstrap (server secrets — never demo password)

---

# CAN COMPLETE AFTER LAUNCH

- [ ] Homepage sections merchandising
- [ ] ProductTags / marketing flags
- [ ] Promotions / referral tuning
- [ ] Inventory opening stock quantities (enter only when known — never fake)
- [ ] Min-stock thresholds / refill ops training
- [ ] Hero image polish
- [ ] Category images
- [ ] Meta WhatsApp Cloud API templates (keep `WHATSAPP_NOTIFICATIONS_ENABLED=false` until ready)
- [ ] Dining table QR deep-links
- [ ] Optional public email if not shown at launch

---

# Area audit (L2)

| Area | Status | Notes |
| --- | --- | --- |
| Café / business identity | READY (name/slogan baseline) / contact MISSING | Name+slogan may be set; phone/WhatsApp/address/about still required |
| Contact details | MISSING REAL DATA | |
| Opening hours / closures | MISSING REAL DATA | Structured `cafe_operating_hours` + closures Admin exist; demo schedule is DEMO ONLY |
| Fulfilment settings | READY (capability) | Takeaway+Delivery implemented; dine-in flag default off |
| Payment UPI / QR | MISSING REAL DATA | Manual payment launch model; no gateway |
| GST / tax | MISSING REAL DATA / OPTIONAL until café decides | Do not copy demo tax_enabled |
| Categories / products / variants / prices | MISSING REAL DATA | launch-menu.md empty; DB intentionally 0 on clean production seed |
| Flavours / add-ons / tags | OPTIONAL/DEFERRED until menu decisions | Demo flavours/add-ons are DEMO ONLY |
| Preparation stations | READY (model) | Must be set per real product before activate |
| Recipes / ingredients / units | MISSING REAL DATA | Blocker for activating products; do not guess recipes |
| Inventory opening stock / min stock | MISSING REAL DATA | Do not seed fake production quantities |
| Home sections | OPTIONAL/DEFERRED | |
| Product / hero / QR media | MISSING REAL DATA | PublicMedia + storage:link |
| Social links | READY (shells) / URLs MISSING | |
| Promotions | OPTIONAL/DEFERRED | |
| Staff / roles | MISSING REAL DATA on production | Demo `*@coffee.local` / `password` is DEMO ONLY |
| Tables / dining | MISSING REAL DATA | Demo T1–T8 DEMO ONLY; inventing table count forbidden |
| Delivery fee in-app | OPTIONAL/DEFERRED | `delivery_fee_amount` reserved/null; third-party customer-paid remains assumed launch rule — confirm |

---

# Catalog / launch-menu gaps

`docs/launch-menu.md` status: **STOPPED — awaiting café decisions**.

Exact gaps (nothing to import yet):
- Missing confirmed categories
- Missing confirmed products / descriptions
- Missing variants / size labels
- Missing selling prices
- Missing flavour / customizable decisions
- Missing station assignments (cannot assign without products)
- Missing recipes / ingredients
- Missing add-ons decisions
- Missing images

**Required café decisions before catalog import/Admin entry:** fill every non-optional cell in `docs/launch-menu.md`.

---

## Suggested order of work

1. Fill `docs/launch-menu.md` (café)
2. Contact + payment UPI/QR + hours + Terms/Privacy
3. Categories → draft products → variants/prices → recipes → images → activate when READY
4. Staff accounts (real passwords)
5. Tables only if Dining will launch
6. `php artisan coffee:launch-readiness` → exit 0
7. Then deployment hosts (`docs/production-deployment.md`) — **do not deploy without HTTPS hosts/access**
