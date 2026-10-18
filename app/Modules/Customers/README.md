# Customers module

Customers are tenant-wide commercial identities and are intentionally separate from authenticated users and stores.

MVP identity rules:

- Email and the superficially normalized phone are each unique per tenant when present.
- Phone normalization only removes spaces, parentheses, dots, and hyphens while preserving an optional leading `+`; it is not E.164 validation.
- Advanced identity resolution, aliases, multiple emails or phones, WhatsApp, social identities, and merges belong to a future `CustomerIdentity` design.
- Fiscal identities belong to the future Billing/Tax/Invoicing domain.

Addresses are live customer data and may be physically deleted in this phase. Future orders must store immutable customer, billing-address, and shipping-address snapshots rather than reconstructing history from these records. A future order may have a nullable `customer_id`.

Guest checkout may later look up candidates by normalized email or phone inside the current tenant, but must only reuse a customer when that is safe. This phase deliberately does not implement `FindOrCreateCustomer` or merge heuristics.

## Admin reads (operational closure)

- `GET /admin/customers` accepts validated read-only filters `search` (first name, last name, email, phone) and `status`; unknown enum or over-long values redirect back with validation errors instead of being silently ignored.
- `GET /admin/customers/{customer}` is the read-only detail page. It is tenant-wide and deliberately lives outside the store middleware group, so it never redirects to store selection. It exposes the customer record (including `updated_at`), the address book, and a tenant-wide order history.
- Order history is tenant-wide on purpose: a customer is a tenant identity, while orders are store-owned. Each history row carries an `openable` flag that is true only when the order belongs to the store currently resolved in the session. `ordersInOtherStores` counts the remaining rows. The session store is read for display decisions only and is always re-checked against the active tenant; it never widens the query or grants access.
- The detail page is guarded by the same `CustomerAccess::canView` check as the list, and mutation capabilities (`update`, `archive`) are resolved from permissions and only shape the UI. All three built-in roles (OWNER, ADMIN, MEMBER) hold `customers.view`, so read denial is exercised through the payment/refund surfaces instead.
- Route contracts: cross-tenant identifiers and malformed (non-UUID) identifiers both return `404`; a permitted store switch never changes membership.
