# Plan posterior de implementación del rediseño

**Estado:** bloqueado hasta aprobación explícita del diseño. Esta fase no implementa el nuevo frontend.

## Principios de ejecución

- Mantener Laravel/Inertia props, FormRequests, rutas, Gates, TenantContext, StoreContext y state machines.
- Migración incremental, nunca big-bang: una plantilla y módulo a la vez.
- Backend sigue siendo autoridad; RBAC visual solo mejora UX.
- No agregar dependencia UI hasta demostrar que primitives Vue/Tailwind no bastan.
- Cada bloque conserva mobile, teclado, focus visible, reduced motion y contraste AA.
- No usar Redis, cache global ni prefetch cross-tenant para “acelerar” el diseño.

## Bloque A — Foundations y shell

1. Congelar tokens aprobados en CSS/Tailwind v4.
2. Crear primitives de button/form/status/feedback.
3. DataTable, ResponsiveDataList y Pagination con navegación Inertia.
4. Extraer AdminSidebar/AdminHeader/Breadcrumbs del layout actual.
5. TenantSwitcher/StoreSwitcher con endpoints existentes.
6. Estados globales de Inertia: progress, session expired, 403, 409 y feedback.

**Gate:** typecheck/build; tests de navegación/RBAC/tenant-store; revisión visual 1440/768/390.

## Bloque B — Catalog e inventory

- Products, Product Edit (variants/options/media/pricing), Categories, Media.
- Listings, Pricing, Promotions.
- Inventory, Variant, Transfer, Locations, Valuation.
- Store Settings como referencia de formulario largo/media.

**Riesgos:** props extensas de Product Edit; múltiples formularios; thumbnails privados; no alterar tax/pricing.

## Bloque C — Sales y operations

- Customers, Carts, Checkouts, Orders.
- Payments, Fulfillment/Shipments, Returns, Refunds, Exchanges.

**Riesgos:** workbenches largos y state machines; no mover decisiones comerciales al frontend.

## Bloque D — Workspace y compliance

- Dashboard, Stores, Settings, Team/Access, Notifications, Activity.
- Suppliers, Purchase Orders/Receipts.
- Tax, Fiscal settings y Fiscal documents/SUNAT.
- Tenant selection y auth styling al final, sin cambiar auth architecture.

**Riesgos:** secciones dashboard por permiso; datos financieros; UNKNOWN/SUBMITTED SUNAT; ledger audit readonly.

## Estrategia de navegación rápida propuesta

1. Mantener page code splitting existente (`import.meta.glob` lazy).
2. Cambiar paginación Media de `<a>` a Inertia `Link` en el bloque Media.
3. Considerar `prefetch` solo en links frecuentes de index→create/detail tras medir y confirmar API Inertia 3; excluir pagos/fiscal y no hacer prefetch masivo.
4. Partial reload solo para props explícitamente estables y con prueba de no-staleness de permisos/contexto.
5. Skeletons estructurales para feedback, nunca como sustituto de reducir backend/DB.
6. Debounce/cancelación en search solo cuando se cambie de submit explícito a búsqueda reactiva.

## Pruebas por bloque

- Feature tests de contratos Inertia, filtros, paginación, FormRequests, RBAC y cross-tenant/store.
- Component/type checks para props y responsive manual.
- `php artisan test`, Pint de PHP cambiado, `composer validate`, `npm run typecheck`, `npm run build`, `git diff --check`.
- Lighthouse/DevTools solo con entorno y browser disponibles; registrar hardware/red/dataset.

## Rollout

- Feature slices pequeños y revisables; no commits automáticos.
- Comparación visual con artefactos Stitch reales.
- No cerrar bloque con pantallas pendientes o pruebas fallidas.
