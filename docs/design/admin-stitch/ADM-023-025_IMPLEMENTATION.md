# ADM-023–025 — Pricing: implementación FASE 26.20

## Alcance

Rediseño completo e internacionalización de las tres pantallas administrativas reales del módulo Pricing, sin modificar backend, rutas, reglas comerciales ni persistencia:

| ID | Pantalla | Ruta |
|---|---|---|
| ADM-023 | Price lists | `/admin/pricing` |
| ADM-024 | Create price list | `/admin/pricing/price-lists/create` |
| ADM-025 | Edit price list + variant prices | `/admin/pricing/price-lists/{priceList}/edit` |

No existen referencias Stitch para ADM-023–025. La implementación aplica directamente Executive Precision B2B y los componentes compartidos al contrato Laravel auditado. No se inició Promotions.

## Contrato verificado

| Punto | Contrato preservado |
|---|---|
| Scope | Pricing es tenant-wide. Usa `TenantContext`; cambiar Store no cambia las listas. Toda lectura/mutación se limita al Tenant activo. |
| RBAC | `pricing.view` abre Index/Edit; `pricing.create` protege Create/Store; `pricing.update` protege configuración, default y set/remove de precios; `pricing.archive` protege Archive. |
| Price list | `name` (120), `code` estable y tenant-unique (40), `currency` configurada, estado `ACTIVE/INACTIVE/ARCHIVED`, `is_default` y count de precios. |
| Create | Crea una lista `ACTIVE`, no default. No acepta estado/default ni autoridad `tenant_id` del frontend. |
| Update | Modifica name, currency y `ACTIVE/INACTIVE`; code no cambia. Currency queda bloqueada después de asignar precios. La default debe seguir activa. |
| Default | Endpoint independiente, transaccional; solo una lista `ACTIVE` puede ser default. |
| Archive | Operación lógica independiente. La default no puede archivarse. No existe DELETE de PriceList ni reactivación de archivadas. |
| Variant prices | Un precio por `ProductVariant` + PriceList, string decimal exacto con scale 4; cero permitido, 15 enteros máximo y hasta 4 decimales de entrada. Set y remove son endpoints separados. |
| Archived | Configuración y precios permanecen preservados y readonly. |
| Integración | Listings resuelve PriceList en cascada Listing override → Store default → Tenant default; solo listas activas son utilizables. Promotions calcula previews sin mutar precios. |

## Implementación visual y funcional

### ADM-023 — listado

- `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `StatusNotice`, `Pagination`, `EmptyState` y `FieldError` reutilizados.
- Tabla compacta desktop y cards mobile con name, stable code, currency, estado, default y cantidad real de precios.
- Paginación server-side existente de 20 elementos; no se inventaron búsqueda ni filtros.
- Edit/View, Make default y Archive condicionados por permiso y estado, con processing, confirmaciones y errores traducidos.
- Empty state traducido y CTA condicionado por `pricing.create`.

### ADM-024 — creación

- Únicos campos reales: name, code y currency.
- Code visible como identificador permanente; validación accesible y errores localizados.
- Estado inicial ACTIVE y condición no-default explicados sin agregar inputs inexistentes.
- Submit único con protección de doble envío y redirect real a ADM-025.

### ADM-025 — edición y precios

- Encabezado con status, stable code, currency y default reales.
- Configuración separada de lifecycle. Currency se deshabilita visualmente cuando `prices_count > 0`; default no permite seleccionar INACTIVE.
- Make default y Archive mantienen endpoints, permisos, confirmaciones y errores independientes.
- Archived y usuario sin `pricing.update` muestran modo explícito de solo lectura.
- Búsqueda real por Product name/SKU y paginación server-side de 20 variants.
- Tabla desktop y cards mobile por `ProductVariant`, incluyendo Product, SKU/name, estado de catálogo y amount actual.
- Set/remove por fila, processing sin doble envío, confirmación de remove y error asociado a la fila activa.
- Los amounts nunca se convierten a `Number`: se envían como strings y se presentan agrupando solo la parte textual, preservando scale-4 y evitando pérdida de precisión.

## Responsive, i18n y accesibilidad

- Desktop usa superficies blancas, bordes slate, controles densos y tablas sin columnas no suministradas.
- Mobile 390 px usa cards, formularios de una columna, acciones de ancho accesible y ninguna tabla/scroll horizontal ni barra inferior.
- Todos los textos nuevos están en `pricing.*` para `es-PE` y `en`: títulos, ayudas, estados mediante `StatusBadge`, acciones, processing, errores, confirmaciones, vacíos y labels accesibles.
- Labels vinculados, `aria-invalid`, `aria-describedby`, regiones de status/error y búsqueda con role real.

## Seguridad, rendimiento e integridad

- Laravel sigue siendo autoritativo para Auth, CSRF, RBAC, tenant scoping y transiciones.
- No se añadió `tenant_id`, StoreContext, endpoint, request, dependencia ni consulta.
- Se conservan eager loading de Product/precio, `withCount`, búsqueda SQL y paginación de 20; no se introdujeron requests auxiliares ni N+1.
- No se alteraron PriceResolver, ListingPriceResolver, Promotions, impuestos, redondeos, órdenes o snapshots históricos.
- Bundles de producción: Index **8.11 kB / 2.67 kB gzip**, Create **6.42 kB / 2.15 kB gzip**, Edit **21.67 kB / 5.76 kB gzip**, más chunks compartidos.

## Validación

- Guard aislado: 1 test, 4 assertions, PASS (`pgsql.testing / commerce_testing`).
- Pricing: 27 tests, 142 assertions, PASS.
- ListingPricing + EffectivePrice: 13 tests, 57 assertions, PASS.
- Access/RBAC: 18 tests, 85 assertions, PASS.
- Suite Laravel completa: 636 tests, 4,429 assertions, PASS.
- `npm run test:i18n`: 692 mensajes Vue por locale, 45 labels de sidebar y 43 mensajes Laravel, PASS.
- `npm run typecheck`, `npm run build` y `git diff --check`: PASS.
- Build emitió únicamente el warning preexistente de `fontaine` opcional; no se añadió la dependencia.

## Limitaciones y trabajo deliberadamente omitido

- Pendiente revisión visual manual en navegador desktop y viewport 390 px.
- No se generaron pantallas Stitch nuevas ni se modificaron diseños aprobados anteriores.
- No se añadieron DELETE de listas, reactivación de archivadas, edición masiva, precio global por Product, filtros, exportación, Promotions ni cambios de dominio.
