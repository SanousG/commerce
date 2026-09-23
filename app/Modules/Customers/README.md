# Customers module

Customers are tenant-wide commercial identities and are intentionally separate from authenticated users and stores.

MVP identity rules:

- Email and the superficially normalized phone are each unique per tenant when present.
- Phone normalization only removes spaces, parentheses, dots, and hyphens while preserving an optional leading `+`; it is not E.164 validation.
- Advanced identity resolution, aliases, multiple emails or phones, WhatsApp, social identities, and merges belong to a future `CustomerIdentity` design.
- Fiscal identities belong to the future Billing/Tax/Invoicing domain.

Addresses are live customer data and may be physically deleted in this phase. Future orders must store immutable customer, billing-address, and shipping-address snapshots rather than reconstructing history from these records. A future order may have a nullable `customer_id`.

Guest checkout may later look up candidates by normalized email or phone inside the current tenant, but must only reuse a customer when that is safe. This phase deliberately does not implement `FindOrCreateCustomer` or merge heuristics.
