# Personalisation Architecture (P2)

First-party behaviour tracking and the planned personalisation pipeline.
Phase-1 launch software remains **FROZEN**; this is Phase-2 work on top of that baseline.

## Pipeline

```
Behaviour Events + Canonical Completed Orders
        ↓
Derived Personalisation Profile (P2.2)
        ↓
Reusable Audience Segment Definitions (P2.5)
        ↓
Recommendation / Campaign (P2.3 / P2.4)
        ↓
Impression → Click → Cart (attributed) → Checkout → Canonical Purchase
        ↓
Attribution Snapshot (order item) + Funnel Events
        ↓
Aggregate Performance Analytics (P2.6)
```

| Phase | Status | Scope |
| --- | --- | --- |
| **P2.1** Behaviour Tracking | **COMPLETE** | Raw append-only events, visitor identity, ingest API, PWA client, retention |
| **P2.2** Personalisation Profiles | **COMPLETE** | Derived customer/visitor profiles from events + completed orders |
| **P2.3** Recommendation Engine | **COMPLETE** | Hybrid strategy pipeline; guest/customer/cold-start; API + PWA rails |
| **P2.4** Campaign/Popup Engine | **COMPLETE** | Admin campaigns; targeting/frequency; eligible API; PWA popup |
| **P2.5** Segmentation | **COMPLETE** | Reusable named segments; shared rule evaluator; campaign segment operators |
| **P2.6** Attribution Analytics | **COMPLETE** | Cart→order attribution snapshots; conversion events; Admin rec/campaign performance |

## P2.1 collection

### Client interaction events (PWA → `POST /api/v1/behaviour/events`)

`product_viewed`, `category_viewed`, `search_performed`, `product_customized`, `cart_item_added`, `cart_item_removed`, `checkout_started`, `favourite_added`, `favourite_removed`, `recommendation_impression`, `recommendation_clicked`, `campaign_impression`, `campaign_clicked`, `campaign_dismissed`

### Server business events (Laravel only)

`order_completed` — recorded from `OrderStatusChanged` → `Completed` with idempotency key `server:order_completed:{order_id}`.
`recommendation_converted` / `campaign_converted` — server-authoritative purchase attribution (idempotent per order item). Clients cannot submit these types.

## Anonymous visitor identity

- Opaque random id in PWA `localStorage` (`coffee.visitor-id.v1`), TTL ~180 days (`coffee.behaviour.visitor_ttl_days`).
- No fingerprinting; IP/device are not identity.
- Authenticated ingest attaches `customer_id` when a customer session is present.
- On login/register, PWA calls `POST /api/v1/behaviour/merge`.
- **Ownership boundary:** `customer_visitor_identities` claims a `visitor_key` for at most one customer (first successful merge). Later customers on a shared device get `visitor_claimed` and the PWA rotates the visitor id. Merge of unclaimed events is idempotent (`customer_id IS NULL` only). Authenticated activity belonging to another customer is never rewritten.

## P2.2 derived profiles

Canonical model: `personalisation_profiles` — one row per **customer** *or* **visitor** (`customer_id` XOR `visitor_key`).

Stored signals (ranked JSON where flexible):

- category / product / flavour affinities
- preferred variants, add-on preferences
- recent product/category ids
- purchase frequency + repeat-purchase product ids
- spend band (`low` / `mid` / `high` with sufficiency flag)
- time-of-day preferences (`morning` / `afternoon` / `evening` / `night`)
- sample counts, `has_sufficient_evidence`, `calculated_at`, `profile_version`

### Scoring (deterministic V1)

Configured under `coffee.behaviour.profile` (not magic numbers in call sites):

| Evidence | Approximate weight |
| --- | --- |
| Canonical completed-order line | `purchase_item` (10) |
| Favourite added | 5 |
| Cart add | 3 |
| Customize | 2.5 |
| View / category view | 1 |
| Favourite / cart remove | small negative |

- **Purchases** come only from `orders.status = completed` (+ items/add-ons). Behaviour `order_completed` rows are **excluded** from scoring to avoid double counting.
- Cancelled/rejected orders are excluded.
- Repeat cap: diminishing `1/n` within `max_repeats_per_signal`.
- Recency: exponential decay with `recency_half_life_days` (café timezone via `coffee.timezone`).
- Cold start: empty affinities + `has_sufficient_evidence = false` when below `min_evidence_signals`. No invented preferences.

### Rebuild / processing

- `PersonalisationProfileService::rebuildForCustomer|Visitor` — idempotent full rebuild.
- `RebuildPersonalisationProfileJob` (unique, queued) after meaningful ingest / order completed / visitor merge.
- `coffee:personalisation-profiles-rebuild` (`--customer`, `--visitor`, `--stale`, `--reset-*`).
- Hourly stale rebuild scheduled. Failures never affect cart/checkout/payment UX.
- Internal read contract: `profilePayloadForCustomer|Visitor` for future engines (no raw event dump to PWA).

