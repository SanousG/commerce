# FASE 26.27 — Payments · ADM-047 a ADM-049

## Alcance

Implementación Vue completa del módulo administrativo Payments:

- **ADM-047** — `/admin/payments` (`admin.payments.index`).
- **ADM-048** — `/admin/payments/{payment}` (`admin.payments.show`).
- **ADM-049** — `/admin/settings/payments` (`admin.payments.settings.show`).

No se generaron pantallas Stitch, no se modificó Laravel y no se añadieron endpoints, requests, servicios, pasarelas ni operaciones comerciales. El rediseño reutiliza **Executive Precision B2B** y los componentes administrativos compartidos.

## Contrato Laravel auditado

Payments es dueño del estado de cobro y del ledger operacional append-only. Orders conserva el acuerdo comercial y el estado de pago no implica despacho. Los importes usan precisión decimal de escala 4; el total cobrado se deriva solo de transacciones `CAPTURE` con estado `SUCCEEDED`, no de `Payment.amount` ni de la proyección `Payment.status`.

Todas las rutas están dentro de middleware `tenant` y `store`. Los controladores vuelven a limitar Payment, Order, Return y Settings mediante `TenantContext` y `StoreContext`; identificadores de otro alcance producen página vacía o `404`. Vue nunca recibe ni envía `tenant_id` o `store_id`.

| Superficie | Permiso | Contrato preservado |
|---|---|---|
| Listado y detalle | `payments.view` | Lectura store/tenant-scoped; MEMBER recibe `403` |
| Configuración | `payments.settings` | Lectura y actualización de settings de la Store activa |
| Crear refund desde detalle | `refunds.manage` | Acción independiente, validada de nuevo por ruta y FormRequest |

## ADM-047 — Listado

- Conserva filtros reales `search`, `status` y `order_id`; búsqueda únicamente por número de Order.
- Estados provienen de `PaymentStatus`; no se inventan filtros por método, proveedor o fecha.
- Mantiene orden descendente por creación, eager loading de Order, agregado SQL de captures exitosos y paginación server-side de 20.
- Tabla compacta de siete columnas en desktop y cards sin scroll horizontal en mobile.
- Método, provider, estados, fechas y referencias enmascaradas están localizados; los importes se formatean como strings sin conversión a `Number`.
- Diferencia Store vacía de filtros sin coincidencias y conserva el filtro contextual `order_id` procedente de Orders.

## ADM-048 — Detalle

- Presenta resumen, estado, método, provider, referencia enmascarada, vínculo al Order y ledger entregado por Laravel.
- El ledger tiene tabla desktop y cards mobile, con tipo, estado, importe, actor y fecha.
- `Payment.status` se identifica como proyección operativa; los captures exitosos continúan siendo la fuente financiera.
- El formulario de refund solo existe con `refunds.manage` y conserva `amount`, `reason`, `return_id` opcional e `idempotency_key`.
- Laravel sigue validando moneda, saldo reembolsable, refunds pendientes, Return del mismo Order/Store, idempotencia y locks Order → Payment → ledger. Vue no calcula el saldo reembolsable.
- El contrato actual no entrega una colección de Refunds ni `refundable_remaining` en esta pantalla; no se fabricó ese bloque ni se realizaron requests adicionales.

## ADM-049 — Configuración

- Conserva exactamente `bank_transfer_enabled`, `cash_enabled`, `online_enabled`, `cod_enabled`, `cod_deposit_type` y `cod_deposit_value`.
- Tipos reales: `NONE`, `PERCENTAGE` y `FIXED_AMOUNT`; `NONE` envía `0.0000`, porcentaje exige valor mayor que 0 y hasta 100, e importe fijo exige valor positivo.
- Las opciones pertenecen a la Store activa. Los planes existentes mantienen su snapshot; guardar afecta la configuración utilizada para planes nuevos.
- No se muestran ni almacenan credenciales de proveedor y no se sugiere que exista una pasarela configurada.

## Integridad, RBAC y aislamiento

- No se alteraron middleware, Gates, FormRequests, transacciones, locks, modelos, enums ni Application Services.
- Se preservan idempotencia scoped, prevención de sobrecobro/reembolso, transacciones exitosas append-only y referencias externas de Payment enmascaradas por Laravel.
- La creación y captura de Payment, cancelación y condiciones de pago continúan en el workbench de Order mediante endpoints ya existentes; no se duplicaron en ADM-048.
- Refunds ADM-056–057 permanece como módulo/pantallas posteriores y no fue rediseñado en esta fase.

## Internacionalización, responsive y rendimiento

- Todo texto de ADM-047–049 está disponible en `es-PE` predeterminado y `en` alternativo mediante vue-i18n.
- Estados, métodos, providers, tipos de transacción, depósitos, errores conocidos, avisos y acciones tienen etiquetas traducidas.
- Desktop mantiene tablas y formularios compactos; mobile 390 px usa cards y controles de ancho completo, sin navegación inferior ni overflow horizontal.
- Se reutilizan las props existentes y `Pagination`; no hay N+1 nuevo, polling ni requests auxiliares.

## Validación

Los resultados finales del guard, tests de Payments e integraciones, suite Laravel, i18n, TypeScript, build, bundles y `git diff --check` se registran en el informe de entrega de FASE 26.27.

## Omisiones deliberadas

- Sin backend, migraciones, dependencias, generación Stitch, Supabase ni cambios en módulos visuales anteriores.
- Sin gateways, webhooks, refunds automáticos, conciliación, captura parcial dentro de un Payment, exportación ni acciones masivas.
- Sin Refunds ADM-056–057, Fulfillment ADM-050–051 ni el siguiente módulo.
