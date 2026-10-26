# ADM-013 — Products Index implementation

Fecha: 2026-10-25. Fase: 26.8.

## Referencias

- Proyecto Stitch: `projects/13874730587997489426`.
- Design System: Executive Precision B2B.
- Desktop: `3a6c7377559d4d389795af2eee4f1c7d`.
- Mobile: `40e90b8a58b74ca599f933a4c2f10924`.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

Stitch se utilizó como referencia visual. Laravel y los tests existentes se
mantuvieron como fuente de verdad funcional.

## Contrato funcional verificado

### Alcance y autorización

- Product, ProductVariant, Category y sus relaciones son Tenant-wide. Products
  Index requiere TenantContext y no StoreContext.
- El controller obtiene Products mediante la relación del Tenant autorizado;
  no acepta `tenant_id` del request.
- La página requiere `products.view` mediante `CatalogAccess`.
- Create requiere `products.create`, Edit/Update `products.update` y Archive
  `products.archive`. El enlace de detalle usa la ruta Edit existente; muestra
  `View` cuando el usuario solo tiene permiso de lectura.
- Los lookups Edit/Update/Archive vuelven a resolver el Product dentro del
  Tenant. Acceso cross-tenant retorna 404.

### Consulta y payload

- Orden: `created_at DESC` mediante `latest()`.
- Paginación server-side: 15 filas, conservando query string.
- Búsqueda opcional única por Product name o SKU de cualquier Variant, con
  comparación PostgreSQL `ILIKE`.
- Eager loading: default Variant (`id`, `product_id`, `sku`) y Categories
  (`id`, `name`), más `withCount('variants')`.
- Props por fila: `id`, `name`, `slug`, `status`, `default_sku` nullable,
  `variants_count` y nombres de Categories.
- Estados reales: `DRAFT`, `ACTIVE`, `ARCHIVED`.
- Archive es lógico, idempotente, auditado y preserva Variants. La pantalla usa
  la confirmación existente y no repite afirmaciones sintéticas de Stitch sobre
  Listings o Variants.

No existen props de media en el Index. Aunque Catalog dispone de ProductMedia y
derivados privados de 300 px, incluir thumbnails aquí requeriría ampliar el
payload, eager loading y gating `media.view`. No se hizo sin evidencia de que el
índice deba pagar ese coste.

## Implementación visual

### Componentes reutilizados

- `AdminLayout`, incluido su drawer mobile y navegación RBAC.
- `Breadcrumbs`.
- `PageHeader`.
- `StatusBadge`.
- `StatusNotice` para el flash real posterior a Archive.

### Componentes mínimos creados

- `EmptyState`: estado vacío compacto con icono y acción opcionales.
- `Pagination`: enlaces Inertia reales, estado activo/deshabilitado, resumen
  `from–to of total` y presentación mobile reducida a Previous, página activa y
  Next. El componente completo se oculta cuando Previous y Next no tienen URL,
  es decir, cuando solo existe una página.

No se creó ProductThumbnail porque el prop no existe. Tampoco se extrajeron
DataTable, ResponsiveDataList, SearchInput o FilterControls: solo una pantalla
los usaría y sus contratos aún no están suficientemente estabilizados.

### Desktop

- Breadcrumb `Catalog / Products`, scope explícito `All company` y descripción
  Tenant-wide para evitar atribuir el catálogo a la Store activa del shell.
- Create solo con `products.create`.
- Search por name/SKU; Clear elimina el único query param soportado.
- Tabla compacta de seis columnas con anchos estables, nombres/slugs truncados,
  SKU técnico, count de Variants, Categories, StatusBadge y acciones RBAC.
- Sin thumbnail, precios, inventario, Store listings, bulk actions, filtros de
  estado, sort UI o exportación.

### Mobile 390

- Mismo drawer aprobado; sin navegación inferior.
- Search y acciones mantienen targets mínimos de 36 px.
- Products se presentan como cards con nombre, slug, Status, default SKU,
  Variant count, Categories y acciones autorizadas.
- No se renderiza la tabla mobile y no hay ancho mínimo ni scroll horizontal en
  el contenido ADM-013.
- Pagination reduce los números intermedios en mobile; desktop conserva todos
  los links generados por Laravel.

## Estados

- Search y Archive muestran estado processing y deshabilitan el control
  correspondiente mientras Inertia navega.
- Catálogo vacío: `No products yet`, con Create si está autorizado.
- Búsqueda sin resultados: mensaje específico y acción real Clear.
- Flash de Archive reutiliza `status` compartido.
- No hay un contrato de error específico del Index; errores HTTP/CSRF siguen el
  manejo global de Laravel/Inertia y no se simuló un error comercial.

## Rendimiento

- No cambió el controller ni el payload.
- Continúan la paginación de 15, eager loading selectivo y `withCount`; no hay
  carga frontend de todo el catálogo ni requests adicionales.
- Se añadió una regresión que compara el query count del Index con 2 y 12
  Products; el conteo permanece constante.
- Bundle de Products Index antes: 3.66 kB (1.63 kB gzip).
- Bundle después: 10.72 kB (3.67 kB gzip). El incremento incluye la tabla/cards,
  estados, Pagination y EmptyState; no se añadieron dependencias ni imágenes.
- No se afirma una mejora porcentual de tiempo porque no existe baseline de
  navegación autenticada o medición de red.

## Pruebas

- `npm run typecheck`: correcto.
- `npm run build`: correcto; permanece el aviso no bloqueante del paquete
  opcional `fontaine`, no instalado.
- `tests/Feature/Catalog`: 34 tests, 176 assertions, correctos sobre
  `pgsql.testing/commerce_testing` en FASE 26.8. Tras FASE 26.9: 40 tests,
  216 assertions.
- Cobertura incluida: búsqueda, paginación de 15, cambio y aislamiento de
  Tenant, RBAC Member, 404 cross-tenant, Archive lógico, Categories/Variants y
  query count estable. FASE 26.9 añadió confirmación explícita de que los nombres
  de Categories asignadas llegan en `products.data.*.categories`.
- `git diff --check`: correcto.

## Diferencias frente a Stitch

- Sin thumbnails: no están en el payload real.
- Sin filtro de Status: el backend solo soporta `search`.
- Sin modal descriptivo de Archive: sus afirmaciones sobre Variants/Listings no
  corresponden al action real; se preserva `window.confirm`.
- Sin totales o páginas sintéticas: Pagination usa metadata Laravel real.
- Sin datos, SKUs o categorías de ejemplo: todo proviene de Inertia.

## Pendientes

No hay navegador autenticado disponible en el harness. Continúa pendiente la
revisión visual manual a 1280, 1440 y 390 px, zoom 100 %, incluyendo foco,
drawer, búsquedas largas, múltiples Categories, nombres extensos, empty states
y paginación de muchas páginas.

FASE 26.9 incorporó el selector compartido de Categories en Create Product y
Edit Product. ADM-001 y las demás pantallas no fueron modificadas.
