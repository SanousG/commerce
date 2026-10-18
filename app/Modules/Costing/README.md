# Inventory Costing

Phase 23 implements operational inventory valuation and gross profitability. It
does not implement accounting entries, accounts payable, landed cost, FX, FIFO,
LIFO, specific identification, or net profit.

## Method and ownership

- The method is moving weighted average, scoped by Tenant, Inventory Location,
  and Product Variant.
- `inventory_levels` remains the only quantity authority. An
  `inventory_valuation` stores only nullable `average_unit_cost`, currency, and
  a concurrency version. Inventory value is derived as `on_hand * average`.
- A null average is `UNINITIALIZED`. Existing quantities are never backfilled
  from current Supplier Product cost.
- Unit cost uses PostgreSQL `numeric(19,6)` for internal precision. Monetary
  snapshots and value deltas use `numeric(19,4)`. PHP calculations use
  `Brick\Math\BigDecimal` and half-up rounding; floats are not used.

For an inbound quantity:

```text
new average = ((old quantity * old average) + (inbound quantity * inbound cost))
              / (old quantity + inbound quantity)
```

When old quantity is zero, the inbound cost becomes the average. Outbound stock
does not change the moving average.

## Recognition and ledger

- Posting a Purchase Receipt increases quantity, updates valuation from the
  receipt item snapshot, and appends `PURCHASE_RECEIPT` in one transaction.
- Completing a Fulfillment decreases quantity, freezes unit cost and COGS on
  each Fulfillment Item, and appends `FULFILLMENT_COGS` in one transaction.
  COGS is recognized at physical fulfillment, never at order or payment time.
- A RESTOCK return uses the weighted historical cost of fulfilled snapshots for
  the original Order Item and enters that value through the normal moving
  average formula. This MVP cannot identify which exact fulfillment batch a
  customer returned; it uses the weighted cost across effectively fulfilled
  units. Non-restock dispositions and refunds have no costing effect.
- Administrative initialization sets cost for positive existing stock without
  changing quantity and appends `OPENING_COST`. It can happen only once.
- `inventory_cost_movements` is append-only in Eloquent and PostgreSQL.
  Receipt, fulfillment, and opening sources are unique. Partial Return restocks
  are separate physical inventory movements and therefore use the unique linked
  Inventory Movement as their operation identity.

## Transactions and locking

Costing joins existing outer transactions. No quantity or cost write can commit
alone. It preserves each workflow's existing order: receipts and initialization
lock Location, Variant, Inventory Level, then Inventory Valuation; fulfillment
locks Order, Fulfillment, Reservations, sorted Inventory Levels, then sorted
Valuations; returns lock Return, Return Item, Location, Inventory Level, then
Valuation. This serializes receipt-versus-fulfillment operations without a
second conflicting lock order.

## Currency and legacy policy

Each Location/Variant valuation has one active ISO currency. A receipt or
return in another currency fails atomically with
`INVENTORY_COST_CURRENCY_MISMATCH`; there is no conversion. Positive legacy
stock must be initialized before another valued receipt or a new fulfillment,
otherwise the operation fails with `INVENTORY_COST_NOT_INITIALIZED`. Historical
Fulfillment Items remain nullable and are never reconstructed from current
costs.

Transfers and generic quantity adjustments are intentionally not assigned an
invented inbound cost in this phase. An inbound adjustment has no cost input,
and historical reconstruction would require chronological receipt and
fulfillment evidence. These workflows require a future explicit valued
transfer/adjustment design before they can provide initialized destination
stock.

## Profitability

- Ordered revenue is the immutable Order Item `line_total - tax_amount`.
- Realized revenue is that snapshot prorated by physically fulfilled quantity.
- Order and item COGS are sums of immutable Fulfillment Item snapshots.
- Realized gross profit is realized revenue minus COGS. Gross margin is gross
  profit divided by realized revenue. Net profit is not calculated.
- Dashboard periods use `fulfillments.fulfilled_at` in the Store timezone for
  both realized revenue and COGS. Existing Gross Sales semantics are unchanged.
- Refunds do not rewrite historical revenue or COGS. Existing refund metrics
  remain separate.
- Cost coverage is covered fulfilled units divided by total fulfilled units.
  Known COGS is shown when incomplete, but gross profit and margin are withheld.
  Cost currency different from Store/Order currency is uncovered; no FX is
  attempted.

Cost and profitability payloads require `inventory.cost.view`. Initialization
requires `inventory.cost.manage`. OWNER and ADMIN receive both; MEMBER receives
neither by default. Inventory valuation is Tenant-wide. Order and dashboard
profitability are additionally Store-scoped.
