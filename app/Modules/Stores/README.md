# Store administration

Stores are operational channels within a Tenant. Their `currency`, `timezone`
and `locale` are explicit commercial settings; browser timezone is never used
as authority. Store updates are always scoped through the active Tenant.

Owners and administrators can list, create, edit, activate and deactivate
Stores according to the existing `stores.*` permissions. Archiving is not
introduced by Phase 21. Deactivating the selected Store clears its session
context so middleware cannot continue operating with a stale Store.

Company identity, team membership and invitations do not belong to a Store.
Changing Stores therefore never changes the Team page.

## Store settings

`StoreSettings` is a one-to-one, Store-owned extension for merchant-facing
identity, branding, contact details, the Peruvian department/province/district
address shape, and validated social links. The row carries `tenant_id` so
composite foreign keys enforce that both the Store and branding assets belong
to the same Tenant. Existing Stores are backfilled with `PE` and the default
primary color `#17211B`; new Stores create the row in the Store creation
transaction. A nullable `display_name` falls back to the existing `Store.name`
instead of copying it.

Currency, timezone and locale remain columns on `stores` and are updated in the
same transaction as the extended settings. Locale keeps the existing
`ll_CC` convention (for example `es_PE`). Currency is a commercial authority,
not a conversion feature: settings never rewrite Price, Checkout, or Order
snapshots.

`stores.view` permits the settings page and `stores.update` permits changes.
These existing permissions already express the required OWNER/ADMIN matrix, so
no duplicate settings/branding permissions are introduced. Queries resolve the
Store from the active `TenantContext`; request `tenant_id` is never accepted.
Updates lock the Store, settings row, and selected Media assets, and require the
two `updated_at` versions shown to the administrator. A stale submission returns
HTTP 409 rather than silently overwriting another session.

Logo and favicon are same-Tenant `MediaAsset` references selected through the
existing `MediaPicker`; files are not copied and arbitrary URLs are not stored.
The schema references are the future storefront branding contract. A later
storefront delivery adapter may resolve a versioned derivative from these IDs,
but must not publish masters or reuse the authenticated admin endpoints.

Social links are a bounded JSON object for Facebook, Instagram, TikTok, YouTube
and WhatsApp. Form validation requires HTTPS and each channel's official domain.
Phone values are normalized to E.164-like `+` plus 7–15 digits.

Guest checkout, buyer notes, order auto-confirmation, shipping rates/zones and
courier behavior are deliberately not stored here. The current Checkout and
Order contracts do not support those preferences, and an inert flag would
misrepresent behavior. Fiscal issuer identity remains exclusively owned by
`FiscalIssuerProfile`.
