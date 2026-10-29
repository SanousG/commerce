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

No amount supplied by a client is used. Preparation resolves listing prices,
promotions, product tax categories, and the effective tenant tax rates again,
then stores exact `numeric(19,4)` snapshots. One UTC `tax_calculated_at` instant
is used for the whole operation; its date in the Store timezone selects rates.

Commercial prices are tax-inclusive. Tax is decomposed from each final line
total after promotions, at line level, and never added to the payable amount:

```text
subtotal_before_discounts - discount_total = subtotal
taxable_amount + exempt_amount + unaffected_amount + tax_total = subtotal
grand_total = subtotal + shipping_total
```

`tax_total` is therefore an included fiscal component. Shipping remains zero
and has no tax category in this phase. Tax validation runs before inventory
reservation. Missing, inactive, unrated, or ambiguous configuration rolls the
transaction back and leaves no lines or reservations.

Inventory reservations use `reference_type = CHECKOUT_LINE` and the checkout
line UUID as `reference_id`. Multiple reservations per line support split
allocation. Active tenant locations are allocated default-first, then by code
and UUID. Locks follow the existing inventory order: locations by UUID,
variants by UUID, and levels by `(location_id, variant_id)`.

Public pickup supplies an explicit active same-Tenant location, so allocation is
restricted to that location and never falls back to another warehouse. The
`READY` Checkout freezes its authorized address/instructions and the expected
`CASH + COD + NONE` terms. These are commercial snapshots, not a Payment;
shipping address fields remain null.

## Future Order Contract

Order creation must:

1. Lock a `READY` Checkout.
2. Reject an expired Checkout.
3. Copy Checkout and CheckoutLine commercial and tax snapshots into Order and
   OrderItem records without recalculation.
4. Mark the Cart `CONVERTED`.
5. Mark the Checkout `COMPLETED`.
6. Apply the reservation commit/release policy chosen by the future Order and
   Payment phases.

Checkout never commits `on_hand` or records collection state. Its pickup payment
fields describe the expected handoff only; Payments remains authoritative for
Order plans, Payment records, and captures.
