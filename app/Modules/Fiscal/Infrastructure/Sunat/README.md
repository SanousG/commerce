# SUNAT direct integration

This infrastructure implements a private, tenant-scoped SUNAT direct adapter. It
does not claim SUNAT certification or legal production readiness. Production is
disabled by default and the concrete transport only accepts `BETA`.

## Official references

Consulted on 2026-09-26:

| Document | Version/date | Official URL | Implemented rule |
| --- | --- | --- | --- |
| Guia de Elaboracion de Documentos XML - Factura Electronica UBL 2.1 | 1.0, May 2017 | https://cpe.sunat.gob.pe/sites/default/files/inline-files/guia%2Bxml%2Bfactura%2Bversion%202-1%2B1%2B0%20%282%29_0%20%282%29.pdf | UBL 2.1, customization 2.0, Invoice type 01, namespaces, signature placement and filename |
| Guia de Elaboracion de Documentos XML - Boleta Electronica UBL 2.1 | 1.0, May 2017 | https://cpe.sunat.gob.pe/sites/default/files/inline-files/guia%2Bxml%2Bboleta%2Bversion%202-1%2B1%2B0_0_0%20%282%29.pdf | UBL 2.1, customization 2.0, Invoice type 03 and receipt lifecycle separation |
| Manual del Programador - Emision desde los Sistemas del Contribuyente | 2.1, updated 2021-05-12 | https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf | WS-Security UsernameToken, `sendBill`, `sendSummary`, `getStatus`, ZIP and CDR |
| Guia de Resumen Diario de Boletas y Notas | 2, January 2018 | https://cpe.sunat.gob.pe/sites/default/files/inline-files/GUIA_Resumen_de_Boletas_11-01-2018%20%282%29_2_0%20%281%29_0.pdf | UBL 2.0, customization 1.1, RC identifier, 500-line limit and ticket lifecycle |
| Servicios Web disponibles | page consulted 2026-09-26 | https://orientacion.sunat.gob.pe/01-servicios-web-disponibles | Official Beta, production and query WSDL endpoints |
| Reglas de validacion | updated 2026-08-26 | https://cpe.sunat.gob.pe/sites/default/files/2026-08/Reglas%20de%20validaci%C3%B3n%20-%20actualizado%20al%2026.08.2026.xlsx | Current rule reference; retained as an original provisioning artifact |

Official packages supplied manually from
https://cpe.sunat.gob.pe/guias-y-manuales because Cloudflare blocked automated
downloads:

| Artifact | SHA-256 |
| --- | --- |
| `Archivos XSD.zip` | `5CAC9D9353521340FBC15D23F465A4946B004535B9FF411541C87DA01694DBD5` |
| `XSL Actualizado al 06092022.zip` | `03A8DEC89D8BEB008E1B565CB72D52980E8FB317670ED5353FADE5DE96A8F0B8` |
| `XSL - UBL 2.0.zip` | `516E671FC7AD40FF02FDF863A164E4F4DA10208EBB51911D13C62930EA53879B` |
| `Reglas de validacion - actualizado al 26.08.2026.xlsx` | `CB5E871CFE3B81ABEA7FAF25B156E5D35B21E8C4837979350919926612DA73B6` |

The XSD ZIP contains both UBL 2.0 and 2.1. The 2.1 Invoice entrypoint is
`2.1/maindoc/UBL-Invoice-2.1.xsd`; the Daily Summary entrypoint is
`2.0/maindoc/UBLPE-SummaryDocuments-1.0.xsd`.

## Configuration and secrets

Configure `SUNAT_ENVIRONMENT=BETA`, credentials, the private disk, and a PKCS#12
storage key through environment variables. `SUNAT_CERTIFICATE_PATH` is relative
to the private `sunat` disk. Passwords and private keys are never returned to the
frontend or written to logs/database. Run `php artisan sunat:diagnose` to check
prerequisites without printing secrets; it accepts `--tenant=` and resolves the
certificate through the tenant-aware provider.

Credentials and certificates are resolved per submission through
`SunatCredentialsProvider::credentialsFor(tenantId)` and
`DigitalCertificateProvider::certificateFor(tenantId)`. The current Beta
implementations read the single global `config('sunat.*')` profile and ignore the
tenant id; that limitation is documented and must be replaced by a per-tenant
store before multiple tenants share Beta credentials. As a fail-closed alignment
check the use case compares 11 digits extracted from the certificate subject DN
and from the SOL username against the document `issuer_tax_id`, raising
`SUNAT_CERTIFICATE_RUC_MISMATCH` or `SUNAT_SOL_USERNAME_RUC_MISMATCH` before any
signature is produced (non-numeric usernames such as `MODDATOS` skip the check).

`SUNAT_ENVIRONMENT=PRODUCTION` is rejected unless
`SUNAT_PRODUCTION_ENABLED=true`; additionally, `SunatBetaTransport` refuses any
non-Beta environment. No production transport is registered in this phase.

