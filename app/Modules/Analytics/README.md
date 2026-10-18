# Analytics / Admin Dashboard

Phase 22 implements a read-only operational projection for the active merchant
Tenant and Store. Analytics depends on transactional modules for reads; no
transactional module depends on Analytics and no dashboard query writes data.
The administrative dashboard closure adds custom period ranges,
period-over-period comparison, a dependency-free evolution chart and a recent
orders list on the same read-only projection.

## Context and date range

- Commercial metrics are explicitly scoped by both authorized `tenant_id` and
  active `store_id`.
- Inventory and procurement are tenant-wide because the current domain has no
  Store sourcing/allocation relationship. The UI labels both sections as "All
  company".
- `DashboardDateRange` computes half-open `[start, end)` boundaries in the
  Store timezone and converts them to UTC for `timestamptz` queries.
- Supported validated values are `today`, `yesterday`, `7d`, `30d`,
  `this_month`, and `custom`. A missing value defaults to `7d`.
- `custom` requires `from` and `to` (`Y-m-d`) and is interpreted as inclusive
  store-local calendar days: the exclusive end is the next store-local midnight,
  so the whole `to` day is included without dropping trailing microseconds.
  `from` must not be after `to`, and the window is capped at
  `DashboardDateRange::MAX_CUSTOM_DAYS` (366) inclusive days. Both bounds are
  validated by `DashboardRequest`; invalid input redirects back with errors.
- Order metrics use `orders.placed_at`, the existing commercial order timestamp.
  Fiscal metrics use the Store-local `fiscal_documents.issue_date`.
- The selected range applies to commercial history, the daily trend, top
  products and the recent orders list. Operational attention counts and the
  refund/return status breakdowns are current-state workloads and intentionally
  are not limited by the selected range.
- Profitability uses `fulfillments.fulfilled_at` for both realized revenue and
  COGS so commercial and cost recognition remain in the same period.
- The Store timezone is the single source of truth for period boundaries; the
  browser timezone is never used. DST transitions are handled by whole
  store-local calendar days, so a day is never lost or gained.

## Period-over-period comparison

- For every main indicator the dashboard computes the current window and the
  immediately preceding window of identical calendar length (`previous()`), and
  reports `current`, `previous`, `delta`, and `percent`.
- `percent` is `(current - previous) / previous * 100`, half-up to two decimals.
  It is `null` when the previous value is zero, and the UI renders a neutral
  "no previous baseline" state instead of an infinite percentage.
- The previous window is always the same length as the current one, so unequal
  durations are never compared. Compared metrics: `gross_sales`, `orders`,
  `average_order_value` (with `orders.view`), `captured_amount` and `net_paid`
  (with `payments.view`), and `refunded_amount` (with `refunds.view`).

## Money and currency

- Money remains PostgreSQL `numeric` and is serialized as scale-4 strings.
- AOV is calculated with `Brick\Math\BigDecimal`, never a float.
- The main dashboard aggregates only Orders matching the active Store currency.
- Payment ledger aggregates require both Payment and transaction currency to
  match the Store currency.
- Mismatched records are excluded and surfaced as warning counts. No FX
  conversion or cross-currency sum is performed.

## Metric definitions

The table below is the authoritative definition of each main KPI. "Scope" is
either the active Store (+ authorized Tenant) or the whole Tenant; "date" is the
reference timestamp; "states" lists the included domain states.

| KPI | Scope | Source | Money/field | Date | States included | Permission |
| --- | --- | --- | --- | --- | --- | --- |
| Order value (Gross Sales) | Store | `orders` | `grand_total` | `placed_at` | all except `CANCELLED` | `orders.view` |
| Orders placed | Store | `orders` | count | `placed_at` | all except `CANCELLED` | `orders.view` |
| Average order value | Store | `orders` | `grand_total / count` | `placed_at` | all except `CANCELLED` | `orders.view` |
| Orders by status | Store | `orders` | count | `placed_at` | every `OrderStatus` | `orders.view` |
| Sales trend | Store | `orders` | `grand_total`, count | `placed_at` (store-local day) | all except `CANCELLED` | `orders.view` |
| Top products | Store | `order_items` + `orders` | `quantity`, `line_total` | `orders.placed_at` | all except `CANCELLED` | `orders.view` |
| Recent orders | Store | `orders` | `grand_total` | `placed_at` | every `OrderStatus` | `orders.view` |
| Confirmed collections | Store | `payment_transactions` + `payments` | `amount` (`CAPTURE`) | `orders.placed_at` | `SUCCEEDED` ledger only | `payments.view` |
| Net collections (Net Paid) | Store | `payment_transactions` + `payments` | `CAPTURE - REFUND` | `orders.placed_at` | `SUCCEEDED` ledger only | `payments.view` |
| Pending payment | Store | `orders` + ledger | `grand_total - net paid` | `placed_at` | non-cancelled Orders | `payments.view` |
| Payment status summary | Store | `orders` | count | `placed_at` | every `OrderPaymentStatus` | `payments.view` |
| Refunded amount | Store | `refunds` | `amount` | `completed_at` | `COMPLETED` only | `refunds.view` |
| Refund requests | Store | `refunds` | count | current state | every `RefundStatus` | `refunds.view` |
| Fulfillment by status | Store | `orders` | count | `placed_at` | every `OrderFulfillmentStatus` | `fulfillments.view` |
| Returns by status | Store | `returns` | count | current state | every `ReturnStatus` | `returns.view` |
| Gross profitability | Store | `fulfillment_items` | revenue/COGS | `fulfillments.fulfilled_at` | `FULFILLED` | `inventory.cost.view` |
| Stock attention | Tenant | `inventory_levels` | `on_hand - reserved` | current state | active Products/Variants/Locations | `inventory.view` |
| Procurement attention | Tenant | `purchase_orders` | counts/units | current state | open POs, `POSTED` receipts | `procurement.view` |
| Fiscal status | Store | `fiscal_documents` | count | `issue_date` | every `FiscalDocumentStatus` | `fiscal_documents.view` |

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
- **Top products**: top ten snapshot products grouped by `product_id` and
  `order_items.product_name`, ranked by summed snapshot quantity (ties broken by
  snapshot name). Revenue is summed `order_items.line_total`. Cancelled Orders
  are excluded; refunds are not netted (the ranking is gross). Archived or
  deleted Products are never excluded because the row is a historical snapshot;
  the UI links to the current Product only when its permission is held.
