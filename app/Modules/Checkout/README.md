# Checkout

Checkout is a temporary, validated commercial snapshot. A cart remains dynamic
intent; a future order will be the contractual snapshot.

## Lifecycle

- `PENDING`: editable contact and address data; no inventory is reserved.
- `READY`: lines and totals are frozen and inventory is reserved until expiry.
- `CANCELLED`: active reservations were released explicitly.
- `EXPIRED`: due reservations were expired and stock became available again.
- `COMPLETED`: reserved for the future Order handoff. It does not commit stock.
- `FAILED`: available for a future persisted failure policy; preparation failures
  currently roll back and leave the session `PENDING`.

Shipping and taxes are deliberately zero in this phase. No amount supplied by a
client is used. Preparation resolves listing prices and promotions again and
stores exact `numeric(19,4)` snapshots.

Inventory reservations use `reference_type = CHECKOUT_LINE` and the checkout
line UUID as `reference_id`. Multiple reservations per line support split
allocation. Active tenant locations are allocated default-first, then by code
and UUID. Locks follow the existing inventory order: locations by UUID,
variants by UUID, and levels by `(location_id, variant_id)`.

## Future Order Contract

Order creation must:

1. Lock a `READY` Checkout.
2. Reject an expired Checkout.
3. Copy Checkout and CheckoutLine snapshots into Order and OrderItem records.
4. Mark the Cart `CONVERTED`.
5. Mark the Checkout `COMPLETED`.
6. Apply the reservation commit/release policy chosen by the future Order and
   Payment phases.

Checkout does not choose payment timing and never commits `on_hand`. The future
Payment phase may support online payment, transfer, cash, COD, or deposits
without adding payment state to Checkout.
