# Media module

## Ownership and boundary

`MediaAsset` belongs to a Tenant, not a Store. The media library therefore uses `TenantContext` and never requires `StoreContext`. Media owns upload validation, normalization, storage, derivatives, metadata, private delivery, and safe deletion. Catalog owns `ProductMedia` and `ProductVariantMedia`, and coordinates attachment without creating a Media → Catalog application-service dependency.

## Image pipeline and storage

- Accepted input is limited to genuine JPEG, PNG, and WebP detected with `fileinfo`, cross-checked with `getimagesize`, and decoded by GD. SVG, GIF, BMP, TIFF, documents, corrupt data, and disguised files are rejected.
- Limits come from `config/media.php`: 10 MB, 6000×6000, and 20 megapixels by default. Dimensions are checked before GD decoding to reduce decompression-bomb risk.
- JPEG EXIF orientation is applied when EXIF is available. Every accepted image is decoded and re-encoded; uploaded bytes and EXIF/GPS metadata are never retained.
- The “master” is the normalized, metadata-free re-encoding in the accepted raster format. JPEG uses quality 90, PNG compression 6, and WebP quality 85 by default.
- GD with JPEG, PNG, and WebP support plus PHP `fileinfo` is required. No image package was added.
- `MEDIA_DISK` selects a Laravel filesystem disk. The private opaque key is `media/tenants/{tenantId}/{mediaId}/master.{ext}`; the original filename is administrative metadata only.
- `THUMBNAIL` (300 px) and `MEDIUM` (1200 px) preserve aspect ratio, never upscale, and are WebP. Derivatives are rows in `media_derivatives`, not separate assets.
- Masters and derivatives are private and are served to authorized admins through scoped controllers with explicit MIME, `inline`, private caching, and `nosniff`.

## Product and variant relationships

`product_media` supports an ordered gallery, a nullable contextual ALT override, and at most one primary image per product. A PostgreSQL partial unique index enforces the primary invariant. `product_variant_media` provides an optional ordered variant gallery. Both relationships use composite tenant foreign keys and prohibit duplicate asset attachment.

Assets are reusable across any number of same-tenant products and variants without copying bytes. Removing an attachment does not delete its asset. Product hard deletion cascades only attachment rows; assets remain. A future variant deletion has the same behavior.

Future storefront resolution is: published StoreListing → tenant-wide Product → ProductMedia → derivative. A variant gallery overrides the product gallery when non-empty; otherwise it inherits the product gallery. Contextual ALT resolves as `product_media.alt_text ?? media_assets.alt_text ?? product.name`. StoreListing does not duplicate media, and images do not affect listing eligibility.

## Deletion

Deletion returns HTTP 409 while product, variant, Store logo, or Store favicon references exist. Branding updates and deletion both lock the MediaAsset row, preventing a new branding reference from racing with deletion. For an unused asset, the service marks it `DELETING`, removes master and derivative objects idempotently, then deletes the DB row. A storage failure restores `READY`. There is no distributed transaction: a final DB failure after successful object deletion intentionally leaves a detectable `DELETING` row for operational reconciliation rather than silently presenting a ready asset with missing bytes.

## Security and permissions

All reads and mutations resolve assets through the active authorized Tenant. Cross-tenant IDs return 404, including attachment attempts. Request input cannot choose tenant, disk, key, checksum, MIME, or status. Mutations remain under Laravel web sessions and CSRF.

- `media.view`: OWNER, ADMIN, MEMBER
- `media.upload`, `media.manage`, `media.delete`: OWNER, ADMIN
- Product/variant attachment requires both `products.update` and `media.view`; upload additionally requires `media.upload`.
- Uploads are rate-limited per user/tenant and tenant.

Store branding stores only same-Tenant asset IDs and continues to use private admin thumbnails for preview. No public endpoint, CDN, R2 connection, document/video flow, quota, archive, byte replacement, or cross-tenant deduplication is implemented. A future public controller can expose immutable/versioned derivatives or CDN URLs without exposing masters or changing domain ownership.
