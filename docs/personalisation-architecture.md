# Personalisation Architecture (P2)

First-party behaviour tracking and the planned personalisation pipeline.
Phase-1 launch software remains **FROZEN**; this is Phase-2 work on top of that baseline.

## Pipeline

```
Behaviour Events (P2.1)
  → Derived Customer/Visitor Profile (P2.2)
  → Segmentation / Context (P2.5)
  → Recommendation Engine (P2.3) + Campaign/Popup Engine (P2.4)
  → Impression / Click / Conversion events
  → Analytics (P2.6)
```

| Phase | Status | Scope |
| --- | --- | --- |
| **P2.1** Behaviour Tracking | **Implemented** | Raw append-only events, visitor identity, ingest API, PWA client, retention |
| **P2.2** Personalisation Profile | Next | Derive affinities (category/product/flavour/variant/add-on), spend band, time-of-day, frequency — from events, not speculative columns |
| **P2.3** Recommendation Engine | Planned | Rank / suggest products using profiles + catalog |
| **P2.4** Campaign/Popup Engine | Planned | Targeted landing/popups using segments + context |
| **P2.5** Segmentation/Targeting | Planned | Visitor/customer segments for campaigns |
| **P2.6** Analytics | Planned | Recommendation/campaign impression→conversion reporting |

## P2.1 collection

### Client interaction events (PWA → `POST /api/v1/behaviour/events`)

`product_viewed`, `category_viewed`, `search_performed`, `product_customized`, `cart_item_added`, `cart_item_removed`, `checkout_started`, `favourite_added`, `favourite_removed`

### Server business events (Laravel only)

`order_completed` — recorded from `OrderStatusChanged` → `Completed` with idempotency key `server:order_completed:{order_id}`. Clients cannot submit this type.

### Reserved (reject until later phases)

`recommendation_*`, `campaign_*`

## Anonymous visitor identity

- Opaque random id in PWA `localStorage` (`coffee.visitor-id.v1`), TTL ~180 days (`coffee.behaviour.visitor_ttl_days`).
- No fingerprinting; IP/device are not identity.
- Authenticated ingest attaches `customer_id` when a customer session is present.
- On login/register, PWA calls `POST /api/v1/behaviour/merge`.
- **Ownership boundary:** `customer_visitor_identities` claims a `visitor_key` for at most one customer (first successful merge). Later customers on a shared device get `visitor_claimed` and the PWA rotates the visitor id. Merge of unclaimed events is idempotent (`customer_id IS NULL` only). Authenticated activity belonging to another customer is never rewritten.

## Privacy & retention

| Topic | Behaviour |
| --- | --- |
| Purpose | Personalisation foundation (recommendations, campaigns, analytics) — not payments/ops |
| Collected | Event type, visitor key, optional customer id, product/category/variant/order refs, short page context, allowlisted metadata |
| Search | Normalized, length-limited query text only (`coffee.behaviour.search_query_max_length`) |
| Not stored | Auth tokens, payment details, recipes/costs, secrets, arbitrary payloads, fingerprints |
| Disable | `COFFEE_BEHAVIOUR_TRACKING_ENABLED=false` — API no-ops; content exposes `behaviour.tracking_enabled`; journey unaffected |
| Retention | Raw events pruned by `coffee:behaviour-events-prune` (scheduled daily). Default 180 days. Does **not** delete orders, payments, inventory, audit, or operational records |
| Consent | No cookie-consent platform in P2.1. Future consent can gate the same `enabled` config / content flag |

## Performance

- Thin synchronous insert; public contract stable if processing later moves to queues.
- Rate limit: `throttle:behaviour-events`.
- PWA tracker fails silently; never blocks navigation/cart/checkout.

## Admin

No surveillance dashboard. Diagnostics: `php artisan coffee:behaviour-events-prune --stats`.
