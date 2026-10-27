# FASE 26.31 — Procurement · ADM-058–063

## Alcance

- **ADM-058:** Suppliers Index (`/admin/suppliers`).
- **ADM-059:** Create Supplier (`/admin/suppliers/create`).
- **ADM-060:** Edit Supplier (`/admin/suppliers/{supplier}/edit`).
- **ADM-061:** Purchase Orders Index (`/admin/purchase-orders`).
- **ADM-062:** Create Purchase Order (`/admin/purchase-orders/create`).
- **ADM-063:** Purchase Order Detail (`/admin/purchase-orders/{purchaseOrder}`). Purchase Receipts se administran dentro de este workbench; su GET solo redirige aquí.

No se modificaron backend, rutas, dominio, modelos, migraciones ni reglas comerciales. No se generaron diseños Stitch.

## Contratos funcionales

Suppliers es tenant-wide. El índice busca por nombre, código, correo o teléfono, filtra por estado y pagina 20. Create admite exclusivamente `name`, `code`, `contact_name`, `email`, `phone`, `website` y `notes`; Laravel normaliza código y campos opcionales. Edit agrega estado ACTIVE/INACTIVE, Archive lógico y asociaciones con variantes activas no configuradas. Cada asociación conserva SKU del proveedor, costo decimal escala 4, moneda ISO, MOQ, múltiplo, lead time, preferencia, notas y estado.

Purchase Orders también es tenant-wide e independiente de Store. El índice busca por número, proveedor, SKU o SKU del proveedor, filtra por proveedor/estado y presenta avance recibido calculado en batch. Create forma un DRAFT transaccional con proveedor ACTIVE, moneda, fecha prevista, notas y hasta 250 líneas distintas.

ADM-063 expone las operaciones reales:

- editar cabecera y líneas únicamente en DRAFT;
- `DRAFT → APPROVED → ORDERED`;
- cancelar DRAFT/APPROVED/ORDERED solo sin receipts POSTED;
- crear receipts DRAFT para ORDERED/PARTIALLY_RECEIVED;
- editar cabecera y líneas, agregar/quitar líneas y cancelar mientras el receipt sea DRAFT;
- postear el receipt para registrar inventario de forma atómica e idempotente.

No existen eliminación de Supplier/PO, reapertura, borrado de receipts posteados ni recepción automática.

## Seguridad e integridad

- Sesión Laravel, email verificado, CSRF y middleware `tenant` permanecen vigentes.
- `suppliers.view/create/update/archive` y `procurement.view/manage/approve/receive` controlan rutas y capabilities; la UI solo refleja esa autoridad.
- Todos los lookups y agregados se limitan a `TenantContext`. Cambiar Store no cambia Procurement y cambiar Tenant sí cambia la colección.
- Costos e importes permanecen strings decimales; Vue solo los formatea visualmente y no calcula totales.
- Al aprobar, las líneas preservan snapshots de producto, SKU, supplier SKU, moneda y costo. Cambios posteriores en Supplier Product no alteran históricos.
- Posting bloquea y valida cantidades, evita over-receipt, crea movimientos `PURCHASE_RECEIPT`, actualiza costo promedio y estado de PO dentro de transacciones. Un segundo post no duplica inventario.

## UI, i18n y responsive

- Executive Precision B2B mediante `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice`.
- Tablas compactas en desktop; cards y formularios apilados en mobile 390 px, sin tablas ni anchos mínimos en mobile.
- es-PE predeterminado y en alternativo mediante `procurement.*`; incluye estados, vacíos, acciones, errores, confirmaciones y etiquetas accesibles.
- Formularios deshabilitan doble envío; Archive, remove, approve, mark ordered, cancel y post solicitan confirmación.

## Rendimiento e integración

- Se conserva paginación server-side de 20 y eager loading existente.
- El progreso de receipts del índice continúa agregado en dos consultas, sin N+1.
- No se añadieron requests, polling, dependencias ni aritmética monetaria cliente.
- Integraciones preservadas: Catalog Variants, Inventory Locations/Movements, Costing, Notifications, Audit y métricas Analytics.

## Omisiones deliberadas

- Sin endpoints, campos, permisos, eliminación física, exportación, bulk actions ni filtros inventados.
- Sin StoreContext, Supabase, migraciones, Stitch, backend o cambios de reglas comerciales.
- Sin contacto con servicios externos.
