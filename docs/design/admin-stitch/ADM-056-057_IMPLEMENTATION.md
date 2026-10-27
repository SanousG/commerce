# FASE 26.30 — Refunds · ADM-056–057

## Alcance y contrato funcional

Implementación Vue completa de las dos pantallas reales registradas en `ADMIN_SCREENS_INVENTORY.md`:

- **ADM-056** — `GET /admin/refunds` (`admin.refunds.index`) → `admin/refunds/Index.vue`.
- **ADM-057** — `GET /admin/refunds/{refund}` (`admin.refunds.show`) → `admin/refunds/Show.vue`.

Refunds pertenece al módulo Payments y representa compensaciones financieras manuales. La creación no se duplicó: continúa en Payment Detail mediante `POST /admin/payments/{payment}/refunds`. El único endpoint de mutación propio de ADM-057 es `POST /admin/refunds/{refund}/complete`.

No se modificaron backend, dominio, modelos, migraciones, rutas ni snapshots. No se utilizó Stitch ni se añadió conexión con pasarelas, ejecución automática, conciliación, cancelación o reversión.

## Estados y operaciones reales

`RefundStatus` declara `PENDING`, `COMPLETED`, `FAILED` y `CANCELLED`. La operación implementada por el backend actual es únicamente:

- `PENDING → COMPLETED`, manualmente y bajo `refunds.manage`.

No existen endpoints actuales para aprobar, marcar failed, cancelar o revertir. Por ello la UI muestra todos los estados serializables, pero ofrece **Complete pending refund** únicamente cuando coinciden la capability `complete` entregada por Laravel y el permiso efectivo `refunds.manage`.

La finalización exige `external_reference` e `idempotency_key`, ambos normalizados y validados por `CompleteManualRefundRequest`. La UI deshabilita el botón durante el envío, solicita confirmación y regenera la clave solo después de éxito; Laravel sigue siendo la defensa autoritativa frente a reintentos y concurrencia.

## ADM-056 — Listado

- Conserva búsqueda por número de Order, filtro por `RefundStatus` y filtro contextual UUID `order_id`.
- Conserva orden descendente, paginación server-side de 20 y query string entre páginas.
- Usa exclusivamente los props existentes: Refund, Order, Payment method/provider, importe, moneda, estado, motivo y fechas.
- Desktop presenta tabla financiera compacta; mobile presenta cards sin ancho mínimo ni scroll horizontal.
- Order, Payment y Refund tienen enlaces directos a sus pantallas existentes.
- Distingue Store sin Refunds de una búsqueda/filtro sin coincidencias.
- Fechas, estados, métodos y providers se localizan; UUID, número de Order, motivo e importes almacenados no se traducen.

## ADM-057 — Detalle

- Presenta resumen de importe, moneda, estado y fechas; motivo, UUID y referencia externa; Payment, Order y Return opcional.
- Presenta `captured_total`, `refunded_total` y `refundable_remaining` exactamente como los calcula y serializa Laravel.
- No reconstruye saldo, moneda ni ledger en Vue. El enlace a Payment Detail permite revisar el ledger transaccional completo, porque Refund Detail no recibe una colección de transacciones.
- La referencia externa de Refund se presenta como la serializa actualmente `RefundController`; este contrato no aplica el helper de enmascarado usado por `PaymentController`.
- La operación manual explica explícitamente que registra dinero ya devuelto fuera de la plataforma y que no contacta a un proveedor.
- Errores conocidos de referencia duplicada, estado, moneda, saldo e idempotencia se localizan, conservando como fallback cualquier mensaje no reconocido del servidor.

## Integridad financiera y relaciones

