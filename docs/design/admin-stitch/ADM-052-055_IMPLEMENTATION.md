# FASE 26.29 — Returns y Exchanges · ADM-052–055

## Alcance

Implementación Vue completa del módulo administrativo de logística inversa:

- **ADM-052** — `/admin/returns` (`admin.returns.index`).
- **ADM-053** — `/admin/returns/{return}` (`admin.returns.show`).
- **ADM-054** — `/admin/exchanges` (`admin.exchanges.index`).
- **ADM-055** — `/admin/exchanges/{exchange}` (`admin.exchanges.show`).

No existen pantallas Create o Edit independientes. Las devoluciones se crean desde Order; los cambios se crean desde Return Detail. No se utilizó Stitch, no se modificó Laravel y no se añadieron endpoints, modelos, migraciones, integraciones externas ni reglas comerciales. Refunds ADM-056–057 queda fuera del bloque.

## Contrato funcional auditado

- Return y Exchange pertenecen a una Tenant, Store y Order. Todos los lookups HTTP vuelven a limitarse por `TenantContext` y `StoreContext`.
- Return sigue `REQUESTED → APPROVED → RECEIVED → INSPECTING → COMPLETED`; `REQUESTED` puede rechazarse, y `REQUESTED`/`APPROVED` pueden cancelarse.
- La recepción exige todas las líneas, cantidades enteras positivas y no superiores a las solicitadas. La inspección limita aceptado por recibido y por el balance retornable server-side.
- Solo líneas aceptadas con disposición `RESTOCK` pueden reingresar inventario. Cada movimiento `RETURN_RESTOCK` conserva Return Item, correlación, costing y auditoría.
- Completar exige todas las líneas inspeccionadas y todos los saldos aceptados `RESTOCK` efectivamente reingresados.
- Exchange se crea únicamente desde Return `INSPECTING` o `COMPLETED`, con líneas aceptadas y saldo aún no asignado. Sigue `PENDING → APPROVED → COMPLETED`; `PENDING` o `APPROVED` pueden cancelarse.
- La cancelación de Exchange libera su asignación para cálculos posteriores; no crea Shipment, Payment, Refund ni movimiento de inventario automático.

## ADM-052 y ADM-054 — Listados

- Conservan búsqueda real por Order number, customer name o email; filtros `status` y `order_id`; orden descendente; paginación server-side de 20 y `withQueryString()`.
- Consumen únicamente las props existentes con eager loading de Order y `withCount('items')`; no agregan requests, polling, sorting ni filtros client-side.
- Presentan tabla compacta desktop y cards mobile con identificador, Order, Customer, estado traducido, líneas y fecha.
- Distinguen Store vacía de filtros sin coincidencias y mantienen el filtro contextual recibido desde Order.

## ADM-053 — Return workbench

- Presenta estado, cantidades agregadas solo para visualización, Order/Customer, motivos, notas, historial append-only, líneas, Exchanges y Refunds únicamente cuando Laravel autoriza su carga.
- Las acciones approve, reject, receive, complete y cancel se muestran solo por capabilities server-side.
- Inspect y Restock mantienen permisos independientes `returns.inspect` y `returns.restock`; límites HTML reflejan las props, pero Laravel vuelve a validar estado, scope, balances y locks.
- La creación de Exchange permite excluir líneas aceptadas y elegir una variante activa tenant-wide; Laravel conserva autoridad sobre pertenencia y cantidad no asignada.
- Los formularios se deshabilitan durante procesamiento y las acciones terminales requieren confirmación traducida para reducir reenvíos accidentales.

## ADM-055 — Exchange detail

- Presenta el manifiesto devuelto → reemplazo, cantidades, Return y Order relacionados, Customer y fechas del ciclo de vida.
- Approve, Complete y Cancel dependen exclusivamente de capabilities de Laravel y conservan confirmaciones y errores traducidos.
- No existe edición de líneas, fulfillment de reemplazo, reserva, envío o refund automático en el backend; no se añadieron controles ficticios.

## Integridad comercial y seguridad

- Vue no calcula fulfilled/returnable/unallocated ni escribe stock; solo muestra proyecciones entregadas por Laravel.
- Aplicaciones existentes conservan transacciones, `lockForUpdate`, idempotencia de Complete Return, locks de Return/Return Item/Order Item/Inventory Level y ledger de costing.
- No se alteraron Orders, Payments, Inventory, Fulfillment, Refunds ni sus snapshots. No se duplicaron operaciones Shipment.
- Las rutas permanecen bajo auth, verificación, CSRF, middleware `tenant`/`store` y permisos `returns.view/manage/inspect/restock` o `exchanges.view/manage`.
- No se envían `tenant_id` ni `store_id`; recursos de otra Tenant o Store continúan resolviendo `404` y permisos ausentes `403`.

## Internacionalización y responsive

- Se añadieron catálogos `returns.*` y `exchanges.*` completos en inglés y es-PE predeterminado: estados, condiciones, disposiciones, acciones, confirmaciones, errores conocidos, vacíos y mensajes de éxito.
- Números de Order, SKU, UUID, nombres, motivos y notas almacenados no se traducen.
- Desktop usa tablas y workbenches compactos. Mobile 390 px usa cards, formularios apilados, controles mínimos de 40 px y manifiestos sin ancho mínimo ni scroll horizontal.
- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice`.

## Rendimiento y omisiones deliberadas

- Se preservan queries, eager loading, agregados y props Inertia existentes. No hay dependencias ni requests auxiliares.
- Sin backend, dominio, migraciones, datos de desarrollo, Supabase, Stitch, transportistas, etiquetas, automatización de Refunds ni otro módulo.
- Los bundles y resultados de validación se registran en el informe final de FASE 26.29.
