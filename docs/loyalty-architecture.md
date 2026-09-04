# Loyalty architecture (P3.1–P3.5)

Phase-1 and Phase-2 remain **DEVELOPMENT COMPLETE / FROZEN**.

## Status

- **P3.1** Loyalty & Rewards Foundation — **COMPLETE**
- **P3.2** Redemption & Reward Rules — **COMPLETE**
- **P3.3** Customer Loyalty Experience — **COMPLETE**
- **P3.4** Admin & Operational Reporting — **COMPLETE**
- **P3.5** Loyalty Intelligence Integration — **COMPLETE**

**Phase 3 Loyalty & Rewards — DEVELOPMENT COMPLETE / FROZEN**

Out of scope through P3.5: wallet/store credit, tiers, subscriptions, gamification, auto-expiry, AI/auto-tuning, payment gateway, invented production earn rates, automatic historical backfill, monetary valuation of outstanding points, opaque loyalty score weighting, multi-touch attribution. P3.3–P3.5 do **not** change earning/redemption ledger economics.

## Flow

```
Earn
 ↓
Immutable Ledger
 ↓
Available Balance
 ↓
Reward Eligibility
 ↓
Checkout Reward Selection
 ↓
Canonical Order Snapshot
 ↓
Redeem Ledger Entry
 ↓
Cancel/Correction
 ↓
Compensating Restore/Reversal
```

## Domain

### `loyalty_accounts`

One row per authenticated customer (`customer_id` unique).

| Field | Semantics |
| --- | --- |
| `available_points` | Running balance (**may be negative** = loyalty debt) |
| `lifetime_earned_points` | Sum of earn credits (not reduced by earn reversals) |
| `lifetime_redeemed_points` | Sum of redeem debits (not reduced by restores) |
| `lifetime_adjusted_points` | Net admin adjustments |
| `version` | Concurrency hint |

### `loyalty_point_transactions` (immutable ledger)

Authoritative history. Never edit/delete rows to “fix” balance.

| Type | Points sign | Notes |
| --- | --- | --- |
| `earn` | + | Completed paid order |
| `redeem` | − | Order loyalty reward |
| `reversal` | − (earn) / + (restore) | Compensating entry |
| `adjustment` | ± | Admin-only, mandatory reason |
| `expiry` | − | Future |

Idempotency keys:

- `earn:order:{id}`
- `reversal:order:{id}`
- `redeem:order:{id}:reward:{reward_id}`
- `restore:order:{id}:reward:{reward_id}`

### `loyalty_rewards`

Configurable catalog (separate from Promotions):

Types: `fixed_order_discount`, `percentage_order_discount`, `free_base_product`, `free_add_on`, `specific_product_reward`, `category_product_reward`.

Status: `active` / `paused` / `archived` (soft delete).

Limits: global, per-customer, optional per-customer period days. Schedule via `starts_at` / `ends_at`. Min spend optional.

### Order snapshot columns

Historical orders keep reward name/type/points/discount/description/config JSON. Live reward edits do not change past invoices.

## Earning (V1)

Config: `config/loyalty.php` (`COFFEE_LOYALTY_*`). Production default: **`enabled=false`**.

Eligible amount: merchandise after discounts (prefer `taxable_amount`), excluding delivery fee & tax.

Points: `floor(eligible / currency_unit) × points_per_currency_unit`.

Guests earn nothing. Effective boundary: `loyalty.effective_at`.

## Redemption (V1)

- Selection on cart (`loyalty_reward_id`) is **not** a spend.
- Final redeem ledger write happens atomically inside successful `OrderService::store`.
- Abandoned/failed checkout does not spend points.
- Unpaid cancel/reject restores via compensating ledger entry (idempotent).
- One loyalty reward per order.
- Server recalculates value; client submits only reward id.

### Stacking

```
catalog prices
→ promotions + referral coupon (`discount_total`)
→ loyalty reward (`loyalty_discount_amount`)
→ tax
```

Referral free-drink benefit remains outside GST basis (unchanged). Config `loyalty.redemption.allow_with_promotions` can disallow promo+loyalty.

Loyalty discount cannot reduce merchandise below zero.

## Debt invariant

Ledger arithmetic is **never silently clamped**.

When an earn is reversed after points were spent:

1. `available_points` may go **negative** (loyalty debt).
2. Customer sees “Points adjustment pending”.
3. Further redemption is blocked while `available_points < points_cost`.
4. Future earnings reduce the debt naturally.
5. Debt is **never** money owed by the customer.

## Integration

- Earn: `OrderStatusChanged→Completed` / `DiningPaymentConfirmed` → unique job.
- Redeem: same DB transaction as order create.
- Restore: unpaid `Cancelled` / `Rejected` transitions.
- Order/payment success is not rolled back by async earn failures; redeem failures abort order create (atomic).

## Referral / promotion separation

- Referral rewards unchanged (`customer_rewards`). Optional bridge `loyalty.referral_bridge.enabled` default **off**.
- Promotions continue to price orders; loyalty consumes post-promotion merchandise.