- **Recent orders**: the latest ten Orders placed inside the selected range,
  newest first, deterministic on `placed_at DESC, order_number DESC`. Customer
  name, local placement time and total come from the Order snapshot; no related
  model is loaded, so there is no N+1.

### Payments and refunds

- **Confirmed collections (captured)**: sum of successful ledger `CAPTURE`
  transactions for the non-cancelled Orders in the selected range. It counts
  captures only, never Payment intents, `payments.amount`, or pending / failed
  attempts.
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
- **Refund request breakdown**: current Refund counts by every real
  `RefundStatus` for the Store, plus `pending_refunds`. These are current-state
  workload counts, not period activity, and never treat a pending request as a
  completed outflow.

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

- **Returns by status**: current Return counts by every real `ReturnStatus` for
  the Store. This distinguishes requested, approved, received, inspecting,
  completed, rejected and cancelled requests.
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

- Sales, top products and recent orders: `orders.view`
- Payment ledger, confirmed collections, net collections and period comparison:
  `payments.view`
- Refund details: `refunds.view`
- Fulfillment and shipment data: `fulfillments.view` / `shipments.view`
- Inventory: `inventory.view`
- Procurement: `procurement.view`
- Returns and exchanges: `returns.view` / `exchanges.view`
- Fiscal/SUNAT: `fiscal_documents.view`
- Costing and gross profitability: `inventory.cost.view`

Order sales remain visible with `orders.view`; ledger detail requires
`payments.view`. Unauthorized query services are not executed and their keys are
absent from Inertia props. The recent orders list is gated by `orders.view`;
each row exposes `payment_status` only with `payments.view` and
`fulfillment_status` only with `fulfillments.view`. Comparison metrics follow
the same permissions as their source section. A MEMBER without financial
permissions receives neither the payment section nor any financial comparison
metric.

## Frontend

The dashboard is a single Inertia page (`admin/Dashboard.vue`) that reuses the
existing AdminLayout and Tailwind utility style. It renders the period selector,
an optional custom date range, KPI cards with period deltas, a dependency-free
inline SVG trend chart (`DashboardTrendChart.vue`), the recent orders list, top
products, stock attention, procurement, returns/refunds and fiscal sections.
No chart library or new design system was introduced.

## Performance and persistence

Queries use SQL `SUM`, `COUNT`, grouped subqueries, bounded top lists and no
per-Order loading. `SalesDashboardMetrics::summary()` and
`PaymentDashboardMetrics::summary()` compute the previous window with scalar
aggregates only, so the trend and top list are never recomputed. The current
indexes already cover Store/date Orders, tenant/date ledger transactions, active
PO expected dates, tenant/Variant Inventory Levels, Store/status fiscal documents,
and operational module lookup patterns. No index migration was added because no
query plan evidence justified one; `DashboardIsolationTest` asserts that the
query count does not grow with Order volume.

There is no cache, analytics table, persisted alert, polling, websocket, event
stream, or notification record in this phase.

## Deliberately omitted / limitations

- No FX conversion: Orders or ledger transactions in a currency other than the
  Store currency are excluded and counted as warnings, never summed.
- No storefront, payment gateway, SUNAT transport, subscription, shipping-fee,
  courier, full accounting, general ledger, or BI/export feature.
- No universal low-stock threshold is invented: low stock uses the configured
  `commerce.inventory.low_stock_threshold`; without a configured value the
  documented default applies and the rule is stated in the UI.
- Inventory is Tenant-wide because the domain has no Store allocation; the UI
  labels it "All company" so its scope is never confused with Store metrics.
- Profitability is withheld when fulfillment cost coverage is incomplete; the
  dashboard never estimates missing COGS.
