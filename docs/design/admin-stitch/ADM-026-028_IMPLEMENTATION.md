# ADM-026–028 — Promotions: implementación FASE 26.21

## Alcance

Rediseño e internacionalización completa de las pantallas administrativas reales de Promotions, sin modificar backend, endpoints, modelos ni cálculos:

| ID | Pantalla | Ruta |
|---|---|---|
| ADM-026 | Promotions | `/admin/promotions` |
| ADM-027 | Create promotion | `/admin/promotions/create` |
| ADM-028 | Edit promotion + preview | `/admin/promotions/{promotion}/edit` |

No existen referencias Stitch para ADM-026–028 y no se generaron diseños. La implementación aplica Executive Precision B2B directamente al contrato Laravel auditado.

## Contrato funcional verificado

- **Scope:** tenant-wide mediante `TenantContext`; cambiar Store no altera Promotions. Todas las promociones, targets, variantes de preview y listas de precios se resuelven dentro del Tenant activo.
- **RBAC:** `promotions.view`, `promotions.create`, `promotions.update` y `promotions.archive` protegen capacidades independientes. Un Member puede ver Index/Edit en modo readonly.
- **Estados:** `DRAFT`, `ACTIVE`, `INACTIVE` y `ARCHIVED`. Create/Update solo aceptan los tres primeros; Archive es lógico, independiente e irreversible.
- **Descuentos:** `PERCENTAGE` o `FIXED_AMOUNT`; valor decimal positivo con hasta 15 enteros y escala 4. Porcentaje máximo 100. Fixed requiere moneda configurada y solo coincide con PriceList de moneda exacta.
- **Vigencia:** fechas UTC opcionales y elegibilidad en `[starts_at, ends_at)`, con fin exclusivo.
- **Resolución:** no hay stacking. Gana prioridad mayor; luego descuento efectivo mayor; luego UUID ascendente. El importe final tiene piso cero y nunca muta `variant_prices`.
- **Alcance:** `ALL` o `SELECTED`. Selected es unión OR de Products, categorías exactas y Variants; no incluye descendientes de categoría. Máximo 100 IDs por tipo al guardar.
- **Preview:** Laravel calcula sobre configuración guardada, Variant y PriceList tenant-scoped. Devuelve base, descuento total, final y promoción ganadora; null si no existe precio base.
- **Ausencias deliberadas:** no hay cupones, uso máximo, segmentación, reglas de Store, stacking avanzado, duplicación, acciones masivas, DELETE físico ni reactivación.

## Implementación

### ADM-026 — Index

- Tabla compacta desktop de siete columnas y cards mobile sin scroll horizontal.
- Nombre/código, descuento, estado persistido, elegibilidad actual, ventana UTC, prioridad y scope/targets reales.
- Búsqueda backend por name/internal code, paginación server-side de 20 y vacíos diferenciados.
- Edit/View y Archive según RBAC y estado; archivo con confirmación traducida, processing y preservación de targets.

### ADM-027 — Create

- Formulario compartido con todos y solo los campos reales: identidad, status, tipo/valor/moneda, vigencia, prioridad, scope y targets.
- Búsqueda backend de targets con máximo 50 resultados por Products/Categories/Variants; el estado del formulario se conserva durante la navegación Inertia.
- Validación accesible, errores localizados y prevención de doble envío. Redirect real a Edit tras crear.

### ADM-028 — Edit

- Datos y targets precargados, update único y modo readonly para Archived o ausencia de `promotions.update`.
- Archive independiente bajo `promotions.archive`; no se presenta reactivación ni eliminación.
- Preview server-side independiente con Variant y PriceList del Tenant. Se explica que usa la configuración guardada y no cambios sin guardar.
- Mensajes de éxito, errores conocidos, confirmación y estados de búsqueda/cálculo/guardado.

## Diseño, i18n y accesibilidad

- Reutiliza `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `StatusNotice`, `Pagination`, `EmptyState` y `FieldError`.
- Desktop mantiene densidad B2B, superficies blancas, slate/azul y montos tabulares. Mobile 390 px usa cards y formularios de una columna, drawer existente y ninguna navegación inferior.
- Catálogo `promotions.*` completo y paritario para es-PE/en: títulos, campos, estados, reglas, ayudas, acciones, vacíos, errores, confirmaciones y labels accesibles.
- Labels asociados, required real, `aria-invalid`, ayudas y regiones de status/error. Los códigos, monedas, SKU y valores internos permanecen sin traducir.

## Seguridad, integridad y rendimiento

- Auth de sesión, CSRF, FormRequests, `PromotionAccess`, transacciones y queries tenant-scoped permanecen autoritativos.
- No se añadió `tenant_id` o `store_id` al frontend ni se modificó `TenantContext`, Pricing, Listings, Cart, Checkout, impuestos u Orders.
- Se conserva paginación 20, `withCount`, carga acotada de targets y cálculo server-side. No se añadieron requests auxiliares, N+1 ni dependencias.
- Bundles de producción: Index **9.83 kB / 3.28 kB gzip**; Create **4.27 kB / 1.79 kB gzip**; Edit **10.31 kB / 3.25 kB gzip**; formulario compartido **14.48 kB / 3.70 kB gzip**.

## Validación

- Guard aislado `pgsql.testing / commerce_testing`: 1 test, 4 assertions, PASS.
- Promotions: 21 tests, 121 assertions, PASS.
- Pricing/Listings/Cart/Checkout/Orders/Access/Tenancy/Tax integration: 143 tests, 941 assertions, PASS.
- Suite Laravel: 635 PASS y 1 fallo no relacionado por orden no determinista en `PurchaseReceiptCostingTest`; el método fallido pasó aislado inmediatamente (1 test, 3 assertions). No se modificó Costing.
- `npm run test:i18n`: 808 mensajes Vue por locale, 45 labels de sidebar y 43 mensajes Laravel, PASS.
- `npm run typecheck`, `npm run build` y `git diff --check`: PASS. Build conserva únicamente el warning preexistente de `fontaine` opcional.

## Pendientes y omisiones

- Pendiente validación visual manual en navegador desktop y viewport 390 px.
- No se generó Stitch ni se modificaron pantallas previamente aprobadas.
- No se añadieron tests frontend, porque el repositorio no tiene runner de componentes configurado; contratos HTTP/dominio quedan cubiertos por la suite existente.
- No se corrigió el test flaky de Costing por estar fuera de alcance y pasar aislado.
