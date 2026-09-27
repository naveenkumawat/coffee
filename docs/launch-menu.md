# Sip The Soul — Final Launch Menu Master (L1)

**Status: STOPPED — awaiting owner confirmation of every required launch-menu decision**  
**Master created:** 20 Sep 2026 · **Prior empty template:** 31 Aug 2026 · **L2 re-audit:** 4 Sep 2026  

This file is the **only** human-reviewable source of truth for the launch catalog **before** any real import or Administrator entry.  
Do **not** lift protection, run `LaunchCatalogSeeder`, or treat the local demo catalog as launch data until every required cell below is café-confirmed.

`LaunchCatalogSeeder` currently **refuses** to run. `php artisan coffee:launch-readiness` / `coffee:catalog-readiness` remain blockers until this master is complete and entered.

---

## Readiness summary

Counts are from **this file only** (confirmed cells). Demo DB / `ProductSeeder` are **not** counted.

| Metric | Count |
| --- | ---: |
| Total launch products listed | 0 |
| Complete products (name + category + type + station + ≥1 size + price for each listed size + launch-active yes/no) | 0 |
| Products missing prices | 0 *(no products listed yet)* |
| Products missing size decisions | 0 *(no products listed yet)* |
| Products missing station | 0 *(no products listed yet)* |
| Products awaiting confirmation | 0 *(entire menu unconfirmed)* |

**Launch-active yes** means the café intends the item to sell at go-live. Catalog import must still keep products **inactive** until recipes, images, and `coffee:catalog-readiness` pass (later phases — not this file).

---

## What is already confirmed (preserve)

| Item | Status |
| --- | --- |
| Café brand name **Sip The Soul** | Confirmed in branding / Website Settings baseline — **not** a catalog item |
| Drink size **options the business supports** | **250 ml · 350 ml · 500 ml** — pick per drink; **do not** assign all three by default |
| Product type values the system accepts | `beverage` or `food` |
| Preparation station values the system accepts | `bar` or `kitchen` |
| Categories | **TBD** — none confirmed |
| Product names | **TBD** — none confirmed |
| Per-size selling prices (₹) | **TBD** — none confirmed |
| Short customer descriptions | **TBD** |
| Recipes / ingredients / stock / images / flavours / add-ons / tags / homepage | **Out of L1 import** — do not invent here |

**Not launch sources (do not copy into this master):**

- Local/demo `ProductSeeder`, `DemoFoodCatalogSeeder`, flavours, prices, or live local DB rows (including incomplete local items such as a draft Rose Latte)
- Illustrative ₹ / size examples in `docs/scope.md`
- CMS/menu legacy tables
- Empty production-style catalog (0 categories · 0 products on a clean production seed)

---

## Catalog fields this master must capture

Mapped to Administrator product create (required for later entry). Unknown = **TBD**. Never fill from demo.

| Launch-menu column | System field | Required to leave STOPPED |
| --- | --- | --- |
| Category | `product_categories.name` | Yes |
| Product name | `products.name` | Yes |
| Product type | `products.product_type` (`beverage` / `food`) | Yes |
| Preparation station | `products.preparation_station` (`bar` / `kitchen`) | Yes |
| Available sizes / variants | `product_variants.name` + serving size | Yes (≥1 variant) |
| Selling price per size | `product_variants.price` (₹, &gt; 0) | Yes (every listed size) |
| Launch active | café intent yes/no *(import still starts inactive)* | Yes |
| Short customer description | `products.short_description` | No — blank allowed |
| Notes / missing information | this file only | — |

Drink variants: if type is beverage, size must be an owner-chosen subset of **250 / 350 / 500 ml** (label + millilitres). Food variants: owner-defined labels (e.g. piece / plate) — **TBD**, not invented.

---

## How to fill

1. Owner confirms **category names** (customer-facing; avoid over-fragmenting).
2. Under each category, copy a **product card** and fill only known facts.
3. For each drink, tick **only** the sizes that will sell. Leave unused sizes out (do not list them as ₹ 0).
4. Leave unknown cells as **TBD**. Do not guess. Do not paste demo prices.
5. When a product card has no TBD in required columns, mark it complete in the readiness counts.
6. Flavours / customizable / tags / recipes stay optional or later — do not block listing a product if those are TBD, but **do** block STOPPED removal until required columns above are confirmed for **every** launch product.

Suggested later entry order (not this phase): Categories → Flavours (if any) → Products (inactive) → Variants/prices → Recipes → Images → Activate when READY → Homepage.

---

## Confirmed categories

| # | Category name | Sort | Notes |
| --- | --- | --- | --- |
| 1 | TBD | TBD | |
| 2 | TBD | TBD | |
| 3 | TBD | TBD | |

Add rows as the owner names them. Delete unused TBD rows when the list is final.

---

## Products by category

No categories or products are confirmed. Duplicate the card below **under a named category heading** once the owner supplies names.

### Category: TBD

*Replace “TBD” with a confirmed category name. Repeat the heading for each category.*

#### Product card (copy)

| Field | Value |
| --- | --- |
| Category | TBD *(must match a confirmed category)* |
| Product name | TBD |
| Product type | TBD — `beverage` / `food` |
| Preparation station | TBD — `bar` / `kitchen` |
| Launch active at go-live | TBD — Yes / No |
| Short customer description | TBD *(optional; leave blank if none)* |
| Notes / missing information | TBD |

**Sizes & selling prices** (drinks: only rows the café will sell)

| Size / variant | Sell this size? | Price (₹) | Status |
| --- | --- | --- | --- |
| 250 ml | TBD — Yes / No / N/A (food) | TBD | Missing size decision / Missing price / Confirmed |
| 350 ml | TBD | TBD | |
| 500 ml | TBD | TBD | |
| Other (food label + unit) | TBD | TBD | |

---

## Confirmed flavours (optional)

Only flavours actually used by launch products. Leave empty if none at launch.

| Flavour name | Used by products | Active | Notes |
| --- | --- | --- | --- |
| TBD | | | *Leave blank if unused* |

---

## Unresolved decisions (block catalog creation and STOPPED removal)

- [ ] Final launch category list
- [ ] Final launch product list (every product named)
- [ ] Product type (`beverage` / `food`) per product
- [ ] Preparation station (`bar` / `kitchen`) per product
- [ ] Per-product size set (subset of 250 / 350 / 500 ml for drinks; food labels TBD)
- [ ] Real selling price (₹) for **each** selected size
- [ ] Launch-active yes/no per product
- [ ] Short descriptions (optional; blank OK)
- [ ] Which flavours (if any) apply
- [ ] Customizable flags (optional until recipes/add-ons)
- [ ] Marketing tags (optional; do not auto-assign)
- [ ] Homepage merchandising (after products exist)

Recipes, ingredients, opening stock, and images are **not** decided in L1 and must not be invented to “complete” this file.

---

## After the owner confirms this file

Re-open catalog structure / import only then. Implementation should:

1. Create only confirmed categories / flavours / draft products / priced variants  
2. Keep products inactive  
3. Skip recipes, stock, images, homepage until later phases  
4. Run `coffee:catalog-readiness` and update `docs/launch-data-todo.md`  
5. Remove **STOPPED** only when required columns have no TBD for every listed launch product
