# Payment architecture

Phase-1/2/3 software baselines remain **FROZEN**. Online gateways are a corrective/additive payment capability on top of the existing order domain.

## Method catalog

```
                  Payment Methods
                       |
        +--------------+--------------+
        |                             |
      Online                         Manual
        |                             |
 Razorpay/PayU/Paytm/PhonePe     Cash/UPI Screenshot
        |                             |
        +--------------+--------------+
                       ↓
                PaymentService
                       ↓
             Canonical Payment State
                       ↓
                  OrderService
```

Codes: `razorpay`, `payu`, `paytm`, `phonepe`, `cash`, `manual_upi`.

## Availability invariant

```
ENABLED != AVAILABLE

AVAILABLE =
  enabled
  + configured
  + fulfilment eligible
  + valid order state (for initiation)
```

- Enabled but incomplete credentials → **not** exposed to customers; Admin shows incomplete.
- No silent fallback to Cash/Manual UPI.
- Secrets never appear in customer APIs.

## Authority invariant

```
BROWSER SUCCESS != PAID
SERVER VERIFIED PAYMENT = PAID
```

Checkout/order creation may select a method. Online confirmation requires:

1. Server-created payment attempt (amount from order)
2. Provider signature/webhook verification
3. Amount/currency match
4. Idempotent `OrderService::confirmGatewayPayment`

Return/callback URLs only drive UX (“Verifying payment…” → refetch order).

## Manual methods

- **Cash** — fulfilment rules centralized in `PaymentMethodCatalog` / `PaymentEligibilityService` (delivery cash blocked; takeaway requires `cash_takeaway_allowed`; dine-in allowed when enabled).
- **Manual UPI** — QR/UPI instructions + screenshot; rejected when method disabled; staff confirmation unchanged.

## Online flow

```
Pending Payment order
  → POST /api/v1/orders/{order}/payment/initiate
  → PaymentAttempt (requires_action)
  → provider UI
  → verify-return and/or /api/webhooks/{provider}
  → PaymentAttempt confirmed
  → Order payment_confirmed
```

Failed attempts leave the order payable within the pending-payment expiry window. Retries do not create a new order.

## Configuration

- Enable flags: website settings `payment_*_enabled` (defaults from `config/coffee.php` / env).
- Gateway secrets: env (`RAZORPAY_*`, `PAYU_*`, `PAYTM_*`, `PHONEPE_*`) — never returned to browsers.
- Public client fields only (e.g. Razorpay `key_id`) may reach the PWA.
- Administrator → Website Settings shows **Payment method readiness** (Ready / Incomplete / Disabled) plus per-method enable toggles. Secrets are never rendered back.

## Race with expiry

Payment confirmation under lock wins over cancellation when payment is verified first. Provider payment after cancellation is recorded and flagged for Admin reconciliation without silently reopening the order.

## Launch readiness

`coffee:launch-readiness` treats Manual UPI credentials as blockers only when Manual UPI is enabled, flags enabled-but-incomplete gateways, and warns when online methods are test/sandbox mode in production.