## XSD provisioning

Official XSDs live under `SUNAT_XSD_ROOT` (default `provisioned/xsd`, private
disk). `php artisan sunat:provision-schemas` unpacks a locally supplied official
ZIP with zip-slip protection and byte-verifies every file against the committed
manifest `config/sunat_xsd_manifest.php` (relative path -> lowercase SHA-256);
missing or changed files fail, unexpected extra files warn. `--verify` checks the
tree without extracting and never touches the network. `sunat:diagnose` runs the
same verification.

## Pipeline

Factura submission (`SubmitFiscalDocumentToSunat`) is synchronous; the former
queued job was removed. The use case executes in this order:

1. Re-resolve the document through `tenant_id + store_id` (cross-tenant lookups
   fail with not-found) and check eligibility.
2. Reject any non-`PEN` document with `SUNAT_CURRENCY_NOT_SUPPORTED` before any
   side effect; only `PEN` is supported for SUNAT.
3. Build deterministic UBL 2.1 only from `FiscalDocument` and its items
   (`InvalidArgumentException` from serialization becomes a `ValidationException`).
4. Validate unsigned UBL against the provisioned official XSD
   (`UBL_VALIDATION_FAILED`).
5. Resolve credentials/certificate for the tenant and assert RUC alignment.
6. Sign the whole document with enveloped XMLDSig inside the first UBL extension.
7. Verify the signature locally against the certificate.
8. Validate signed UBL against XSD (`SIGNED_UBL_VALIDATION_FAILED`).
9. Package exactly one XML in the protocol ZIP (byte-deterministic: entry mtime
   forced to 0) and compute `request_hash = sha256(signed XML)`.
10. Claim the submission (see concurrency below); a lost claim returns the
    existing submission without reaching the network.
11. Store unsigned XML, signed XML and ZIP under the tenant/document/attempt.
12. Invoke Beta `sendBill`, store the original CDR ZIP/XML, then parse it.
13. In one transaction mark submission accepted/rejected and update the document.

Every failure after the claim is recorded with `recordFailure`, which writes the
submission status/error and updates the document through a fresh query (the
in-memory model may hold a stale status), then logs a sanitized warning
(tenant/document/submission ids, stage, status, phase, message; never XML,
credentials or bytes).

The 2017 official guide examples specify inclusive C14N with comments, SHA-1
digest, enveloped transform and RSA-SHA1 for RSA certificates. Those documented
legacy algorithms are isolated in `XmlDigitalSigner`; they are not represented
as a general security recommendation. The generated payload contains no XML
comments and serialization is deterministic (regression tested). Any algorithm
change requires a newer official SUNAT rule and regression fixtures.

### Rounding and totals (multi-line safety)

Stored snapshots retain four decimals; XML monetary fields use two decimals,
half-up. Per line: `extension = round2(base)`, `total = round2(line_total)` and
`tax = total - extension`, so each line reconciles exactly in the document.
Document `LineExtensionAmount`/`TaxExclusiveAmount` are the sum of line
extensions, `TaxAmount` the sum of line taxes, and
`TaxInclusiveAmount`/`PayableAmount` the sum of line totals. Two fail-closed
checks run before any side effect:

- Exact four-decimal reconciliation between snapshot items and document totals,
  otherwise `SUNAT_UBL_TOTALS_DO_NOT_MATCH_ITEMS`.
- `sum(round2(line_total)) == round2(grand_total)`, otherwise
  `SUNAT_UBL_ROUNDING_DRIFT_NOT_RECONCILED` (documented residual drift risk).

A non-Standard tax category carrying a non-zero tax is rejected with
`SUNAT_UBL_UNEXPECTED_CATEGORY_TAX`. Stored snapshots are never changed.
Discounted documents are blocked because the current snapshot does not yet
model the tax-exclusive UBL allowance needed to represent the discount without
recalculating tax. Current product quantities are discrete commerce units and
are represented by verified Catalog 03 code `NIU`.

`cac:RegistrationAddress` follows the UBL 2.1 `AddressType` sequence exactly
(`ID`, `CityName`, `PostalZone`, `Region`, `District`, `AddressLine`, `Country`);
UBL 2.1 uses `cbc:Region`/`cbc:District`, not the UBL 1.1 `RegionName`/
`DistrictName`. The customer address is emitted as `cac:PostalAddress/cbc:StreetName`.

## XSL limitation

The supplied SUNAT XSL files are preserved but are not executed locally. They
reference unpublished/missing DataPower resources such as
`local:///commons/error/validate_utils.xsl`, catalog data and factoring dates.
Rewriting those dependencies would invent validation behavior. Local validation
therefore means official XSD plus explicit verified rules and tests, not a claim
that the entire SUNAT DataPower XSL pipeline was reproduced.

## Lifecycle and safety