### Visitor → customer

After a valid P2.1 claim/merge: visitor profile row is deleted; customer profile rebuild uses attached events + orders. Shared-device `visitor_claimed` unchanged.

### Privacy / retention interaction

| Topic | Behaviour |
| --- | --- |
| Tracking disabled | No behaviour events loaded into rebuild; **canonical completed orders** still inform customer profiles |
| Raw event prune | Deletes events only; profiles remain until next rebuild (then reflect remaining lookback window + orders) |
| Profile reset | `--reset-customer` / `--reset-visitor` deletes derived rows only — never orders/finance/inventory |
| PWA | Does not expose full behavioural history or profile internals |

## Privacy & retention (events)

| Topic | Behaviour |
| --- | --- |
| Purpose | Personalisation foundation (recommendations, campaigns, analytics) — not payments/ops |
| Collected | Event type, visitor key, optional customer id, product/category/variant/order refs, short page context, allowlisted metadata |
| Search | Normalized, length-limited query text only |
| Not stored | Auth tokens, payment details, recipes/costs, secrets, arbitrary payloads, fingerprints |
| Disable | `COFFEE_BEHAVIOUR_TRACKING_ENABLED=false` |
| Retention | `coffee:behaviour-events-prune` (scheduled). Does **not** delete orders, payments, inventory, audit, or operational records |

## Performance

- Thin event insert; profile rebuild is async/unique-job.
- Rate limit: `throttle:behaviour-events`.
- PWA tracker fails silently; never blocks navigation/cart/checkout.

## Admin / ops

- Event diagnostics: `php artisan coffee:behaviour-events-prune --stats`
- Profile rebuild/reset: `php artisan coffee:personalisation-profiles-rebuild ...`
- No customer surveillance dashboard in P2.1/P2.2

## P2.3 hybrid recommendation engine

Canonical orchestrator: `RecommendationService` (strategy pipeline).

```
Candidate Strategies → Eligibility → Weighted Scoring → Dedup → Soft Diversity → Final list
```

### Strategies

| Key | Signal |
| --- | --- |
| `buy_again` | Distinct completed orders / qty / recency (excludes cancelled/rejected) |
| `favourite` | Existing `ProductFavourite` watchlist |
| `repeated_interest` | Multi-day / engagement behaviour (distinct days > same-session spam) |
| `affinity` | P2.2 `profilePayloadFor*` product affinities when evidence sufficient |
| `similar` | Category / flavour catalog adjacency |
| `frequently_bought_together` | Distinct-order co-occurrence with minimum evidence |
| `cart_context` | Cart complements (FBT when available, else category) |
| `trending` | Recent multi-actor behaviour + completed-order momentum |
| `popular` | Completed-order sales popularity (not `is_bestseller` alone) |
| `new_arrival` | Configurable creation window (+ optional `is_new` merchandising override) |
| `featured` / `bestseller` | Existing catalog merchandising flags |

Cold start (`has_sufficient_evidence = false`): trending / popular / featured / bestseller / new / similar / cart — no invented personal preferences. Warm profiles increase personalised strategy contribution via config weights + warm strategy list.

### Surfaces & API

- Contexts: `home`, `product_detail`, `menu`, `cart`, `post_order`
- `GET /api/v1/recommendations` — guest (`visitor_key`) or authenticated customer; customer-safe `ProductResource` + stable `reason` keys (no scores/profile internals)
- PWA rails: Home, Product detail, Cart (`RecommendationSection` + ProductCard / customization)
- Feedback: `recommendation_impression` / `recommendation_clicked` with allowlisted `request_id`, `reason`, `strategy`, `placement`, `context`; impressions deduped per request in the client

### Config / cache

`coffee.behaviour.recommendations.*` — limits, lookbacks, FBT/trending mins, strategy weights, cold/warm/context strategy maps. Global aggregates cached as JSON-safe arrays (`RecommendationAggregateStore`); never cache personalised result sets across users.

### Out of scope (later)

- ML / external recommendation services
- Automatic weight tuning from P2.6 metrics

## P2.4 campaign / popup engine

Canonical models: `campaigns`, `campaign_impressions` (frequency history).

Admin CRUD under Administrator → **Campaigns** (draft/active/paused/ended). Campaigns may reference a Promotion/Product/Category as CTA destination; they do **not** reimplement discount logic.

### Matching pipeline

```
Active + scheduled
    ↓
Placement match (global/home/menu/category/product/cart/checkout/order_success + optional ids/tags)
    ↓
Audience / context rules (ALL / ANY / exclude; allowlisted operators)
    ↓
Frequency eligibility (session / actor / day / cooldown / max impressions)
    ↓
Priority → specificity → schedule → id tie-break
    ↓
At most one popup
```

