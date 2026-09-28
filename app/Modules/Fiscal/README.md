# Fiscal module

`Order`, `FiscalDocument`, and SUNAT transport are separate concerns:

- Order is the immutable commercial agreement and source of item prices.
- FiscalDocument is an immutable representation copied from the confirmed
  Order tax snapshot.
- Transport is an external integration concern represented only by contracts
  and `FiscalSubmission` storage preparation.

## Implemented

- Tenant-wide issuer profile and store-specific configured series.
- Concurrency-safe number allocation with an Order lock and a FiscalSeries row
  lock. Uniqueness constraints prevent duplicate primary documents and numbers.
- Factura and boleta generation through `GenerateFiscalDocument`.
- Copying tax-inclusive line and aggregate snapshots without increasing or
  recalculating the Order total.
- Immutable issuer, customer, item, category, rate, and total snapshots.
- Credit-note type, relationship, reason, adjustment, and database constraints
  as preparation only.
- Synchronous SUNAT submission pipeline: idempotent per-document claim with a
  partial unique index, official XSD validation, XMLDSig signing/verification,
  deterministic ZIP packaging, `sha256(signed XML)` request hash, Beta `sendBill`,
  CDR parsing, artifact storage and attempt history.
- Delivery-phase classification (pre-delivery / remote response / unknown) with
  retryable vs. blocked outcomes, plus fail-closed monetary reconciliation and
  PEN-only enforcement.
- Rate-limited, tenant/store-scoped submission and artifact routes.

Generation requires `tax_snapshot_version = 1`, reconciles item snapshots with
Order aggregates, and copies them without reading Product, TaxCategory, or
TaxRate. Legacy Orders are rejected with `ORDER_TAX_SNAPSHOT_MISSING`; existing
generated documents remain untouched. Non-zero shipping is still rejected
because shipping taxation has not been defined.

The implementation guarantees unique allocation and counter increment under a
row lock. It does not claim a broader regulatory no-gap guarantee; failure,
void, and contingency handling remain future work.

## SUNAT Beta infrastructure

Phase 20 adds UBL 2.1 Factura/Boleta serialization, official local XSD
validation, XMLDSig signing and verification, protocol ZIPs, private artifacts,
Beta `sendBill`, CDR parsing, attempt history and a production guard. See
`Infrastructure/Sunat/README.md` for sources, hashes, configuration, security,
known XSL limitations and the precise implemented lifecycle.

This is not a SUNAT certification claim. Boleta individual submission is
blocked and the Daily Summary schema is architectural preparation only.

Still excluded: production traffic, functional Credit Note submission,
communication of cancellation, PDF, detracciones, percepciones, retenciones,
advanced ISC, remission guides, supplier fiscal documents and accounting.

Refunds and Returns never create a Credit Note automatically. A Refund records
money returned; a Credit Note is a fiscal document. Neither substitutes for or
mutates the other.
