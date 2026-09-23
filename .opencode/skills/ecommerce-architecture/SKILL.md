---
name: ecommerce-architecture
description: Use when designing architecture, modules, domain boundaries, or external integrations for this multi-tenant Laravel e-commerce SaaS.
---

# E-commerce Architecture

## System Shape

- This system is a multi-tenant e-commerce SaaS.
- Use a Modular Monolith architecture in one Laravel application.
- Keep the backend, admin, and storefront in the same project and initial
  deployment.
- Do not introduce microservices unless the user makes an explicit future
  architecture decision.
- Prefer pragmatic Laravel designs and avoid ceremonial architecture.

## Domain Roadmap

The expected future domains are:

- Identity
- Tenancy
- Stores
- Catalog
- Inventory
- Pricing
- Promotions
- Customers
- Cart
- Checkout
- Orders
- Payments
- Fulfillment
- Shipping
- Returns
- Suppliers
- Procurement
- Conversations
- AI
- Marketing
- Automations
- Analytics
- Integrations
- Billing
- Audit

This list is a roadmap, not a scaffolding instruction. Do not create all modules
in advance. Create a module only when the active phase requires it, and never
advance to another phase without explicit instruction.

## Boundaries

- Keep domain ownership explicit as modules grow.
- Avoid circular dependencies between modules.
- Prefer stable contracts or application-level coordination when one domain
  needs another domain's behavior.
- Integrate external providers through adapters or connectors so provider
  details do not leak into controllers or core domain logic.
- Keep one deployment and one application unless explicitly instructed
  otherwise.
