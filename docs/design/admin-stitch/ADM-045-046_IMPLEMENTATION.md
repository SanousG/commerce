# FASE 26.26 — Orders · ADM-045 y ADM-046

## Alcance

Implementación Vue de:

- **ADM-045** — `/admin/orders` (`admin.orders.index`).
- **ADM-046** — `/admin/orders/{order}` (`admin.orders.show`).

No se generaron pantallas Stitch, no se modificó Laravel y no se añadieron operaciones comerciales. El rediseño usa el sistema **Executive Precision B2B** y los componentes administrativos existentes.

## Contrato Laravel auditado

Orders es un snapshot comercial permanente creado atómicamente desde un Checkout `READY` no vencido. La creación copia líneas, comprador, direcciones, promociones, impuestos tax-inclusive y montos `numeric(19,4)` sin recalcular catálogo, precios, promociones ni impuestos. La relación estable `OrderItem.checkout_line_id` conecta las reservas creadas por Checkout; crear la orden no altera `on_hand` y cancelar libera únicamente reservas activas de sus líneas.

El listado está siempre limitado por `TenantContext` y `StoreContext`; la Store no es un filtro del navegador. Laravel acepta `search`, `status`, `payment_status`, `fulfillment_status`, `customer_id`, `from` y `to`, ordena por `placed_at` descendente y pagina 20 registros. La búsqueda cubre número, nombre y correo.

El detalle entrega el snapshot de Order, líneas y `history.actor`. Los bloques adicionales son condicionales por permiso:

| Bloque | Permiso/condición de lectura | Acciones existentes preservadas |
|---|---|---|
| Cliente | `customers.view` y `customer_id` existente | Navegar al perfil |
| Payments | `payments.view` | Crear pago manual, cambiar términos, capturar o cancelar cuando sus flags lo permiten |
| Fulfillment | `fulfillments.view` | Crear desde reservas restantes cuando `can_manage` y la elegibilidad de pago lo permite |
| Returns | `returns.view` | Abrir devoluciones; crear con `returns.manage` y orden con despacho |
| Fiscal | `fiscal_documents.view` | Emitir solo cuando `can_issue` |
| Costing | `inventory.cost.view` | Proyección readonly de rentabilidad |
| Cancelación | `orders.manage`, `CONFIRMED`, `UNFULFILLED`, capturado `0.0000` | Cancelar con motivo y liberar reservas activas |

Los enlaces a Payments, Refunds, Returns y Fulfillments existen solo cuando el usuario puede ver el módulo y hay recursos relacionados. Las referencias externas se vuelven a validar en el alcance tenant/store del módulo destino.

## ADM-045 — Listado

- Búsqueda y los seis filtros reales, incluido el filtro contextual de cliente.
- Paginación server-side reutilizando `Pagination` y metadatos Laravel.
- Tabla compacta de siete columnas en desktop y cards sin scroll horizontal en mobile.
- `StatusBadge` para estado comercial, pago y despacho.
- Moneda e importe histórico mostrados sin cálculos; agrupación y separador decimal localizados sin convertir a `Number`.
- Fechas localizadas con `Intl.DateTimeFormat` y locale activo.
- Estados vacíos diferenciados entre Store sin pedidos y filtros sin coincidencias.
- Navegación al detalle como única acción del listado, coherente con `orders.view`.

## ADM-046 — Detalle

- Cabecera, estados, información general, referencias abreviadas de Checkout/Cart y contacto autorizado.
- Snapshots de envío y facturación.
- Líneas con cantidades, precios unitarios, descuentos, promoción, impuesto, bases tributarias y reservas.
- Totales exactos, indicando el impuesto incluido en subtotal; Vue no suma ni recalcula valores.
- Historial de estados con actor, fecha y motivo.
- Bloques condicionales de Payments, Fulfillment, Returns, Fiscal y Costing basados exclusivamente en props existentes.
- Formularios y endpoints existentes conservados, incluida la idempotencia de pagos/capturas.
- Cancelación visible exclusivamente cuando `canCancel` viene de Laravel.
- Tabla de rentabilidad solo en desktop y cards mobile, sin overflow horizontal.

## Integridad, RBAC y aislamiento

- No se reciben ni envían `tenant_id` o `store_id` desde Vue.
- No se alteraron consultas, middleware, permisos, FormRequests, servicios, transiciones ni modelos.
- Se preservaron `Cart → Checkout → Order`, reservas `CHECKOUT_LINE`, monedas, escala 4, snapshots fiscales y comerciales, y restricciones de cancelación.
- No se añadieron operaciones de cobro, reembolso, cancelación de fulfillment, despacho, SUNAT ni integraciones externas.
- La referencia externa de pagos continúa enmascarada por Laravel; las referencias Checkout/Cart se presentan abreviadas.

## Internacionalización y responsive

- Todo texto nuevo de Orders se incorporó en `en` y `es-PE` mediante vue-i18n.
- Estados canónicos usan `StatusBadge`; enums operativos visibles tienen etiquetas localizadas.
- Confirmaciones de cancelación de orden y pago están traducidas.
- Desktop prioriza densidad de información; a 390 px los filtros, líneas, métricas, acciones y tablas extensas se reorganizan en cards o grillas de una columna.
- No se añadió navegación inferior ni dependencia frontend.

## Rendimiento

- ADM-045 conserva 20 registros por página y no realiza requests adicionales.
- ADM-046 consume una sola respuesta Inertia y no solicita relaciones desde Vue.
- La carga condicional y eager loading siguen bajo control de `OrderController`; no se modificó el backend.

## Validación

Los resultados finales de guard, tests Laravel, checks frontend, build, bundles y `git diff --check` se registran en el informe de entrega de FASE 26.26.

## Omisiones deliberadas

- Sin cambios backend, rutas, reglas comerciales, migraciones o dependencias.
- Sin generación Stitch.
- Sin acciones masivas, exportación, edición de snapshots ni filtros no aceptados por `OrderIndexRequest`.
- Sin datos de pagos, envíos, devoluciones, documentos o costos cuando Laravel no entrega el contrato autorizado.
