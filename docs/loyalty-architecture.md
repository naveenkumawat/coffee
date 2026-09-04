# Loyalty architecture (P3.1–P3.3)

Phase-1 and Phase-2 remain **DEVELOPMENT COMPLETE / FROZEN**.

## Status

- **P3.1** Loyalty & Rewards Foundation — **COMPLETE**
- **P3.2** Redemption & Reward Rules — **COMPLETE**
- **P3.3** Customer Loyalty Experience — **COMPLETE**
- **P3.4** Admin/Operations Loyalty Controls — **NEXT**

Out of scope through P3.3: wallet/store credit, tiers, subscriptions, gamification, auto-expiry, AI, payment gateway, invented production earn rates, automatic historical backfill. P3.3 does **not** change earning/redemption economics.

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
- Admin: Loyalty Rewards CRUD; user show balance/ledger + manual adjustment
- Invoices: separate loyalty discount line (not cash/payment)
- Behaviour (allowlisted): `loyalty_reward_viewed`, `loyalty_reward_selected` (client); `loyalty_reward_redeemed` reserved server-side

### Customer experience payload (P3.3)

- `display_available_points` — never shows debt as money owed (`max(0, available)`)
- `next_reward` — server progress toward nearest reachable reward (deterministic by points_cost, id)
- `available_now` / `locked` / `recently_redeemed` — discovery groups
- Reward cards: state, `unavailable_message`, `benefit_label`, optional `image_url`
- `personalisation_summary` — safe fields for future P3.5 (no segment wiring yet)
- Activity: customer labels only; may include `order_number`; no metadata/idempotency/source internals

Hub discovery uses a high merchandise basis when the cart is empty so points-based discount rewards can appear before checkout; checkout still recalculates against the real cart.

## Future

- **P3.4** Admin/operations controls
- **P3.5** Intelligence / segment integration
