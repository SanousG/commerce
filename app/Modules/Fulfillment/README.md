# Fulfillment Module

Phase 15 introduces tenant-scoped partial fulfillment and shipment state machines.

## Decisions

- A fulfillment belongs to one confirmed order and one inventory location.
- Every fulfillment item identifies one order item, one checkout inventory reservation, and the exact quantity allocated from it.
- Cancelled fulfillment allocations do not consume order or reservation allocation capacity and may be reused.
- Only `FULFILLED` fulfillment items contribute to the order fulfillment status.
- Reservation `quantity` remains the original quantity. `committed_quantity` tracks cumulative commits, and only the remaining quantity is released or expired.
- Completion locks the order, fulfillment, reservations by ID, and inventory levels in stable order in one transaction. It writes one inventory movement per fulfillment item using the fulfillment item as reference and fulfillment UUID as correlation ID.
- Payment eligibility is read-only. Missing payment plans are treated as `PREPAID`; COD requires successful captures at least equal to `amount_due_now`.
- Fulfillment and shipment histories are append-only in Eloquent and PostgreSQL.
- Shipments exist only for `SHIPPING` orders, are one-to-one with fulfillments, and cannot be marked shipped until the fulfillment is fulfilled.
- Operational records and their histories use restricted deletion. They are retained for auditability.

## Future Work

- Carrier adapters, labels, packages, rates, webhooks, and tracking event ingestion.
- Returns and reverse inventory movements.
- Split shipments within one fulfillment if that business requirement is introduced.
- Confirmed orders own their inventory reservations, so checkout expiry is cleared during order creation.
- Order completion remains separate: fulfillment never marks an order `COMPLETED` or changes payment state.
- Pickup completes through fulfillment without a shipment; pickup-specific ready/picked-up states are deferred.

## Admin reads

- `GET /admin/fulfillments` is store- and tenant-scoped and accepts validated read-only filters `search` (linked order number), `status`, and `order_id`. `order_id` supports cross-module navigation from an order, is accepted only as a UUID, and never widens the scope.
- There is no standalone shipment page. Because the Fulfillment index and detail include shipment state and tracking data, both routes require the explicit combination `fulfillments.view` + `shipments.view`. Order detail remains available with `orders.view`; its fulfillment projection requires `fulfillments.view` and omits shipment state and the mixed Fulfillment link without `shipments.view`.

## Notifications

- A successful completion emits one store-scoped `FULFILLMENT_COMPLETED` event keyed by fulfillment UUID for recipients with `fulfillments.view`.
- Marking a shipment ready emits one `SHIPMENT_READY` event keyed by shipment UUID. Recipient resolution and delivery-time revocation use `shipments.view`; the current fixed system-role matrix grants it together with `fulfillments.view`, which is also required by the linked mixed-resource page.
- These rows are written inside their owning transaction; optional email is queued only after commit.