Rules reuse P2.2 `profilePayloadFor*` plus canonical completed-order / favourite reads. Location rules (`location_city` / `location_zone`) fail closed unless explicit `location_available` context is provided — no covert geolocation/IP identity.

### Surfaces & API

- Primary surface: customer PWA popup (`CampaignPopupController` + overlay scroll lock)
- Architecture-ready surfaces: `banner`, `inline`, `landing` (same targeting)
- `GET /api/v1/campaigns/eligible` — customer-safe render payload only (no rules/scores/profile)
- `POST /api/v1/campaigns/interactions` — optional explicit frequency write
- Behaviour ingest: `campaign_impression` / `campaign_clicked` / `campaign_dismissed` (mirrors frequency rows); server `campaign_converted` on attributed paid completion
- Impressions fire only when the modal is actually presented; client dedupes per `request_id`

### Config / cache

`coffee.behaviour.campaigns.*` — cache TTL for active campaign config by surface. Never cache personalized eligibility across actors.

### Out of scope (later)

- P2.6 impression→purchase attribution dashboard
- Aggressive exit-intent / dark-pattern triggers

## P2.5 reusable audience segments

Canonical model: `audience_segments` (name, description, status, actor_scope visitor/customer/both, dynamic `rules` JSON, `stable_key`).

Membership is **derived** from current authoritative data (profiles, completed orders, favourites, visitor behaviour counts). No permanent manually maintained customer-id lists as the primary architecture. No segment→segment nesting in V1.

### Shared targeting language

`TargetingRuleValidator` / `TargetingRuleEvaluator` — same safe ALL / ANY / EXCLUDE vocabulary for segments and campaigns. Campaigns add context operators (`current_product`, cart, fulfilment) plus:

- `segment_matches`
- `segment_not_matches`

Campaigns reference active segment ids; they do **not** copy segment rule JSON. Inactive/archived/missing segments fail closed at evaluation; Admin validation rejects non-active references on save.

### Evaluation

`SegmentService`: `matches(segment, actor)`, `matchingSegments(actor)` (read contract for future recommendations/landing), `matchesCached` (short identity-safe TTL; version-bumped on segment edits — cache is never canonical truth). Dynamic evaluation first; no warehouse/CDP.

Thresholds (order counts, lapse days, frequency, etc.) are configured per segment rule — no invented café-wide business defaults.

No `segment_matched` behaviour events (membership is derived state). Tracking-disabled semantics follow P2.1/P2.2 (behaviour omitted; completed-order signals remain). Location-dependent segment rules use the same explicit location context as P2.4 and fail closed when unavailable.

### Admin

Administrator → **Audience Segments**: list/filter, create/edit, activate/pause/archive, rule JSON + readable summary, explicit actor/count preview (capped scan — not on every form render).

### Config

`coffee.behaviour.segments.*` — match cache TTL, definition cache TTL, preview scan limit.

### Out of scope (later)

- Nested segments
- Materialized membership warehouse
- Automatic recommendation weight tuning / campaign auto-pause from P2.6 metrics
- Personalised landing surfaces using segment ids
- Multi-touch marketing attribution

## P2.6 attribution analytics

Closes the feedback loop: exposure → click → cart → checkout → canonical purchase → aggregate performance.

### Durable linkage (minimum)

- Optional `cart_items.attribution` JSON (validated server-side against behaviour evidence; does not affect pricing/`configuration_hash`)
- Snapshot `order_items.attribution` at order creation (stable history even if campaigns/weights change)
- `commerce_attribution_events` funnel rows: `cart_added` / `converted` (idempotent keys)
- Server behaviour events: `recommendation_converted` / `campaign_converted`

Guest attribution uses `visitor_key` + `request_id`; survives login via merge payload + visitor→customer evidence matching. No fingerprinting/IP identity.

### Rules (deterministic V1)

Priority: direct click evidence within configured window → optional view-through impression → unattributed. Metrics are **attributed/conversion correlation**, not causal proof. No multi-touch model.

Purchase conversion only when order is `Completed` and payment is canonically confirmed (retail: order payment confirmed; dining: parent session payment confirmed). Cancelled/rejected/unpaid excluded. Duplicate status transitions are idempotent.

### Admin

Administrator → Recommendation Performance / Campaign Performance (date presets, KPIs, strategy/campaign/placement tables). Aggregate only — no customer browsing timelines. Auth: `canViewFinancialReports()`.

### Retention boundary

Raw behaviour events may prune per `coffee.behaviour.retention_days`. Order item attribution snapshots and conversion funnel rows remain with business order records.

### Config

`coffee.behaviour.attribution.*` — view-through toggle/windows, analytics cache TTL.

### Explicit non-goals

- Do **not** auto-tune P2.3 weights or auto pause campaigns from these metrics
- No warehouse / Elasticsearch / external CDP
