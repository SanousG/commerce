# Orders

Orders are permanent commercial snapshots copied atomically from a non-expired `READY` Checkout. Creation never invokes pricing, promotion, cart calculation, customer-address resolution, or current catalog reads.

Inventory reservations retain their `CHECKOUT_LINE` reference. `OrderItem.checkout_line_id` provides the stable path from an Order item to those reservations without mutating or duplicating them. Order creation leaves reservations `ACTIVE` and does not change `on_hand`; cancellation releases only active reservations belonging to the Order's checkout lines.

Payment and fulfillment statuses are independent from commercial Order status. Future Payments owns payment-status transitions and must support multiple and partial payments. Future Fulfillment owns fulfillment-status transitions and reservation commit. `COMPLETED` is reserved until those domains define their coordination rules.

Cancellation currently supports only `CONFIRMED` plus `UNFULFILLED`. Payments must later add paid/refund checks. Once Fulfillment commits stock, cancellation requires a future return/restock workflow rather than reservation release.
