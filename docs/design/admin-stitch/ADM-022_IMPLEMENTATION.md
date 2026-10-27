# ADM-022 — Edit Listing: implementación FASE 26.19

## Alcance

Rediseño e internacionalización de `/admin/listings/{listing}/edit`, incluyendo lifecycle existente. No se modificaron backend, reglas comerciales, rutas ni autorizaciones. No existe referencia Stitch para ADM-022; se aplicó Executive Precision B2B directamente al contrato Laravel auditado.

## Contrato verificado

| Punto | Contrato preservado |
|---|---|
| Lectura | `listings.view`; resolución estricta por Tenant + Store activa. URLs cross-store y cross-tenant devuelven 404. |
| Update | `listings.update`; un request guarda slug requerido, override nullable y el array presente `enabled_variant_ids`. |
| Variants | Solo variants del Product; una variant de catálogo no `ACTIVE` no puede habilitarse. Variants nuevas se configuran al guardar. |
| Search/page | Búsqueda real por SKU/nombre; 20 por página, default primero y luego SKU. |
| Preview | Base, descuento, precio final y promociones se calculan server-side; son readonly y no mutan Pricing/Promotions. |
| Publish | `listings.publish`; requiere Store/Product activos, una variant activa habilitada, price list efectiva activa y precio para cada SKU habilitado. |
| Unpublish | Mismo permiso independiente `listings.publish`; cambia a `UNPUBLISHED`. |
| Archive | `listings.archive`; confirmación previa, transición separada y redirect al Index. No existe DELETE. |
| Archived | Configuración y variants permanentemente readonly; no se presenta una acción de reactivación. |

## Implementación

- `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `StatusNotice`, `Pagination`, `EmptyState` y `FieldError` reutilizados.
- Encabezado con Store, estado real, slug y primera fecha de publicación localizada.
- Panel de lifecycle independiente con Publish, Unpublish y Archive según permiso y estado.
- Avisos diferenciados para Archived y acceso view-only.
- Configuración real de slug y price-list override, incluyendo lista efectiva actual.
- Búsqueda Inertia y paginación server-side de variants. `Pagination` añadió `preserveState` opcional; su valor predeterminado no cambia y Edit lo activa para conservar selecciones locales al navegar.
- Tabla desktop y cards mobile con enabled state, SKU/nombre, estado de catálogo, base, descuento/promoción y precio efectivo.
- Errores de Update y lifecycle traducidos, incluido el listado dinámico de SKU sin precio.

## Responsive y accesibilidad

- Desktop: cards blancas, bordes slate, tabla compacta sin columnas inventadas y acciones separadas.
- Mobile 390 px: variants en cards, precios en grilla de tres columnas y acciones apilables; sin scroll horizontal.
- Checkboxes nativos con labels accesibles por SKU, estados readonly visibles y búsqueda etiquetada.
- Sin navegación inferior ni cambios al shell.

## Seguridad y rendimiento

- La UI condiciona acciones por `listings.update`, `listings.publish` y `listings.archive`; Laravel sigue siendo autoritativo.
- No se añadieron endpoints ni requests. Se conservan eager loading, batch preview con `calculateMany`, paginación 20 y consultas scoped.
- La navegación de búsqueda/paginación preserva el formulario local sin solicitar datos adicionales.
- Chunk de producción: `Edit-C_U5qptI.js`, **20.07 kB** (**5.64 kB gzip**), además de chunks compartidos.

## Validación

- Guard aislado: 1 test, 4 assertions, PASS.
- Suite Listings: 25 tests, 185 assertions, PASS.
- Cobertura relevante: update de variants agregadas posteriormente, preview de promociones sin mutación, fallback Listing → Store → Tenant, lifecycle, validaciones de publicación, RBAC y aislamiento cross-store/cross-tenant.
- `npm run test:i18n`: 607 mensajes Vue por locale, PASS.
- `npm run typecheck`, `npm run build` y `git diff --check`: PASS.

## Pendiente

- Revisión visual manual en navegador desktop y viewport 390 px.
- No se implementaron edición de precios/promociones, sincronización, DELETE, storefront ni acciones sin endpoint.
