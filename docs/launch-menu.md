# The88Coffees — Launch menu

**Status: STOPPED — awaiting café decisions**  
**Date:** 31 Aug 2026 · **L2 re-audit:** 4 Sep 2026  

No confirmed real menu list (categories, products, sizes, or selling prices) was found in project docs, seeders, legacy menu tables, or local DB.

**Not used as launch source:**
- Demo `ProductSeeder` / category / flavour seeders (local/testing only)
- Illustrative ₹ examples in `docs/scope.md` (requirements samples, not café prices)

**Catalog left empty:** 0 categories · 0 flavours · 0 products · 0 variants · 0 homepage sections (clean production seed).

Do **not** invent menu items here. Fill the decision tables below, then enter via Administrator (preferred) or implement a data-driven import only after this file is complete. `LaunchCatalogSeeder` currently refuses to run.

**Readiness:** `php artisan coffee:launch-readiness` · `php artisan coffee:catalog-readiness`

---

## How to use this template

1. Café owner/ops fills **Confirmed categories**, **Confirmed products**, and **Variants & prices**.
2. Leave any unknown cell blank — do not guess.
3. Mark flavours only if drinks actually offer them.
4. Leave ProductTags / homepage empty until merchandising is decided.
5. After confirmation, enter data via Administrator (draft/`is_active=false` until readiness passes).

Suggested entry order: Categories → Flavours (if any) → Products (inactive) → Variants/prices → Recipes (later) → Images → Activate when READY → Homepage sections.

---

## Confirmed categories

| # | Category name | Active | Sort order | Notes |
| --- | --- | --- | --- | --- |
| 1 | | Yes / No | | |
| 2 | | | | |
| 3 | | | | |

*Keep names customer-friendly. Avoid over-fragmenting.*

---

## Confirmed flavours (optional)

Only flavours actually used by launch products.

| Flavour name | Used by products | Active | Notes |
| --- | --- | --- | --- |
| | | | |

*Leave this section empty if no flavour options at launch.*

---

## Confirmed products

| Product name | Category | Short description (optional) | Flavours (optional) | Customizable? | Notes |
| --- | --- | --- | --- | --- | --- |
| | | | | Yes / No / Unknown | |

*All new products should stay inactive until recipes + images + readiness pass.*

---

## Variants & prices

| Product | Variant / size label | Sort order | Selling price (₹) | Status |
| --- | --- | --- | --- | --- |
| | e.g. Small / Regular / Large / Single | | | Missing price / Confirmed |

*Do not invent prices. Price must be &gt; 0 when entered. Not every drink needs every size.*

---

## Current catalog summary (system)

| Category | Product | Variant | Price | Status |
| --- | --- | --- | --- | --- |
| — | — | — | — | No launch products yet |

`php artisan coffee:catalog-readiness` → Products: 0 | Ready: 0 | Incomplete: 0  
`php artisan coffee:launch-readiness` → expects blockers until this file is confirmed and catalog/payment/hours are configured.

---

## Unresolved decisions (block catalog creation)

- [ ] Final launch category list
- [ ] Final launch product list
- [ ] Per-product sizes / variant labels
- [ ] Real selling price for each variant
- [ ] Which flavours (if any) apply
- [ ] Customizable flags per product
- [ ] Short descriptions (optional; blank OK)
- [ ] Marketing tags (optional; do not auto-assign)
- [ ] Homepage section merchandising (after products exist)

---

## After café fills this file

Re-open the “Define and prepare the real launch menu structure” task. Implementation should:

1. Create only confirmed categories / flavours / draft products / priced variants  
2. Keep products inactive  
3. Skip recipes, stock, images, homepage until later phases  
4. Run `coffee:catalog-readiness` and update `docs/launch-data-todo.md`
