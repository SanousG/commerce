# SF-04.2 — Recojo en tienda y pago al recoger

**Fecha:** 2026-10-28  
**Estado:** implementado; prepara Checkout `READY`, no crea Orders públicas  
**Dependencias:** SF-04, SF-04.1, Stores, Checkout, Inventory, Payments y Tax

## 1. Resultado

SF-04.2 cierra los gates mínimos para preparar una compra pública con una única
modalidad comercial:

- recojo en una dirección autorizada explícitamente por Store;
- reserva en una ubicación activa e inequívoca del inventario del Tenant;
- método esperado `CASH`;
- términos `COD` con depósito `NONE` y valor `0.0000`;
- shipping `0.0000` porque no existe servicio de envío, no porque se ofrezca
  “envío gratis”.

La preparación congela estos datos en Checkout, pero no invoca
`CreateOrderFromCheckout`, no crea Customer, Order ni Payment, y no marca ningún
importe como pagado. La confirmación pública pertenece a SF-04.3.

## 2. Contratos auditados

- `StoreSettings` es Store-owned y ya contiene la dirección comercial. Esa
  dirección sólo se autoriza para recojo cuando `pickup_enabled = true`.
- `StorePaymentSettings` mantiene capacidades separadas para `cash_enabled` y
  `cod_enabled`; COD no se interpreta como método.
- `PaymentMethod::Cash` representa el canal esperado. `PaymentTerms::Cod` y
  `CodDepositType::None` representan cuándo se paga y que no existe adelanto.
- `CheckoutSession` es el snapshot temporal autoritativo; `READY` conserva sus
  líneas, totales, impuestos, recojo y pago esperado hasta expirar.
- `OrderPaymentPlan.expected_method` es nullable para no alterar planes
  históricos. `ConfigureOrderPaymentTerms` sólo valida un método esperado cuando
  se proporciona.
- `CreateOrderFromCheckout` copia el snapshot de recojo, pero no se expone en
  rutas públicas ni configura todavía el plan de pago público.
- Reservations siguen perteneciendo a Inventory y conservan
  `reference_type = CHECKOUT_LINE`.

## 3. Configuración por Store

Store Settings permite activar o desactivar recojo, seleccionar una ubicación
activa del mismo Tenant, autorizar la dirección peruana configurada en contacto
y añadir instrucciones opcionales de hasta 1000 caracteres.

La dirección exige línea 1, distrito, provincia, departamento y país. La
validación HTTP limita la ubicación al Tenant activo y el update vuelve a
bloquearla y verificarla dentro de la transacción. Una FK compuesta
`(pickup_inventory_location_id, tenant_id)` impide referencias cross-tenant en
base de datos. Stores existentes quedan con recojo deshabilitado.

La pantalla de Payments muestra readiness para pago al recoger. Sólo está listo
si `cash_enabled`, `cod_enabled` y `cod_deposit_type = NONE` con valor cero. Se
reutilizan `stores.update` y `payments.settings`; no se añadió un rol o permiso.

## 4. Readiness y Checkout público

`PublicPickupReadiness` consulta siempre por Tenant y Store resueltos desde
`PublicStoreContext`. Falla cerrado si falta autorización, dirección completa,
ubicación activa, efectivo, COD o la combinación de depósito `NONE/0.0000`.

Vue no envía modalidad, ubicación, dirección de recojo, método, términos,
depósito, moneda ni importes. Laravel vuelve a resolver y bloquear
StoreSettings, StorePaymentSettings y la ubicación dentro de la misma
transacción que actualiza y prepara Checkout. El request prohíbe todos los
campos `shipping_*` y `billing_same_as_shipping`; facturación sigue opcional.

El snapshot `READY` guarda dirección, instrucciones, ubicación, `CASH`, `COD` y
`NONE/0.0000`. Constraints PostgreSQL exigen la combinación completa y prohíben
snapshots parciales. La presentación omite el UUID interno de ubicación y
mantiene `orderCreationAvailable = false`.

## 5. Inventario y expiración

Inventory permanece tenant-wide. La ubicación seleccionada es una regla
operativa de atención, no ownership del Store. Para pickup,
`CheckoutInventoryAllocator` reserva sólo en esa ubicación y no hace fallback a
otra. Sin UUID conserva el comportamiento tenant-wide de contratos existentes.

`ExpireCheckout` no cambia: un Checkout `READY` vencido pasa a `EXPIRED`, libera
reservas activas y reduce `reserved` sin cambiar `on_hand`. La recuperación
pública dispara esta expiración antes de presentar un Checkout operativo.

## 6. Seguridad e idempotencia

- `PublicStoreContext` y la cookie cifrada/HttpOnly del Cart son las únicas
  autoridades públicas; no se aceptan IDs internos.
- Permanecen CSRF, `throttle:public-checkout`, bloqueo de sesión y headers
  privados/noindex.
- Inicio usa idempotencia persistida. Preparación usa UUID y fingerprint; una
  clave reutilizada con PII diferente se rechaza.
- Precio, promoción, impuestos, moneda, stock y totales se recalculan en Laravel
  con escala decimal 4.
- Configuración comercial y stock se bloquean transaccionalmente antes de
  congelar snapshots y reservas.
- Ningún token o UUID interno de ubicación se publica en props o URLs.

## 7. Migración

`2026_10_28_000001_add_store_pickup_commercial_support.php` añade tres campos a
`store_settings`, snapshots a Checkout/Order y
`order_payment_plans.expected_method` nullable. Incluye defaults seguros, FKs
compuestas y checks de coherencia.

Se valida exclusivamente mediante tests en `pgsql.testing/commerce_testing`; no
se ejecuta manualmente contra `commerce`. Tras revisión y fuera de esta tarea,
el despliegue usará el mecanismo normal del proyecto (`php artisan migrate
--force`).

## 8. Pruebas

La cobertura incluye configuración habilitada/deshabilitada, dirección
incompleta, ubicación cross-tenant, RBAC, Store/Tenant públicos inactivos,
CASH/COD/NONE, ausencia de Payment, shipping prohibido, repricing, ubicación
exacta, stock, idempotencia, aislamiento, constraints y expiración/liberación.
Las suites corren sólo después del guard de la base aislada.

## 9. Contrato exacto para SF-04.3

SF-04.3 puede implementar un orquestador público idempotente que, bajo locks:

1. recupere el Checkout `READY` poseído por el Cart y rechace expiración;
2. llame una sola vez a `CreateOrderFromCheckout` sin recalcular snapshots;
3. configure `OrderPaymentPlan` con `CASH + COD + NONE`, debido ahora cero y
   debido al recoger igual al total;
4. no cree Payment ni cambie `Order.payment_status = UNPAID`;
5. emita una credencial pública nueva almacenada como digest, sin reutilizar el
   token del Cart ni el número de Order;
6. responda desde estado persistido para que reintentos produzcan la misma Order
   y el mismo plan.

SF-04.3 no debe añadir shipping tarifado, courier, pasarela, adelanto, Customer
implícito ni confirmación basada sólo en estado Vue.
