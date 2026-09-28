# Tax module

Tax configuration is tenant-wide and separate from commercial pricing and
fiscal documents. Products may reference a nullable `TaxCategory`; no tax is
inferred when that assignment or an unambiguous effective `TaxRate` is missing.

Sale prices are tax-inclusive. For a standard category:

```text
base = inclusive total / (1 + rate / 100)
tax  = inclusive total - base
```

Calculations use `BigDecimal`, internal division scale 10, half-up rounding,
and persisted monetary scale 4. Exempt and unaffected categories remain
distinct and require a zero rate. Rates are configuration, not hardcoded IGV
constants.

Cart calculates a dynamic tax preview without persisting tax on CartItem.
Checkout preparation uses one operation timestamp, applies promotions first,
then decomposes each final line total and persists snapshot version 1 before
inventory reservation. Order copies that snapshot exactly. FiscalDocument is a
representation of Order and does not resolve current categories or rates.

Rates use the operation instant converted to the Store timezone to choose the
inclusive local effective date; timestamps are stored in UTC. Missing category
is `TAX_CATEGORY_MISSING`, no effective rate is `TAX_RATE_MISSING`, and inactive
or ambiguous configuration is `TAX_CONFIGURATION_INVALID`.

Historical Orders remain `tax_snapshot_version = null`. They are not inferred
from current Product configuration and new fiscal issuance is blocked with
`ORDER_TAX_SNAPSHOT_MISSING`. Existing generated fiscal documents are unchanged.
