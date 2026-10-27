# ADM-020 — Listings Index: implementación FASE 26.18

## Alcance

Rediseño e internacionalización de `/admin/listings` sobre el contrato Laravel existente. La pantalla administra publicaciones de la Store activa, su price list predeterminada y las transiciones de lifecycle disponibles. No se modificaron backend, rutas, permisos ni reglas comerciales.

ADM-020 cuenta con referencias Stitch recuperables: desktop `84a1d011736549e699fd19c66c68837e` y mobile `12e94170e33e439092b7222f6fd8d499`. Ninguna está listada o incorporada al canvas; la implementación Vue conserva el contrato real y omite navegación o datos no respaldados.

## Contrato verificado

| Punto | Contrato preservado |
|---|---|
| Scope | Tenant autorizado y Store activa mediante `TenantContext` y `StoreContext`; la consulta parte de la relación `store->listings()` y vuelve a limitar por Tenant. |
| Lectura | `listings.view`; 20 filas por página, orden descendente de creación y query string preservado. |
| Búsqueda | Nombre de Product, `storefront_slug` o SKU de Variant. No existen filtros de estado, ordenamiento manual ni acciones masivas. |
| Price list efectiva | Override del Listing; si no existe, fallback Store → default del Tenant. Solo se presenta una lista `ACTIVE`. |
| Configuración Store | `listings.update`; el default opcional se limita a price lists activas del Tenant. |
| Create | Acción visible únicamente con `listings.create`. |
| Publish / Unpublish | `listings.publish`; validaciones de Store, Product, variants y precios permanecen server-side. |
| Archive | `listings.archive`; confirmación previa, transición separada y sin endpoint DELETE. |
| Datos | Product, estado, slug, variants habilitadas, price list efectiva y fecha de publicación. No se inventan amounts, URL pública, analytics ni estado de sincronización. |

## Implementación

- Reutiliza `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `StatusNotice`, `Pagination`, `EmptyState` y `FieldError`.
- Expone claramente la Store activa y la configuración de default price list sin confundirla con el default tenant-wide.
- Implementa búsqueda Inertia real con `preserveState`, reemplazo de historial, estado de procesamiento y limpieza.
- Muestra tabla compacta en desktop y cards en mobile con los mismos datos y acciones.
- Presenta Create, Edit/View, Publish, Unpublish y Archive según permiso y estado de dominio.
- Bloquea envíos duplicados de lifecycle mediante el estado `processing` y conserva errores operativos traducidos.
- Mantiene nombres de Product, slugs, currencies y price lists como datos persistidos, sin traducción artificial.

## Responsive y accesibilidad

- Desktop: tabla de seis columnas, cantidades tabulares y acciones condicionadas sin scroll innecesario.
- Mobile 390 px: cards con estado, variants, price list, fecha y acciones; sin navegación inferior.
- Búsqueda con `role="search"`, label asociado, botones nativos y errores con `role="alert"`.
- Estados vacíos diferenciados para catálogo vacío y búsqueda sin resultados.

## Seguridad y rendimiento

- Vue solo condiciona visibilidad; Laravel mantiene autorización efectiva en `ListingAccess`, middleware y Form Requests.
- Los IDs de Tenant y Store no se envían desde el formulario ni se adoptan como autoridad.
- Las mutaciones resuelven el Listing desde la relación de la Store activa y el Tenant autorizado.
- La consulta conserva eager loading, conteo de variants habilitadas y paginación server-side de 20.
- No se añadieron dependencias, endpoints, consultas de storefront ni cálculos monetarios client-side.

## Validación

- FASE 26.39: guard aislado PASS; suite Laravel completa **636 tests / 4429 assertions**, PASS.
- FASE 26.39: `npm run test:i18n`, `npm run typecheck`, `npm run build`, ambos `npm audit` y `git diff --check`, PASS.
- La cobertura relevante de Listings incluye RBAC, aislamiento cross-tenant/cross-store, búsqueda, fallback de price list y lifecycle.

## Cobertura y pendientes

- Cobertura Vue de Listings: **3/3** (`ADM-020`–`ADM-022`).
- Cobertura Vue administrativa: **69/69**.
- Cobertura Stitch real administrativa: **9/69**; ADM-020 es una de las nueve pantallas representadas.
- Las referencias Stitch de ADM-020 continúan recuperables pero no incorporadas al canvas. No se regeneraron ni modificaron.