## Surfaces

- Customer API: `GET /account/loyalty` (hub + progress + discovery), `GET /account/loyalty/rewards`, cart `POST/DELETE /cart/loyalty-reward`
- Order API: `loyalty_feedback` (earned only when ledger exists; `earning_pending` when async award not yet written)
- PWA: Loyalty rewards hub, progress, reward cards, cart/checkout clarity, debt messaging, order feedback
- Admin: Loyalty Operations dashboard/ledger/adjustments/CSV exports; Loyalty Rewards CRUD + bulk pause/activate + duplicate; user show balance/ledger/debt + confirmed idempotent adjustment
- Invoices: separate loyalty discount line (not cash/payment)
- Behaviour (allowlisted): `loyalty_reward_viewed`, `loyalty_reward_selected` (client); `loyalty_reward_redeemed` reserved server-side

### Customer experience payload (P3.3)

- `display_available_points` — never shows debt as money owed (`max(0, available)`)
- `next_reward` — server progress toward nearest reachable reward (deterministic by points_cost, id)
- `available_now` / `locked` / `recently_redeemed` — discovery groups
- Reward cards: state, `unavailable_message`, `benefit_label`, optional `image_url`
- `personalisation_summary` — customer-safe loyalty intelligence signals (wired in P3.5)
- Activity: customer labels only; may include `order_number`; no metadata/idempotency/source internals

Hub discovery uses a high merchandise basis when the cart is empty so points-based discount rewards can appear before checkout; checkout still recalculates against the real cart.

### Admin operations reporting (P3.4)

Owner/Manager only (`canManageWebsiteSettings`). Operator/Barista/Chef/Waiter denied.

Aggregate metrics (cafe timezone date presets) via `LoyaltyReportingService`:

| Metric | Definition |
| --- | --- |
| `earned_points` | Sum of positive canonical earn transactions in range (not reduced by later reversals) |
| `redeemed_points` | Absolute sum of canonical redeem transactions in range |
| `restored_points` | Sum of redemption restore transactions in range |
| `reversed_earn_points` | Absolute sum of earn-reversal transactions in range |
| `adjustment_positive` / `negative` / `net` | Adjustment ledger in range, presented separately from earn/redeem |
| `outstanding_points` | Sum of `max(available_points, 0)` across accounts (points, **not** cash liability) |
| `debt_points` | Absolute sum of `min(available_points, 0)` |
| `redemption_rate` | Redemptions ÷ qualifying earn orders in range; zero denominator ⇒ `—` |

Reward performance merges behaviour views/selections with canonical redeem ledger + order discount attribution. Missing events are not inferred. Server redemption is truth.

Adjustments: mandatory reason, confirmation, idempotency key, actor metadata, DB transaction + row lock, no edit/delete (compensating adjustment only). Negative adjustments may create debt per P3.2.

Reward ops: activate/pause/archive/duplicate; bulk pause/activate with per-row validation and partial failure reporting. Prefer archive; historical order snapshots never mutated.

CSV exports (same `streamDownload` pattern as financial reports): ledger, balances, redemptions — no raw idempotency keys.

### Loyalty intelligence (P3.5)

```
Loyalty Ledger / Rewards
        ↓
Loyalty Summary Context  (LoyaltyPersonalisationContextService)
        ↓
Profiles / Segments / Targeting
        ↓
Campaigns + Merchandising
        ↓
Customer Experience
```

**Boundary: intelligence may READ loyalty; intelligence must NOT mutate loyalty economics.**

Request-scoped `loyalty` actor context (via `SegmentService::buildContext`) exposes safe derived signals only:

- `loyalty_enabled`, `has_loyalty_account`, `available_points` (display), `points_band`
- `has_affordable_reward`, `affordable_reward_count`, nearest reward + `near_reward`
- `recent_earner` / `recent_redeemer` from **canonical ledger** lookbacks (not `loyalty_reward_selected`)
- `loyalty_debt`, `redemption_blocked`
- `eligible_product_ids` / `eligible_category_ids` for explicit recommendation rails

Points bands and lookbacks are centralized in `config/loyalty.php` → `intelligence.*`.

Targeting rule types (shared evaluator): `loyalty_enabled`, `loyalty_points_gte` / `_lte`, `loyalty_points_band`, `loyalty_reward_available` / `_not_available`, `loyalty_near_reward`, `loyalty_recent_redeemer` / `_earner`, `loyalty_debt`, `loyalty_redemption_blocked`.

Anonymous visitors: empty loyalty context (no pseudo-account). Tracking disabled: ledger-based signals still work. Context failure: loyalty rules fail closed; generic campaigns/sections/recommendations remain.

Recommendations: optional explicit strategy `loyalty_reward_eligible` only — never default warm/cold weighting. No opaque loyalty score. Campaign→loyalty conversion attribution deferred (no inferred multi-touch).