Each `FiscalSubmission` is one immutable attempt number. A partial unique index
(`fiscal_submissions_one_in_flight`) allows only one in-flight attempt per
document.

### Submission claim and concurrency

Before any network or artifact write the use case runs a short transaction that
locks the document row with `lockForUpdate`, re-checks eligibility under the
lock, and either returns an existing compatible submission or creates the next
attempt and moves the document to `SUBMISSION_PENDING`. Only the creator of the
submission row reaches the network; a concurrent caller receives the existing
submission without a second `sendBill`. A duplicate-key violation on the
partial unique index is caught and re-read as a second barrier. A submission
whose `request_hash` matches an existing non-terminal/accepted attempt is
reused, so retrying the same signed payload never duplicates a transmission.

`request_hash` is `sha256(signed XML)` (not the ZIP); the ZIP is additionally
byte-deterministic because entry mtimes are forced to 0, so identical payloads
produce identical archives.

### Delivery phase classification

Every transport failure is classified into `SunatDeliveryPhase`:

- `PreDelivery` — the request certainly never reached SUNAT: WSDL/DNS resolution
  failures, connection refused, TLS handshake failures. Only these strict
  patterns are matched; anything ambiguous is not classified as pre-delivery.
- `RemoteResponse` — a SOAP fault carrying a non-empty `faultcodens` (a real
  SUNAT business response).
- `DeliveryUnknown` — timeouts, connection resets, HTTP errors and every other
  ambiguous failure where the request may have been processed.

Outcome mapping (enforced by `recordFailure`):

| Classification | Submission status | Document status | Retry |
| --- | --- | --- | --- |
| `PreDelivery` / `RemoteResponse` | `ERROR` | `GENERATED` | allowed |
| `DeliveryUnknown` | `UNKNOWN` | `SUBMITTED` | blocked (manual) |
| Local preparation failure (before send) | `ERROR` | `GENERATED` | allowed |

`UNKNOWN` means delivery may have occurred and blocks blind automatic resend.
Only a parsed CDR code `0` marks the document accepted; any nonzero CDR code is
retained as rejected. Transport errors never become a SUNAT rejection.
Observations are preserved on accepted documents. A missing CDR after a send is
`SUNAT_CDR_MISSING` and a malformed CDR is `SUNAT_CDR_REQUIRED_FIELDS_MISSING`;
both are recorded as `UNKNOWN` rather than triggering a blind resend.

`SunatTransport::getStatus` exists for reconciliation but currently has no
caller: automatic ticket/document reconciliation (audit item H2) is deliberately
not implemented. Documents stuck in `SUBMITTED`/`UNKNOWN` or `PROCESSING`
require manual review of the stored artifacts and CDR before any state change.

### Rate limiting and logging

The admin submission route is throttled (`throttle:sunat-submit`: 10 attempts
per minute per user, 30 per minute per IP) in addition to permission and
tenant/store scoping. Failures log a sanitized warning with identifiers, stage,
status and phase only — never XML, ZIP bytes, credentials or headers.

Artifacts use `storage/app/private/sunat/tenants/{tenant}/documents/{document}`.
No public URL is generated. Download routes re-resolve document and submission
through current tenant/store context and `fiscal_documents.manage`.

CDR parsing uses `LIBXML_NONET`; CDR ZIPs have size, file-count, filename and
path-traversal checks. SOAP trace is disabled.

## Boleta and Daily Summary

Boleta UBL 2.1 generation and XSD validation are separate from transmission.
Individual `sendBill` is blocked. `FiscalDailySummary` and membership tables are
prepared for UBL 2.0 `SummaryDocuments`, `sendSummary`, tickets and `getStatus`.
Functional summary building/submission remains disabled until its complete
application workflow and tests are added; no Boleta is falsely marked accepted.

## Beta limitations

Beta is for controlled functional testing, not real issuance or load testing.
It may not reproduce every production validation, and Beta acceptance is not a
legal certification. Normal automated tests never contact SUNAT. This phase
does not send production traffic.

Manual Beta smoke procedure:

1. Set the Beta environment, full documented SOL username, password and a
   private PKCS#12 storage key outside source control.
2. Run `php artisan sunat:diagnose` inside Sail and resolve every failed check.
3. Create/select an existing test Factura in the admin UI; do not create real
   commercial data automatically.
4. Use **Enviar Factura a SUNAT** once. Do not use this flow for stress tests.
5. Confirm the attempt, response, signed XML and original CDR in the private
   document view. No automated implementation-time Beta call is performed.
6. If the attempt ends `UNKNOWN`/`SUBMITTED`, do not press send again: review
   the stored artifacts, query SUNAT (`getStatus` is not wired yet) and resolve
   manually. The route is rate limited (10/min per user).

## Deliberately excluded

Credit Note submission, communication of cancellation, PDF, customer portal,
production enablement, OSE behavior and compliance scheduling are not included.
