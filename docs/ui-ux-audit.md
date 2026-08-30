# Customer PWA UI/UX Audit

Last updated: August 30, 2026  
Status: Phase C6 final PWA QA complete — see `docs/pwa-launch-checklist.md` for smoke test + blockers  
Theme inspiration: `theme/pwa/ombe-bootstrap-pwa.vercel.app` (layout/component patterns only)  
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
| Design tokens incomplete | P1 | Few CSS vars (`--coffee-*`); spacing/radius/shadows ad hoc | **Done (C1)** tokens for space/type/radius/shadow/states/safe-area/touch |
| Typography roles | P1 | Poppins/Raleway used inconsistently; many competing H2/eyebrow pairs | **Partial (C1)** type tokens added; page hierarchy still pending redesign |
| Buttons | P1 | Bootstrap `btn` + pills mixed with link-buttons | **Partial (C1)** min touch + focus/press feedback |
| Cards | P1 | Many bordered+heavy-shadow cards (`product-card`, `summary-card`, `auth-card`, …) | **Partial (C1)** shared surface recipe lightened |
| Form controls | P1 | `coffee-input` + Bootstrap forms | **Partial (C1)** focus/touch consistency |
| Chips / filters | P1 | Category/flavour pills OK; home pills always “active-soft” | **Partial (C1)** idle chips on Home; press feedback |
| Badges | P2 | `ProductBadges` + `auth-badge` reused for status | Status badge map (pending/ready/cancelled) separate from marketing badges |
| Headers | P1 | Home `Header` overcrowded; other pages `PageHeader` | **Done (C3)** slim Home header; PageHeader elsewhere |
| Bottom nav | P1 | Correct 5 destinations; sticky (not fixed); label “Sign in” when guest | **Done (C1)** fixed + safe-area; cart badge bump |
| Sticky action bar | **P0** | `StickyActionBar` exists but **no CSS** for `.sticky-action-bar*` | **Done (C1)** fixed above bottom nav |
| Skeletons | P2 | Generic media+lines only | **Partial (C1)** card/list/hero variants + shimmer |
| Empty / error | P1 | Components exist; copy often technical | Customer language; optional illustration; primary CTA always |
| Toast / feedback | **P0** | Almost none; errors inline; add-to-cart silent on Home/Menu | **Done (C1)** global toast host + store |
| Motion rules | P2 | Minimal CSS motion; no `prefers-reduced-motion` | **Done (C1)** shared transitions + reduced-motion |
| Customer copy tone | **P0** | “Sanctum session”, “Server-authoritative”, “API”, backticks in payment copy | **Done (C1)** cafe-facing copy pass |

## Screen findings

### Home

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Top bar packs Saved + Sign in + Cart + decorative mark; duplicates bottom nav | **Done (C3)** slim brand header + Order CTA; nav stays in bottom bar | Shared `Header` |
| P1 | Hero uses inset card + small media (not Ombe full-bleed energy) | **Done (C3)** stronger hero plane; single Explore menu CTA | Hero entrance |
| P1 | Featured / New / Bestsellers all use same dense grid; long scroll with seeded 18 products | **Done (C3)** Featured grid + New/Bestsellers horizontal rails | ProductRail snap |
| P1 | Quick-add uses default variant only (skips size) | **Done (C3)** multi-variant → detail; single-variant quick Add | Toast + badge |
| P2 | Category pills all look selected (`active-soft`) | **Done (C3)** idle chips on Home | Chip press |
| P2 | About/contact preview + link grid compete with order CTA | **Done (C3)** secondary about/WhatsApp; quiet legal links | — |

### Menu

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Filters + search consume first viewport; results buried | **Done (C3)** compact sticky discovery + results count | Sticky filter bar |
| P1 | Full-page skeleton on every filter change | **Done (C3)** filters stay mounted; skeleton only results | Soft fade results |
| P2 | No active filter chips summary beyond Clear | **Done (C3)** dismissible active filter chips | Chip dismiss |
| P2 | Empty “Clear filters” uses navigation not in-place reset when coming from EmptyState | **Done (C3)** EmptyState `onAction` clears in place | — |

