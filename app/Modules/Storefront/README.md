# Storefront delivery module

Storefront is a public HTTP delivery and orchestration boundary. It does not
own Products, Listings, prices, carts, checkouts, orders, or Store settings.
Those records and rules remain in their existing modules.

## Public context

`PublicStoreContext` is request-scoped and independent of the administrative
`TenantContext` and `StoreContext`. `ResolvePublicStore` derives its Tenant and
Store only from a published `StorefrontPublication`, verifies both lifecycle
states, eagerly loads the allowed Store settings, and clears the context in a
`finally` block. Browser `tenant_id`, `store_id`, and administrative session
selection never participate in public resolution.

## Development route

SF-01 exposes only `/tienda/{publicHandle}`. The route requires all three:

1. `STOREFRONT_DEVELOPMENT_ROUTE_ENABLED=true`;
2. application environment `local` or `testing`;
3. a host in `STOREFRONT_DEVELOPMENT_HOSTS`.

Failure is a generic 404. Production remains blocked even if the feature flag
is accidentally enabled. Subdomains and custom domains are deliberately not
implemented.

## Public data contract

The SF-01 page exposes the public handle, display-name fallback, short
description, currency, resolved Vue locale, timezone, and validated theme
colors. It does not expose internal IDs, contacts, social links, Media IDs,
administrative identity, membership, permissions, or notifications. Media
delivery and the catalog belong to SF-02.

## Public catalog and themes

SF-02 adds a read-only projection over published, commercially eligible
Listings. Price lists, promotions and tenant-wide inventory are resolved in
batches by their owning modules. Public Media URLs contain derivative
checksums, never asset IDs, and are re-authorized against branding or an
eligible Listing before private Storage is read. Masters are never public.

Themes are allowlisted in `config/storefront-themes.php` and mirrored by a
static frontend registry. `essential` is the only available/default theme and
the safe fallback. Database values can never become arbitrary component paths.
See `docs/storefront/SF-02_ESSENTIAL_CATALOG.md` for routes and extension rules.

## Public guest cart

SF-03 adds a public orchestration layer over the existing Cart application
services. Ownership comes from an encrypted, HttpOnly, Store-specific cookie;
the token is never sent through URLs or Inertia props. Public mutations retain
CSRF, rate limits, session idempotency and Cart row locks. CartCalculator remains
authoritative for current listing eligibility, prices, promotions, inventory
availability and tax previews. No inventory reservation, Checkout, Order or
Payment is created. See `docs/storefront/SF-03_PUBLIC_CART.md`.

## Public guest checkout

SF-04 reuses the cart cookie as the sole public ownership credential and scopes
every Checkout lookup by the resolved Tenant, Store and Cart. Public HTTP never
accepts those IDs and never exposes the Checkout token. The separate Storefront
controller starts, updates, prepares and presents Checkout through existing
application services; it does not expose administrative controllers.

Preparation revalidates the cart, persists tax/commercial snapshots and reserves
inventory. SF-04.2 adds one fail-closed path: an explicitly authorized Store
pickup address, one active same-Tenant inventory location, and expected
`CASH + COD + NONE`. Public requests accept no shipping fields, and the location
UUID is never presented. No Customer, Order or Payment is created; Order
creation remains blocked for SF-04.3. See `SF-04_PUBLIC_CHECKOUT.md` and
`SF-04_2_PICKUP_AND_PAYMENT.md` under `docs/storefront`.
