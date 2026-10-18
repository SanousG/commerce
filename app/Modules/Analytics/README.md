# Analytics / Admin Dashboard

Phase 22 implements a read-only operational projection for the active merchant
Tenant and Store. Analytics depends on transactional modules for reads; no
transactional module depends on Analytics and no dashboard query writes data.

## Context and date range

- Commercial metrics are explicitly scoped by both authorized `tenant_id` and
  active `store_id`.
- Inventory and procurement are tenant-wide because the current domain has no
  Store sourcing/allocation relationship. The UI labels both sections as "All
  company".
- `DashboardDateRange` computes half-open `[start, end)` boundaries in the
  Store timezone and converts them to UTC for `timestamptz` queries.
- Supported validated values are `today`, `yesterday`, `7d`, `30d`, and
  `this_month`. A missing value defaults to `7d`.
- Order metrics use `orders.placed_at`, the existing commercial order timestamp.
  Fiscal metrics use the Store-local `fiscal_documents.issue_date`.
- The selected range applies to commercial history. Operational attention
  counts are current-state workloads and intentionally are not limited by the
  selected range.
- Profitability uses `fulfillments.fulfilled_at` for both realized revenue and
  COGS so commercial and cost recognition remain in the same period.

## Money and currency

- Money remains PostgreSQL `numeric` and is serialized as scale-4 strings.
- AOV is calculated with `Brick\Math\BigDecimal`, never a float.
- The main dashboard aggregates only Orders matching the active Store currency.
- Payment ledger aggregates require both Payment and transaction currency to
  match the Store currency.
- Mismatched records are excluded and surfaced as warning counts. No FX
  conversion or cross-currency sum is performed.

## Metric definitions

### Sales

- **Gross Sales**: sum of immutable `orders.grand_total` for Orders placed in
  the selected range, current Store and Store currency, excluding only
  `CANCELLED`. Returns and refunds do not reduce Gross Sales.
- **Orders**: count over the same set used by Gross Sales. `placed_at` is the
  commercial date.
- **Average Order Value**: Gross Sales divided by Orders, rounded half-up to four
  decimal places. It is `0.0000` when there are no Orders.
- **Orders by status**: all real `OrderStatus` values for Orders in the range,
  including `CANCELLED` as its own status.
- **Sales trend**: Gross Sales and non-cancelled Order count grouped by local
  Store calendar date. Missing dates are emitted with zero values.
- **Top products**: top five snapshot product names grouped by `product_id` and
  `order_items.product_name`, ranked by summed snapshot quantity. Revenue is
  summed `order_items.line_total`. Cancelled Orders are excluded; refunds are
  not netted.

### Payments and refunds

- **Net Paid**: successful ledger `CAPTURE` transactions minus successful
  ledger `REFUND` transactions for the non-cancelled Orders in the selected
  range. This matches `OrderPaymentCalculator` rules and does not sum Payment
  intents or `payments.amount`.
- **Pending Payment Amount**: sum per eligible Order of
  `max(order.grand_total - ledger_net_paid, 0)`. The ledger is aggregated once
  per Order in SQL to avoid N+1 calculator calls.
- **Payment status summary**: Order counts by the real persisted
  `OrderPaymentStatus` values in the selected range.
- **Refunded Amount**: sum of `COMPLETED` Refund amounts whose `completed_at` is
  in the selected range. `PENDING`, `FAILED`, and `CANCELLED` do not contribute.
- **Pending refunds**: current count of `PENDING` Refunds for the Store.

### Gross profitability

- **Realized revenue ex tax**: immutable Order Item `line_total - tax_amount`,
  prorated by quantity in Fulfillments completed inside the selected range.
- **COGS**: sum of immutable Fulfillment Item COGS snapshots in that same range.
- **Gross Profit**: realized revenue ex tax minus COGS.
- **Gross Margin**: Gross Profit divided by realized revenue ex tax.
- **Cost Coverage**: fulfilled units with a cost snapshot in Store currency
  divided by all fulfilled units. COGS shows known cost when incomplete, but
  Gross Profit and Gross Margin are withheld until coverage is 100%.
- Refunds remain a separate metric and do not rewrite historical realized gross
  profit. No FX, shipping allocation, operating expenses, or net profit exists.

### Fulfillment and shipping

- **Fulfillment summary**: non-cancelled Order counts by the real persisted
  `OrderFulfillmentStatus` in the selected range. Shipment state is not used to
  derive these values.
- **Fulfillments pending**: current Fulfillment rows in `PENDING` or
  `PROCESSING`.
- **Shipments pending or ready**: current Shipment rows in `PENDING` or `READY`.
- **Shipped not delivered**: current Shipment rows in `SHIPPED`.

### Inventory

- Only active Products, active Variants and active Inventory Locations count.
- Availability is aggregated once per Variant as
  `sum(on_hand - reserved)` across active Locations.
- **Out of stock**: total Variant availability is less than or equal to zero.
- **Low stock**: total Variant availability is greater than zero and less than
  or equal to `commerce.inventory.low_stock_threshold` (default `5`).
- Counts are Variants, not Inventory Level rows. Variants without an active
  level have zero availability and are therefore out of stock.

### Procurement

- All procurement metrics are tenant-wide.
- Status counts use every real `PurchaseOrderStatus`.
- **POs to receive**: Purchase Orders in `ORDERED` or `PARTIALLY_RECEIVED`.
- **Incoming units**: ordered item quantity minus quantities on `POSTED`
  Purchase Receipts for those open POs. Draft/cancelled receipts do not count.
- **Overdue**: an open PO whose non-null `expected_at` is before current time.

### Returns and fiscal

- **Open returns**: `REQUESTED`, `APPROVED`, `RECEIVED`, or `INSPECTING`.
- **Awaiting inspection**: `RECEIVED` or `INSPECTING`.
- **Restock pending**: an open Return with a `RESTOCK` item whose restocked
  quantity is below its accepted quantity.
- **Pending exchanges**: `PENDING` or `APPROVED`, shown only with exchange view
  permission.
- Fiscal range counts use every real `FiscalDocumentStatus`.
- Fiscal attention is the distinct set of `GENERATED`, `SUBMISSION_PENDING`,
  `SUBMITTED`, or `REJECTED` documents plus documents with an `UNKNOWN`
  submission. A document is never double-counted.
- Missing active issuer/series configuration is a settings warning. `BETA` is
  explicitly displayed and never represented as production.

## Authorization

The dashboard route requires the normal authenticated Tenant and Store context.
There is no new `dashboard.view` permission. Each service is called and each
payload section is included only when the user has its domain view permission:

- Sales and top products: `orders.view`
- Payment ledger: `payments.view`
- Refund details: `refunds.view`
- Fulfillment and shipment data: `fulfillments.view` / `shipments.view`
- Inventory: `inventory.view`
- Procurement: `procurement.view`
- Returns and exchanges: `returns.view` / `exchanges.view`
- Fiscal/SUNAT: `fiscal_documents.view`
- Costing and gross profitability: `inventory.cost.view`

Order sales remain visible with `orders.view`; ledger detail requires
`payments.view`. Unauthorized query services are not executed and their keys are
absent from Inertia props.

## Performance and persistence

Queries use SQL `SUM`, `COUNT`, grouped subqueries, bounded top lists and no
per-Order loading. The current indexes already cover Store/date Orders,
tenant/date ledger transactions, active PO expected dates, tenant/Variant
Inventory Levels, Store/status fiscal documents, and operational module lookup
patterns. No speculative index migration was added without production query
plans.

There is no cache, analytics table, persisted alert, polling, websocket, event
stream, or notification record in this phase.
