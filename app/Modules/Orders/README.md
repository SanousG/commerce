# Orders

Orders are permanent commercial snapshots copied atomically from a non-expired `READY` Checkout. Creation never invokes pricing, promotion, tax calculation, cart calculation, customer-address resolution, or current catalog reads.

Tax snapshot version 1 includes category, rate, taxable/exempt/unaffected
buckets and tax per item, plus matching Order aggregates and
`tax_calculated_at`. `tax_total` is included within `subtotal`; it is not added
to `grand_total`. Changing Product, Pricing, TaxCategory, or TaxRate after
Checkout preparation cannot change an Order.

Inventory reservations retain their `CHECKOUT_LINE` reference. `OrderItem.checkout_line_id` provides the stable path from an Order item to those reservations without mutating or duplicating them. Order creation leaves reservations `ACTIVE` and does not change `on_hand`; cancellation releases only active reservations belonging to the Order's checkout lines.

Payment and fulfillment statuses are independent from commercial Order status. Future Payments owns payment-status transitions and must support multiple and partial payments. Future Fulfillment owns fulfillment-status transitions and reservation commit. `COMPLETED` is reserved until those domains define their coordination rules.

Cancellation currently supports only `CONFIRMED` plus `UNFULFILLED`. Payments must later add paid/refund checks. Once Fulfillment commits stock, cancellation requires a future return/restock workflow rather than reservation release.

## Admin reads

- `GET /admin/orders` is store-scoped through `StoreContext` and tenant-scoped through `TenantContext`. The store is not a filter input: the list always reflects the store resolved for the session.
- Validated read-only filters are `search` (order number, customer name, email), `status`, `payment_status`, `fulfillment_status`, `customer_id`, and the `from`/`to` date bounds on `placed_at`. `customer_id` is a browser-supplied foreign identifier, accepted only as a well-formed UUID; it narrows the already scoped query and can never widen it. Invalid or unknown values redirect back with validation errors.
- `GET /admin/orders/{order}` exposes a `relatedLinks` block (customer, payments, refunds, returns, fulfillments) that is populated only for resources the acting user may view and that actually exist. Cross-module links carry an `order_id` that the target index re-validates inside its own tenant/store scope. `returns` is surfaced only with `returns.view`. Order commercial snapshots remain immutable and are never recalculated from current prices.

## Notifications

Successful order creation writes one store-scoped `ORDER_PLACED` admin notification inside the order transaction. Its durable event key uses the order UUID, recipients require `orders.view`, and content intentionally omits customer and monetary data. Optional email is scheduled only after commit by the Notifications module.
