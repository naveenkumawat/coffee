# Customer PWA UI/UX Audit

Last updated: August 30, 2026  
Status: Phase C8 product tags + unified ordering complete — see `docs/pwa-launch-checklist.md` for smoke test + blockers  
Theme inspiration: layout/component patterns only (former Ombe demo theme is not product identity)  
Demo data: local/testing seeders via `php artisan migrate:fresh --seed`  
Reviewed against: `docs/pwa-scope.md`, `docs/pwa-theme-map.md`, `customer-pwa` routes/components/`theme.css`

## Progress

| Item | Status |
| --- | --- |
| Design tokens (space/type/radius/shadow/safe-area/touch) | Done (C1) |
| StickyActionBar above fixed bottom nav | Done (C1 / P0) |
| Toast feedback (add-to-cart, favourites, errors) | Done (C1 / P0) |
| Customer-facing copy (no Sanctum/API/server jargon) | Done (C1 / P0) |
| Payment backtick / raw formatting | Done (C1 / P0) |
| Motion + `prefers-reduced-motion` | Done (C1) |
| Fixed bottom nav + content clearance | Done (C1) |
| Ordering path polish (PDP → Cart → Checkout → Confirmation) | Done (C2) |
| Home + Menu catalog polish | Done (C3) |
| Orders + Tracking + Account polish | Done (C4) |
| Auth + Content polish | Done (C5) |
| Final PWA QA + launch readiness | Done (C6) |
| Brand identity: The88Coffees lockup + semantic brand tokens | Done (C7) |
| Manifest / theme-color / offline shell branding | Done (C7) |
| Dynamic marketing tags + shared ProductOrderControl | Done (C8) |

## C8 tags + ordering (final)

| Priority | Finding | Fix |
| --- | --- | --- |
| P0 | Hardcoded NEW/TOP/VEG badges | Replaced with API `tags[]` + shared `ProductTags` (VEG no longer promotional) |
| P0 | Card vs detail different size/Add flows | Unified `ProductOrderControl` (`compact` / `full`) — same multi-size expand/stepper everywhere |
| P1 | Badge overflow on cards | Compact mode shows max 2 tags + `+N` overflow |
| P1 | Unknown variants used QuickAdd sheet | Inline generic variant chips via same order control |

## C7 branding findings (final)

| Priority | Finding | Fix |
| --- | --- | --- |
| P0 | Header/auth still used Ombe wordmark PNG | Replaced with cup mark SVG + **The88Coffees** wordmark (`BrandLogo`) |
| P0 | Manifest/title/meta still “Coffee Cafe” | Updated to The88Coffees / 88Coffees short name |
| P1 | Palette aliased as generic green + purple-gray accent | Added `--brand-*` tokens from cup mark (green `#04764e`, cream `#d4b896`, warm surfaces); purple-gray accents routed to charcoal secondary |
| P1 | Fallback copy said “Coffee Cafe” | Shared brand constant + Website Settings seeder business name |
| P2 | Product card rhythm | Minor action-zone gap/height tighten only — ordering interactions unchanged |

## Remaining visual notes (non-blocking)

- Real product photography still improves catalog sameness more than further CSS polish.
- Self-host Google Fonts later if launch network budget requires it (**P2**).

## Priority legend

| Priority | Meaning |
| --- | --- |
| **P0** | Blocks usable ordering / launch confidence |
| **P1** | Important UX improvement for daily use |
| **P2** | Polish / visual refinement |

## Shared design-system gaps (do first)

Implement these before page-by-page redesign. Prefer tokens + shared components over per-page CSS.

| Gap | Priority | Current | Recommended |
| --- | --- | --- | --- |
| Design tokens incomplete | P1 | Few CSS vars (`--coffee-*`); spacing/radius/shadows ad hoc | **Done (C1 + C7)** `--brand-*` + `--coffee-*` aliases |
| Typography roles | P1 | Poppins/Raleway used inconsistently; many competing H2/eyebrow pairs | **Partial (C1)** type tokens added; page hierarchy still pending redesign |
| Buttons | P1 | Bootstrap `btn` + pills mixed with link-buttons | **Partial (C1)** min touch + focus/press feedback |
| Cards | P1 | Many bordered+heavy-shadow cards (`product-card`, `summary-card`, `auth-card`, …) | **Partial (C1)** shared surface recipe lightened |
| Form controls | P1 | `coffee-input` + Bootstrap forms | **Partial (C1)** focus/touch consistency |
| Chips / filters | P1 | Category/flavour pills OK; home pills always “active-soft” | **Partial (C1)** idle chips on Home; press feedback |
| Badges | P2 | `ProductBadges` + `auth-badge` reused for status | Status badge map (pending/ready/cancelled) separate from marketing badges |
| Headers | P1 | Home `Header` overcrowded; other pages `PageHeader` | **Done (C3/C7)** slim Home header + BrandLogo |
| Bottom nav | P1 | Correct 5 destinations; sticky (not fixed); label “Sign in” when guest | **Done (C1)** fixed + safe-area; cart badge bump |
| Sticky action bar | **P0** | `StickyActionBar` exists but **no CSS** for `.sticky-action-bar*` | **Done (C1)** fixed above bottom nav |
| Skeletons | P2 | Generic media+lines only | **Partial (C1)** card/list/hero variants + shimmer |
| Empty / error | P1 | Components exist; copy often technical | Customer language; optional illustration; primary CTA always |
| Toast / feedback | **P0** | Almost none; errors inline; add-to-cart silent on Home/Menu | **Done (C1)** global toast host + store |
| Motion rules | P2 | Minimal CSS motion; no `prefers-reduced-motion` | **Done (C1)** shared transitions + reduced-motion |
| Customer copy tone | **P0** | “Sanctum session”, “Server-authoritative”, “API”, backticks in payment copy | **Done (C1)** cafe-facing copy pass |

## Out of scope for redesign phase (reminders)

- No new business modules  
- No Admin/Barista Blade changes  
- Former Ombe delivery/wallet/chat/rewards pages are not Coffee targets  
- Backend remains source of truth for prices, cart, payment, orders  
- Ordering interactions (size controls, bag-plus, sheets) locked — visual only  
