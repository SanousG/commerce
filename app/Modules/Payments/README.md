# Payments

Payments owns collection state and the append-only operational ledger. Orders remain the commercial agreement; a paid order is not fulfilled.

## Decisions

- `PaymentMethod` is the channel (`ONLINE`, `BANK_TRANSFER`, `CASH`). Provider is separate and currently controlled by `PaymentProvider`; adding a provider requires an explicit enum/schema migration so unvalidated plugin identifiers cannot enter the ledger.
- `COD` is a payment term, not a method. `OrderPaymentPlan` snapshots `PREPAID` or `COD`, deposit configuration, and amounts. Existing orders have a virtual `PREPAID` default until terms are explicitly configured.
- Public pickup requires expected method `CASH`, terms `COD`, and deposit `NONE/0.0000`. Preparing Checkout creates no Payment or capture. `OrderPaymentPlan.expected_method` is nullable for historical compatibility; SF-04.3 will copy the frozen expectation into the public Order plan.
- Manual captures equal the full `Payment.amount`. Partial order payment uses multiple Payments; partial capture inside one Payment is deferred.
- Captured and remaining totals derive only from successful `CAPTURE` transactions. `Payment.status` is an operational projection, not the financial source of truth.
- Locks are acquired in this order: Order, Payment, then transaction lookup/insert. This serializes competing captures and prevents overpayment.
- Manual payment creation and capture each require scoped idempotency keys. Reusing a key with a different payload is rejected.
- Successful transactions are append-only. Corrections and refunds must add compensating records in the future, never edit captures.

## Future Webhooks

Provider webhooks must verify signatures, reject/replay safely by provider event ID, locate the local intent, acquire idempotency locks, and compare amount/currency against local records. They must not trust tenant IDs or financial values from payloads. No webhook endpoint exists until a real provider is integrated.

## Deferred

Refunds will add compensation records and update `PARTIALLY_REFUNDED`/`REFUNDED`. COD balances will be captured at delivery or delivery confirmation, without equating payment with fulfillment.

## Admin reads

- `GET /admin/payments` and `GET /admin/refunds` are store- and tenant-scoped. They accept validated read-only filters `search` (linked order number), `status`, and `order_id`. `order_id` exists to support cross-module navigation from an order; it is accepted only as a UUID and cannot widen the scope, so another store's or tenant's order simply yields an empty page. Invalid or unknown values redirect back with validation errors.
- `MEMBER` lacks `payments.view` and `refunds.view`, so both read surfaces return `403` for members while `OWNER`/`ADMIN` retain access. Mutation remains gated by `payments.manage`/`payments.capture`/`refunds.manage`.

## Notifications

- A successful capture emits `PAYMENT_CAPTURED` with the successful transaction UUID as the transition identity; recipients require `payments.view`.
- Refund creation maintains the actionable `REFUND_PENDING` condition. Successful completion resolves that condition and emits `REFUND_COMPLETED` keyed by the successful refund transaction UUID; recipients require `refunds.view`.
- Notification text does not include amount, currency, external references, or customer data. Authorization and preferences are re-evaluated before queued email delivery.
