# Promotions module

Promotions consumes base prices from Pricing and catalog identity from Catalog. Neither
Pricing nor Catalog depends on Promotions, and `variant_prices` are never modified by a
promotion calculation.

## MVP rules

- Scope is either the entire tenant catalog or an OR-union of explicit product, exact
  category, and variant targets. Category descendants are not implicit.
- Eligibility requires `ACTIVE` status and uses the UTC interval `[starts_at, ends_at)`.
- Fixed discounts apply only when their currency exactly matches the price list. There
  is no foreign-exchange conversion.
- Calculations use exact decimal arithmetic and round percentage discounts half-up to
  four decimal places. Currency display precision is a separate presentation concern.
- Promotions do not stack. Higher priority wins; ties use larger effective discount,
  then ascending UUID for deterministic resolution.
- The final amount has a zero floor and the base `VariantPrice` remains unchanged.

## Future checkout boundary

Checkout and Orders must recalculate promotions server-side. They must never trust an
effective price submitted by a browser. A future order item should snapshot base price,
discount, final unit price, and applicable promotion identifiers for auditability.
Coupon codes, usage limits, customer segmentation, store-specific rules, and advanced
stacking are intentionally outside Phase 8.