### Product Detail

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Add CTA not sticky; fights bottom nav on small phones | **Done (C2)** StickyActionBar + qty inline | Price update live |
| P1 | Flavours not selectable (only badges/meta); customizable promise weak | **Done (C2)** flavour chips + barista note when customizable | Chip display |
| P1 | Placeholder product imagery (rotating assets) | Real product images when available; better empty art | Image fade-in |
| P2 | Immediate navigate to cart after add | **Done (C2)** stay + toast “Added” | Toast + badge |

### Favourites

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Same product grid as Menu; no heart-empty illustration strength | Dedicated empty art; keep ProductCard | Heart toggle spring |
| P2 | Hard to discover from bottom nav | Account shortcut already exists; optional Home Saved only if decluttered | — |

### Login / Register / Forgot / Reset

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| **P0** | Auth card badge “Sanctum session” | **Done (C1/C5)** cafe-facing AuthCard copy | Shared `AuthCard` |
| P1 | Forms functional but generic vs Ombe welcome framing | **Done (C5)** branded auth card + clearer hierarchy | Field focus ring |
| P1 | Register field density | **Done (C5)** compact essentials + optional phone | — |
| P2 | Success states only navigate | **Done (C5)** success toast on login/register/reset | Toast |

### Account

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | “Customer session active” / developer tone | **Done (C4)** friendly greeting hero | Account hero |
| P1 | Profile + password + long link list = dense scroll | **Done (C4)** Orders/Favourites first; cafe links secondary; logout bottom | List rows |
| P2 | Logout in header corner easy to miss/mis-tap | **Done (C4)** logout separated at page bottom | — |

### Cart

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| **P0** | Sticky checkout CTA unstyled / not sticky | **Done (C1/C2)** StickyActionBar above bottom nav | Bar slide-up |
| P1 | Copy “synced from the API” / “Server totals” | **Done (C1/C2)** “Pickup total” / customer copy | — |
| P1 | Clear cart has no confirm | Confirm remove-all | Sheet |
| P2 | Quantity/remove feedback only busy-disable | **Done (C2)** toast on qty/remove/clear | Stepper press |

### Checkout

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Many fields: customer + pickup often duplicated | **Done (C2)** “Same as my contact details” toggle | Progressive fields |
| **P0** | Sticky place-order bar missing styles | **Done (C1/C2)** StickyActionBar | Disable + busy on submit |
| P1 | Risk of double submit mitigated in code; UI feedback weak | **Done (C2)** full-width loading / aria-busy | Progress |
| P2 | Technical PageHeader description | **Done (C2)** short pickup/payment copy | — |

### Confirmation

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Success emotion weak vs Ombe success screens | **Done (C2)** success header; order # + total; Track + WhatsApp | Checkmark pop |
| **P0** | Payment card shows literal backticks around status in copy | **Done (C1)** plain “Pending Payment” | — |
| P1 | UPI not one-tap copy | **Done (C2)** Copy UPI / order # + WhatsApp primary | Copy toast |

### Orders list

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Active vs historical orders same weight | **Done (C4)** status tones + active sort; Ready/Pending callouts | Status color tokens |
| P2 | Refresh is text link only | **Partial (C4)** clearer Refresh label; pull later | Refresh spin |

### Order Detail / Tracking

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Timeline solid but dense; Ready for Pickup not celebratory enough | **Done (C4)** prep tracker after payment; Ready next-step callout | Current step emphasis |
| P1 | History list duplicates timeline | **Done (C4)** history collapsed behind toggle | Accordion |
| P2 | Status colors mostly badge text | **Done (C4)** shared `OrderStatusBadge` tones | — |

### About / Contact / FAQ / Terms / Privacy

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P2 | Plain `content-preline` wall of text | **Done (C5)** About hero; Contact actions; long-form measure | Accordion |
| P2 | FAQ content is newline blocks not Q/A UI | **Done (C5)** CMS FAQ → accessible accordion | Accordion |

