# Catalog module

Catalog owns tenant-wide Products, Categories, Options, Variants, and their media attachment relationships. Products and their media do not belong to a Store. StoreListing references a Product and does not duplicate gallery state.

`ProductMedia` is an ordered, reusable MediaAsset relationship with at most one primary image and an optional contextual ALT override. `ProductVariantMedia` is an optional ordered gallery. Catalog application services coordinate same-tenant attachment, detachment, primary selection, and ordering transactionally; the Media module remains independently usable and does not call Catalog services.

Archiving a Product retains media relationships. If a Product or future Variant is hard-deleted, its relationship rows cascade while MediaAssets remain because they may be reused. Images do not change listing publication eligibility, commercial snapshots, carts, checkouts, or historical orders.

Future storefront behavior is documented in `app/Modules/Media/README.md`; no storefront behavior is implemented here.
