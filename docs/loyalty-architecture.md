# Loyalty architecture (P3.1)

Phase-1 and Phase-2 remain **DEVELOPMENT COMPLETE / FROZEN**.

## Status

**P3.1 Loyalty & Rewards Foundation — COMPLETE**

Out of scope for P3.1: redemption, expiry automation, tiers, wallet/store credit, subscriptions, gamification, payment gateway, invented production earn rates, and automatic historical backfill.

## Flow

```
Canonical Paid Completed Order
          ↓
   Loyalty Eligibility
          ↓
     Earning Rule
          ↓
 Immutable Points Ledger
          ↓
    Cached Account Balance
          ↓
 Customer/Admin Read Models
```

## Domain

### `loyalty_accounts`

One row per authenticated customer (`customer_id` unique).

Cached counters (must stay transactionally consistent with ledger writes):

- `available_points`
- `lifetime_earned_points`
- `lifetime_redeemed_points` (always 0 until P3.2 redemption)
- `version` (optimistic concurrency hint)

### `loyalty_point_transactions` (immutable ledger)

Authoritative history. Never edit/delete earn rows to “fix” balance.

| Field | Notes |
| --- | --- |
| `type` | `earn`, `redeem` (future), `reversal`, `adjustment` (future), `expiry` (future) |
| `points` | Signed integer (`earn` positive, `reversal` negative) |
| `source_type` / `source_id` | e.g. `order` + order id |
| `idempotency_key` | Unique durable key |
| `reason_code`, `description` | Customer/admin safe labels |
| `metadata` | Minimal internal snapshot (eligible amount, order number) — not exposed on customer API |
| `occurred_at` | Business time |

## Earning (V1)

Config: `config/loyalty.php` (`COFFEE_LOYALTY_*` env).

Production default: **`enabled=false`** until the café chooses real economics.

Eligible amount policy (`merchandise_after_discount_ex_tax_ex_delivery`):

1. Prefer canonical `orders.taxable_amount` (merchandise after promotions/referral coupon).
2. Else `max(0, subtotal − discount_total)`.
3. **Exclude** `delivery_fee_amount` and `tax_amount` (pre-tax earning).

Points:

`floor(eligible_amount / currency_unit) × points_per_currency_unit`

Optional `minimum_eligible_amount`. Deterministic floor rounding only.

### Order eligibility

- Authenticated `customer_id` present (guest orders earn nothing)
- Order `status = completed`
- Payment confirmed:
  - Retail: `orders.payment_status = confirmed`
  - Dining: parent `dining_sessions.payment_status = confirmed`
- `completed_at` (or fallback timestamp) on/after `loyalty.effective_at` when set

Takeaway, Delivery, and Dining (paid session semantics) are supported.

## Integration & consistency

Listeners (after commit; do **not** fail order/payment):

- `OrderStatusChanged` → `Completed` → `AwardLoyaltyPointsForOrderJob`
- `DiningPaymentConfirmed` → dispatch job per session order

Jobs are unique per order id, retryable, and call idempotent `LoyaltyService::awardForOrder()`.

**Boundary:** order/payment success is independent of loyalty. Loyalty failures are logged/retryable; ledger uniqueness prevents double awards.

## Idempotency

- Earn key: `earn:order:{order_id}`
- Reversal key: `reversal:order:{order_id}`

Row locks on account + unique key handle concurrency/retries.

## Reversal

`LoyaltyService::reverseOrderAward()` creates a compensating `reversal` row. Original earn remains immutable.

Phase-1 cannot cancel a completed order, so reversal is service-ready and covered by direct tests (no new cancel/refund workflow).

### Negative balance invariant (for P3.2)

Ledger arithmetic is **never silently clamped**.

P3.1: a reversal that would drive `available_points` below zero is **rejected** (`would_go_negative`). With no redemption this should not occur in normal earn→reverse flows.

P3.2 must define redemption/reversal debt rules explicitly if redemptions can leave insufficient available points for later reversals.

## Referral / promotion separation

- Referral rewards remain `ReferralService` / `customer_rewards` — unchanged.
- Promotions continue to price orders; loyalty only consumes the final eligible snapshot.
- Future optional: P3.2 may emit loyalty ledger rows from approved referral outcomes without rewriting referral economics.

## APIs & surfaces

- Customer: `GET /api/v1/account/loyalty` (+ alias `GET /api/v1/customer/loyalty`)
- PWA: Account → Loyalty points (`/account/loyalty`) — balance, lifetime earned, recent activity, safe explanation; **no redemption UI**
- Admin: User show (customers) — balance + transaction history (Actions/authorization unchanged)

No client-submitted balances. No guest loyalty endpoint.

## Historical orders

No automatic backfill. Use `loyalty.effective_at` (and `enabled`) as the activation boundary. Explicit backfill command is future work if needed.

## Future

- **P3.2** Redemption & reward rules (+ optional admin adjustment; referral→loyalty bridge)
- **P3.3** Loyalty customer UX polish
- **P3.4** Admin/operations controls
- **P3.5** Intelligence integration