### Loading / Empty / Error / Offline

| Priority | Issue | Recommended change | Reuse / animation |
| --- | --- | --- | --- |
| P1 | Skeleton not page-shaped | Match layout (hero/list/form) | Shimmer (respect reduced-motion) |
| P1 | Offline banner good; no retry CTA on failed mutations | Toast + Retry for cart/checkout errors | — |
| P2 | Empty states text-only | One illustration set | Soft enter |

## Navigation & chrome

- Bottom nav destinations match `pwa-scope` (Home / Menu / Cart / Orders / Account) — keep.
- Home top actions duplicate Cart/Account/Favourites → **declutter (P1)**.
- Bottom nav `position: sticky` works inside shell; Ombe uses fixed footer — prefer **fixed + safe-area (P1)** so content padding stays predictable with sticky CTAs.
- Sticky CTAs must reserve space above bottom nav — currently broken (**P0**).

## Catalog / product UX summary

- Discovery works with seeded catalog (18 products, filters, flags) but visual hierarchy is repetitive.
- Product cards are information-dense (category + badges + description + meta + price + add).
- Ombe pattern: tighter list cards, stronger image, less meta on card, detail page for choices.
- Multi-variant quick-add is a correctness/UX trap (wrong size) — route to detail (**P1**).

## Cart / checkout / orders UX summary

- Server-authoritative flows are correct; **customer-facing language and sticky CTAs** are the weak spots.
- Payment pending path is critical for Coffee (manual UPI) — confirmation/detail need copy, copy-UPI, WhatsApp prominence (**P0/P1**).
- Order tracking exists and is usable; elevate Ready / Pending Payment visually (**P1**).

## Animation opportunities (lightweight CSS)

Prefer transform/opacity; honor `prefers-reduced-motion`.

1. Page/section enter (fade + 8px rise)  
2. Product card press / favourite heart  
3. Add-to-cart → toast + cart badge bump  
4. Quantity stepper tick  
5. Filter chip select  
6. Sticky CTA appear  
7. Order timeline step complete  
8. Confirmation success check  
9. Skeleton shimmer  
10. Offline/online banner slide  

Avoid continuous decorative animation and heavy JS animation libraries.

## Accessibility & touch

| Item | Priority | Note |
| --- | --- | --- |
| Touch targets | P1 | Audit pills/icon chips; enforce ≥44px |
| Focus visible | P1 | Ensure all buttons/links/inputs |
| Live regions | P2 | Toasts + cart updates `aria-live` |
| Status by color only | P1 | Pair color with text/icon |
| Reduced motion | P2 | Global media query |

## Performance notes (UI-related)

- Route lazy-loading already in place — good.
- Home fires multiple catalog requests — acceptable; consider coalescing later (**P2**).
- Google Fonts CSS import in `theme.css` — consider self-host / preconnect (**P2**).
- Product images placeholders inflate perceived sameness — real media matters more than code (**P1**).

## Recommended implementation order

1. **Foundation:** tokens, button/card/form primitives, customer copy pass, toast system  
2. **Chrome:** StickyActionBar CSS + fixed bottom nav spacing; slim Home header  
3. **Ordering path:** **Done (C2)** Product Detail sticky CTA; Cart/Checkout sticky + copy; Confirmation payment UX  
4. **Catalog:** **Done (C3)** Product card densify/simplify; Menu sticky filters + results count; Home rails  
5. **Orders:** **Done (C4)** Status emphasis + timeline polish + Account cleanup  
6. **Auth/Content:** **Done (C5)** AuthCard + FAQ accordion + Contact/About/Terms/Privacy polish  
7. **Motion pass:** micro-interactions + reduced-motion  
8. **Quality:** **Done (C6)** responsive/PWA/offline/a11y QA + launch checklist (`docs/pwa-launch-checklist.md`)  

## Out of scope for redesign phase (reminders)

- No new business modules  
- No Admin/Barista Blade changes  
- Ombe delivery/wallet/chat/rewards pages are not Coffee targets  
- Backend remains source of truth for prices, cart, payment, orders  