- El importe se mantiene como string decimal escala 4. Vue solo aplica formato visual localizado y nunca usa `Number` para aritmética monetaria.
- `CreateRefund` bloquea Order, Payment, Return opcional y transacciones; reserva el saldo de Refunds `PENDING` y rechaza acumulados superiores a captures exitosos.
- `CompleteManualRefund` bloquea Order → Payment → Refund → transactions, revalida moneda y saldo, y crea exactamente un `PaymentTransaction` `REFUND/SUCCEEDED` compensatorio.
- La idempotencia valida que importe, moneda, Refund, motivo y referencia coincidan; una clave reutilizada con payload distinto se rechaza.
- El ledger exitoso permanece append-only. La operación actualiza la proyección de estado de pago del Order, registra auditoría y resuelve/emite notificaciones, sin cambiar Fulfillment ni Inventory.
- Return es opcional, debe pertenecer al mismo Tenant, Store y Order, y se conserva como referencia histórica. No dispara movimientos ni reembolsos automáticos.

## Seguridad, RBAC y aislamiento

- Las rutas permanecen bajo sesión Laravel, email verificado, CSRF, `tenant`, `store` y permisos `refunds.view`/`refunds.manage`.
- Todas las lecturas y lookups vuelven a limitarse por `TenantContext` y `StoreContext`; otro Tenant o Store obtiene `404` y un rol sin permiso obtiene `403`.
- La UI no envía `tenant_id`, `store_id`, importes derivados ni moneda.
- `OWNER` y `ADMIN` poseen `refunds.view/manage`; `MEMBER` no recibe acceso al módulo.
- Las acciones financieras continúan usando `POST`; no existe mutación mediante GET.

## Diseño, responsive e internacionalización

- Executive Precision B2B mediante `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice`.
- Desktop prioriza tabla, importes tabulares y bloques financieros compactos.
- Mobile 390 px usa cards, grillas apilables, textos con wrap y CTAs de al menos 40 px, sin overflow horizontal propio.
- Catálogo `refunds.*` completo en inglés y es-PE predeterminado: filtros, estados, vacíos, acciones, confirmación, advertencias, errores y mensajes de éxito.
- `scripts/validate-i18n.mjs` inspecciona ahora también ambas páginas Refunds para impedir claves estáticas ausentes o sin resolver.

## Rendimiento y bundles

- No se añadieron requests, polling, filtros client-side ni dependencias.
- Se preservan eager loading de Order/Payment, cálculo batch del balance en el detalle y paginación server-side.
- Build Vite de producción:
  - ADM-056 `Index-DGgUPyW3.js`: **7.86 kB** (**2.78 kB gzip**).
  - ADM-057 `Show-DPsPjKHP.js`: **9.74 kB** (**3.07 kB gzip**).
  - CSS global: **80.73 kB** (**16.33 kB gzip**).
  - Entrada compartida: **433.42 kB** (**135.13 kB gzip**).
- Vite informó solo la advertencia preexistente del paquete opcional `fontaine`; no se instaló ninguna dependencia.

## Validación ejecutada

- Guard `pgsql.testing/commerce_testing`: **1 passed, 4 assertions**.
- Payments + Refunds + lifecycle de notificación: **36 passed, 294 assertions**.
- Returns + Orders: **31 passed, 390 assertions**.
- Suite Laravel completa: **636 passed, 4429 assertions**.
- `npm run test:i18n`: **1994 mensajes Vue**, 45 labels de navegación y 43 mensajes Laravel por locale validados.
- `npm run typecheck`: aprobado.
- `npm run build`: aprobado en 1.56 s.
- `git diff --check`: aprobado.

## Limitaciones y omisiones deliberadas

- No hay creación dentro de Refunds: se mantiene en Payment Detail.
- No hay aprobación, fallo manual, cancelación, reversión, gateway, webhook ni conciliación porque no existen endpoints para esas operaciones.
- ADM-057 no recibe filas individuales del ledger ni actores; muestra los tres agregados server-side y enlaza al ledger de Payment.
- No se recalcularon snapshots de Order, Checkout o Fiscal; no se modificaron Payment, Return, Inventory ni Fulfillment directamente.
- Sin backend, dominio, migraciones, Supabase, Stitch, dependencias, commit, push o PR.
