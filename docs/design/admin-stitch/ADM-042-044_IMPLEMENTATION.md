# ADM-042–044 — Implementación Checkout

Fecha: 2026-10-26
Fase: 26.25

## Alcance entregado

Se rediseñaron e internacionalizaron las tres pantallas administrativas reales de Checkout, sin generar Stitch, modificar Laravel, instalar dependencias ni iniciar el rediseño de Orders:

| ID | Ruta | Vista | Resultado |
|---|---|---|---|
| ADM-042 | `/admin/checkouts` | `admin/checkouts/Index.vue` | Listado, búsqueda, filtro de estado y paginación server-side |
| ADM-043 | `/admin/checkouts/create` | `admin/checkouts/Prepare.vue` | Inicio idempotente desde un Cart activo elegible |
| ADM-044 | `/admin/checkouts/{checkout}` | `admin/checkouts/Show.vue` | Detalle, edición `PENDING`, preparación, cancelación y handoff real a Order |

Las pantallas reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice` según Executive Precision B2B.

## Contrato funcional auditado

- El índice exige `checkouts.view`, limita la consulta al Tenant y Store activos, busca por prefijo de token, nombre o correo, filtra por estado, ordena por creación descendente y pagina 20 registros.
- Inertia recibe solo `token_hint`; nunca se expone el token completo.
- El inicio exige `checkouts.manage`, ofrece hasta 100 Carts `ACTIVE`, no vacíos y sin Checkout `PENDING`/`READY`, y envía un UUID de idempotencia generado por el navegador.
- Solo `PENDING` permite editar contacto, `SHIPPING`/`PICKUP`, dirección de envío y facturación. Las direcciones guardadas se resuelven nuevamente dentro del Tenant y Customer correctos.
- Preparar es una operación separada de guardar. La UI la bloquea mientras existan cambios sin guardar y evita doble envío.
- `READY` habilita crear Order únicamente con `orders.manage`. La creación usa POST, confirmación localizada y bloqueo de reenvío; nunca se dispara por GET o recarga.
- No existe método de pago en Checkout y no se agregó ninguno.

## Estados y operaciones

| Estado | Comportamiento real |
|---|---|
| `PENDING` | Editable; no reserva inventario; puede prepararse o cancelarse |
| `READY` | Líneas/totales congelados; inventario reservado hasta `expires_at`; puede crear Order o cancelarse |
| `COMPLETED` | Order creada; snapshot histórico readonly |
| `EXPIRED` | Reservas vencidas y liberadas; readonly |
| `CANCELLED` | Reservas activas liberadas explícitamente; readonly |
| `FAILED` | Reservado para política futura; los fallos actuales de preparación hacen rollback y conservan `PENDING` |

Las reservas visibles usan `ACTIVE`, `RELEASED`, `COMMITTED` y `EXPIRED`. No se incorporaron transiciones manuales adicionales.

## Cart → Checkout → Order

1. `StartCheckout` bloquea y revalida el Cart, es idempotente por Store/idempotency key y crea un Checkout `PENDING` sin reservar stock.
2. `UpdateCheckout` persiste únicamente datos admitidos mientras siga `PENDING`.
3. `PrepareCheckout` recalcula en servidor Pricing, Promotions, Tax y disponibilidad; genera snapshots exactos y reserva inventario atómicamente antes de cambiar a `READY`.
4. `CreateOrderFromCheckout` bloquea el Checkout `READY`, exige reservas vigentes, copia snapshots sin recalcular, crea una Order `CONFIRMED`, cambia Checkout a `COMPLETED` y Cart a `CONVERTED`.
5. Repetir la creación devuelve la misma Order. Una restricción de base de datos impide una segunda Order por Checkout.

## Integridad monetaria, fiscal e inventario

- Vue muestra sin recalcular los strings `numeric(19,4)` enviados por Laravel; no usa punto flotante ni conversión FX.
- Los precios comerciales son tax-inclusive. El impuesto se descompone del total final de línea después de promociones y no se suma nuevamente.
- Una sola marca UTC `tax_calculated_at`, interpretada en la zona horaria de Store, selecciona las tasas de toda la preparación.
- Se muestran subtotal antes de descuentos, descuento, bases gravada/exonerada/inafecta, impuesto incluido, subtotal, envío y total general.
- Líneas preparadas muestran precios unitarios/base/final, descuentos, promoción, categoría/tasa/impuesto y asignaciones de inventario.
- Cualquier fallo de pricing, promociones, impuestos o reserva hace rollback de la preparación completa.

## Seguridad y aislamiento

- Se preservan autenticación Laravel, sesión/cookie, CSRF y middleware `tenant`/`store`.
- `checkouts.view` controla lectura; `checkouts.manage` controla inicio, edición, preparación y cancelación; `orders.manage` controla la creación de Order.
- Todas las búsquedas de Checkout/Cart se limitan por Tenant y Store activos. UUID de otro Tenant/Store retorna 404.
- No se acepta `tenant_id` ni `store_id` del frontend. Las direcciones guardadas se limitan al Tenant y Customer del Checkout.
- Owner/Admin administran; Member conserva visualización según la matriz RBAC existente.

## UX, responsive e internacionalización

- ADM-042 usa tabla compacta desktop y cards mobile; búsqueda, estado, empty states y paginación mantienen sus parámetros en servidor.
- ADM-043 usa formulario de una columna y empty state cuando no existen Carts elegibles.
- ADM-044 usa KPIs compactos, formularios responsive, snapshots readonly, totales y líneas en cards; no introduce ancho mínimo ni scroll horizontal en 390 px.
- Se agregó `checkout.*` completo en `en` y `es-PE`, estados Checkout y estados de reservas. Español (Perú) sigue siendo el locale predeterminado.
- Fechas usan `Intl.DateTimeFormat` con el locale activo. Los montos conservan moneda y escala exactas recibidas.
- Acciones destructivas/handoff tienen confirmación localizada; formularios bloquean doble envío y muestran errores accesibles.

## Rendimiento y bundles

- Se preservan paginación server-side de 20, límite de 100 Carts elegibles y eager loading existente de líneas/reservas/ubicaciones, Customer/addresses y Order.
- No se agregaron requests, polling, historiales completos ni consultas frontend.
- Build Vite, tamaño raw / gzip:
  - ADM-042 Index: **7.76 kB / 2.82 kB**.
  - ADM-043 Prepare: **3.84 kB / 1.58 kB**.
  - ADM-044 Show: **23.79 kB / 6.04 kB**.
  - CSS global: **79.92 kB / 16.24 kB**; app JS: **375.72 kB / 120.89 kB**.

## Validación ejecutada

| Comando | Resultado |
|---|---|
| Guard `TestingDatabaseConnectionTest` | 1 passed, 4 assertions; `pgsql.testing/commerce_testing` confirmado |
| `php artisan test tests/Feature/Checkout` | 16 passed, 78 assertions |
| Cart + Orders | 33 passed, 288 assertions |
| Pricing + Promotions + Tax relevante + Inventory | 93 passed, 550 assertions |
| Suite Laravel completa | 636 passed, 4,429 assertions, 148.75 s |
| `npm run test:i18n` | 1,322 mensajes Vue, 45 labels sidebar y 43 mensajes Laravel por locale |
| `npm run typecheck` | Passed |
| `npm run build` | Passed; 828 módulos; 3.28 s |
| `git diff --check` | Passed antes de documentación; se repite al cierre |

La suite cubre RBAC, aislamiento cross-tenant/cross-store, idempotencia, restricciones de base de datos, snapshots, precisión monetaria, promociones, impuestos tax-inclusive y reservas de inventario. No contactó SUNAT real.

## Limitaciones y omisiones deliberadas

- No se generaron referencias Stitch; ADM-042–044 siguen sin assets propios y requieren validación visual manual desktop/mobile 390 px.
- No se modificaron controllers, Form Requests, Application Services, modelos, rutas, permisos, consultas ni contratos Inertia.
- No se agregaron métodos de pago, shipping fee editable, recálculo Vue, exportación, bulk actions, refresh, token completo ni endpoints.
- No se ejecutaron migraciones ni se modificó la base `commerce`.
- No se inició el rediseño de Orders ADM-045–046 ni otro módulo.
