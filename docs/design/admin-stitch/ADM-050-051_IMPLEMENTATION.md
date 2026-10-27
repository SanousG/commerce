# FASE 26.28 — Fulfillment · ADM-050 y ADM-051

## Alcance

Implementación Vue completa del módulo administrativo Fulfillment:

- **ADM-050** — `/admin/fulfillments` (`admin.fulfillments.index`).
- **ADM-051** — `/admin/fulfillments/{fulfillment}` (`admin.fulfillments.show`).

No se utilizó Stitch, no se modificó Laravel y no se añadieron endpoints, modelos, migraciones, transportistas, etiquetas ni reglas comerciales. El rediseño reutiliza **Executive Precision B2B** y los componentes administrativos compartidos.

## Contrato funcional auditado

- Un Fulfillment pertenece a un Order confirmado, una Tenant, una Store y una Inventory Location.
- Cada línea referencia exactamente un Order Item y una Inventory Reservation de Checkout. La creación ocurre desde Order, admite fulfillment parcial y valida ubicación activa, reserva activa/no expirada, correspondencia de variante y cantidades aún no asignadas.
- Estados de Fulfillment: `PENDING → PROCESSING → FULFILLED`; `PENDING` o `PROCESSING` pueden pasar a `CANCELLED`. Completar requiere elegibilidad de pago y Order confirmado.
- Un Order `SHIPPING` admite un único Shipment por Fulfillment. Estados: `PENDING → READY → SHIPPED → DELIVERED`; `PENDING` o `READY` pueden pasar a `CANCELLED`. Solo se puede marcar `SHIPPED` cuando el Fulfillment está `FULFILLED`.
- No existe pantalla standalone de Shipment. Laravel solo soporta datos manuales opcionales de transportista, servicio, número y URL de seguimiento.
- Historiales de Fulfillment y Shipment son append-only. Fulfillment no completa el Order ni modifica pagos, impuestos o snapshots comerciales.

## ADM-050 — Listado

- Conserva filtros reales `search`, `status` y `order_id`; la búsqueda solo consulta el número de Order.
- Conserva paginación server-side de 20, orden descendente por creación, eager loading de Order/Location/Shipment y agregados SQL de líneas/unidades.
- Presenta tabla compacta desktop y cards mobile con Order, ubicación, estado, trabajo asignado, Shipment y fecha.
- Diferencia Store vacía de filtros sin coincidencias y conserva la navegación contextual `order_id` desde Order.
- No agrega filtros, ordenamiento, acciones masivas ni datos logísticos que el controlador no entregue.

## ADM-051 — Workbench

- Presenta resumen operativo, vínculo al Order, Location, elegibilidad de pago, pick list, costos solo con `inventory.cost.view`, reservas, fechas e historiales disponibles.
- Las acciones de Fulfillment se muestran exclusivamente con capabilities server-side: iniciar, completar y cancelar.
- El Shipment permite crear datos manuales opcionales y ejecutar únicamente las transiciones autorizadas por Laravel: listo, enviado, entregado o cancelado.
- Formularios y acciones se bloquean durante procesamiento para reducir reenvíos accidentales; Laravel conserva la autoridad ante solicitudes repetidas o manipuladas.
- No se muestran dirección de entrega ni datos del cliente porque el contrato Inertia de Fulfillment no los expone. El Order vinculado sigue siendo la superficie autorizada para esos datos.

## Integridad de Orders e Inventory

- Vue no calcula disponibilidad ni cantidades despachables y no escribe stock.
- `CompleteFulfillment` conserva transacción y orden de locks sobre Order, Fulfillment, reservas y niveles; revalida estado/cantidad y crea movimientos `RESERVATION_COMMIT` correlacionados con el Fulfillment.
- `InventoryReservation.quantity` permanece original; `committed_quantity` acumula commits. Fulfillments cancelados no consumen capacidad de asignación.
- Solo líneas de Fulfillments `FULFILLED` contribuyen al estado de fulfillment del Order.
- Returns y movimientos inversos no se modificaron; no se alteraron costos, pagos, impuestos ni snapshots.

## Seguridad y aislamiento

- Las lecturas mixtas exigen `fulfillments.view` + `shipments.view`.
- Las operaciones usan `fulfillments.manage`, `fulfillments.complete` o `shipments.manage` según el endpoint.
- Todas las rutas permanecen bajo autenticación, CSRF, middleware `tenant` y `store`; los controladores vuelven a limitar recursos mediante `TenantContext` y `StoreContext`.
- Vue no recibe ni envía `tenant_id` o `store_id`. Recursos de otra Tenant o Store continúan devolviendo `404`; un rol sin permiso conserva `403`.

## Internacionalización y responsive

- Se añadió el catálogo `fulfillment.*` completo en inglés y es-PE predeterminado: listados, estados, acciones, confirmaciones, formularios, errores conocidos, fechas y estados vacíos.
- Números de pedido, SKU, referencias, UUID, transportistas y tracking almacenados no se traducen.
- Desktop usa tablas compactas y paneles operativos; mobile 390 px usa cards, formularios apilados y acciones de ancho accesible sin tabla ni overflow horizontal.
- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice`.

## Rendimiento

- No se alteraron queries ni props Inertia y no se añadieron requests auxiliares, polling o dependencias.
- El listado conserva eager loading, agregados y paginación existentes; el detalle consume exclusivamente la proyección entregada por Laravel.
- El tamaño final de bundles y resultados de validación se registran en el informe de entrega de FASE 26.28.

## Omisiones deliberadas

- Sin backend, dominio, migraciones, datos de desarrollo, Supabase, Stitch ni dependencias.
- Sin carrier adapters, tarifas, paquetes, etiquetas, webhooks, tracking events o edición posterior de Shipment.
- Sin nuevas pantallas de Shipping, Returns, Refunds, storefront ni módulos posteriores.
- Sin direcciones o datos de cliente adicionales, porque no forman parte de las props autorizadas actuales.
