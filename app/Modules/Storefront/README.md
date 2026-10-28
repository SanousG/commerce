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
