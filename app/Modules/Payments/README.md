# Payments

Payments owns collection state and the append-only operational ledger. Orders remain the commercial agreement; a paid order is not fulfilled.

## Decisions

- `PaymentMethod` is the channel (`ONLINE`, `BANK_TRANSFER`, `CASH`). Provider is separate and currently controlled by `PaymentProvider`; adding a provider requires an explicit enum/schema migration so unvalidated plugin identifiers cannot enter the ledger.
- `COD` is a payment term, not a method. `OrderPaymentPlan` snapshots `PREPAID` or `COD`, deposit configuration, and amounts. Existing orders have a virtual `PREPAID` default until terms are explicitly configured.
- Manual captures equal the full `Payment.amount`. Partial order payment uses multiple Payments; partial capture inside one Payment is deferred.
- Captured and remaining totals derive only from successful `CAPTURE` transactions. `Payment.status` is an operational projection, not the financial source of truth.
- Locks are acquired in this order: Order, Payment, then transaction lookup/insert. This serializes competing captures and prevents overpayment.
- Manual payment creation and capture each require scoped idempotency keys. Reusing a key with a different payload is rejected.
- Successful transactions are append-only. Corrections and refunds must add compensating records in the future, never edit captures.

## Future Webhooks

Provider webhooks must verify signatures, reject/replay safely by provider event ID, locate the local intent, acquire idempotency locks, and compare amount/currency against local records. They must not trust tenant IDs or financial values from payloads. No webhook endpoint exists until a real provider is integrated.

## Deferred

Refunds will add compensation records and update `PARTIALLY_REFUNDED`/`REFUNDED`. COD balances will be captured at delivery or delivery confirmation, without equating payment with fulfillment.
