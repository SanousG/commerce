# SF-04 — Checkout público Essential

**Estado:** preparación segura extendida por SF-04.2; creación pública de Order reservada para SF-04.3

## 1. Resultado y límites comerciales

SF-04 entrega el flujo guest-first desde un Cart autorizado hasta un Checkout
`READY`. SF-04.2 reemplaza la captura de entrega domiciliaria por un único punto
de recojo autorizado y facturación opcional; recalcula precios, promociones,
impuestos y stock, y reserva inventario durante el TTL configurado.

La auditoría detectó dos bloqueos. SF-04.2 los resolvió para la preparación, no
para la creación de Order:

1. **Entrega:** Pickup está autorizado explícitamente por Store y no representa
   shipping gratis. No se añadieron zonas, tarifas ni courier.
2. **Pago:** la combinación soportada es método esperado `CASH`, términos `COD`
   y depósito `NONE/0.0000`. No existe cobro, Payment ni captura al preparar.

La UI permite **validar y reservar temporalmente**, muestra pickup y pago al
recoger, y no ofrece “Confirmar pedido”. No existe endpoint público de creación
de Order. `CreateOrderFromCheckout` queda sin exponer hasta SF-04.3.

## 2. Contratos auditados y reutilizados

- `PublicStoreContext`: Tenant/Store nacen exclusivamente de una publicación
  vigente; la sesión administrativa y parámetros del navegador no participan.
- `PublicCartContext` / `PublicCartIdentity`: posesión por cookie Laravel
  cifrada, HttpOnly, Store-specific y sin token en URL o props.
- `CartCalculator`: autoridad de listing, variante, cantidad, stock, precio,
  promoción, moneda e impuesto.
- `StartCheckout`: un Checkout operacional por Cart e idempotencia persistida.
- `UpdateCheckout`: edición bajo lock sólo en `PENDING`.
- `PrepareCheckout`: repricing, snapshot decimal `numeric(19,4)`, snapshot fiscal
  v1, transición `PENDING → READY` y reserva transaccional.
- `CheckoutInventoryAllocator`: conserva asignación tenant-wide para contratos
  existentes y acepta una ubicación exacta para pickup público.
- `ExpireCheckout`: vence el Checkout y libera reservas cuando el TTL termina.
- `CreateOrderFromCheckout`: copia snapshots y valida reservas, pero no conoce
  shipping comercial ni configura términos de pago; no se invoca públicamente.
- `StorePaymentSettings` / `ConfigureOrderPaymentTerms`: CASH, COD y NONE son
  conceptos separados; `expected_method` nullable conserva compatibilidad.
- Customers/CustomerAddresses: no hay resolución guest segura ni identidad
  fiscal; SF-04 no crea ni adjunta Customer.

## 3. Endpoints públicos

Todos usan `/tienda/{publicHandle}`, grupo `web`,
`storefront.development`, `storefront.public` y `storefront.cart`.

| Método | Ruta | Función |
| --- | --- | --- |
| `POST` | `/checkout` | Recalcula Cart e inicia o recupera Checkout operacional |
| `GET` | `/checkout` | Recupera y presenta únicamente el Checkout del Cart poseído |
| `POST` | `/checkout/preparar` | Valida datos, recalcula, crea snapshots y reservas |

Los POST usan `throttle:public-checkout` y bloqueo de sesión de 10 segundos. Las
respuestas con PII llevan `Cache-Control: no-store, private`,
`Referrer-Policy: no-referrer` y `X-Robots-Tag: noindex, nofollow`.

## 4. Identidad, autorización e idempotencia

- La cookie del Cart es la credencial guest. Checkout siempre se busca por la
  combinación autoritativa `tenant_id + store_id + cart_id`.
- No se aceptan `tenant_id`, `store_id`, `cart_id`, `checkout_id`, importes,
  moneda, impuestos, método de pago ni tipo de entrega desde Vue.
- El token secreto de Checkout permanece en PostgreSQL y nunca llega a URL,
  Inertia, HTML ni JavaScript.
- CSRF permanece activo mediante el grupo `web`.
- Cada inicio/preparación requiere UUID de mutación. Inicio usa la constraint
  `(cart_id, idempotency_key)` y el índice de Checkout operacional. Preparación
  conserva en sesión un fingerprint del payload: un replay idéntico es seguro y
  reutilizar el UUID con PII distinta se rechaza.
- Locks existentes de Cart, Checkout, ubicaciones, variantes y niveles
  serializan la preparación. El bloqueo de sesión evita doble submit del mismo
  guest.
- No se incluyen datos personales en URLs, claves de rate limit, notificaciones
  ni caché pública.

## 5. Captura y presentación

El contrato público contiene únicamente campos soportados por Checkout:

- nombre y al menos email o teléfono;
- dirección e instrucciones de pickup resueltas por Laravel, nunca por Vue;
- snapshot opcional de dirección de facturación.

No se capturan DNI, RUC, razón social ni datos de tarjeta porque esos contratos
no existen en Checkout. Tampoco se crea Customer. Essential implementa progreso
de compra, formulario responsive, resumen sticky, importes autoritativos,
errores no destructivos, loading, estado `READY` y restricciones comerciales.
Los componentes del theme son visuales; la orquestación y DTO permanecen en
Storefront para reutilización por themes futuros.

## 6. Snapshots, impuestos y reservas

`PrepareCheckout` ejecuta un cálculo nuevo y no copia importes del request. La
promoción se aplica antes de descomponer impuestos tax-inclusive. CheckoutLine y
Checkout guardan escala 4, timestamp fiscal único y snapshot v1. El total
pagable no suma nuevamente el impuesto incluido.

Las reservas usan `reference_type = CHECKOUT_LINE`, expiran con el Checkout y no
modifican `on_hand`. Un fallo de impuesto o stock revierte líneas y reservas y
mantiene `PENDING`. Un `READY` recuperado no se recalcula ni edita. Al detectar
TTL vencido, la recuperación invoca `ExpireCheckout` para liberar stock.

La asignación pickup consume sólo la ubicación activa tenant-owned configurada
por la Store. No se creó ownership de almacén por Store ni fallback arbitrario.

## 7. Shipping, pagos y Order

La presentación expone `deliveryType = PICKUP`, `shippingConfigured = false`,
pickup autorizado y `paymentMethods = [CASH]` sólo cuando readiness está
completo. Siempre conserva `orderCreationAvailable = false`.

Para habilitar creación pública en SF-04.3 se necesita:

1. reutilizar exclusivamente el snapshot pickup ya congelado;
2. crear y configurar `OrderPaymentPlan` con `CASH + COD + NONE`;
3. una transacción/orquestación idempotente que cree Order y configure el plan
   de pago sin dejar estados parciales;
4. credencial pública de reconocimiento/consulta almacenada como digest.

Aunque `CreateOrderFromCheckout` crea una Order `CONFIRMED` y `UNPAID`, SF-04 no
lo llama: hacerlo antes de esos gates produciría una orden comercialmente
artificial. Nunca se crea Payment ni se marca una Order pagada.

## 8. Riesgo del token de Cart

El token aleatorio de 256 bits continúa almacenado en claro por el contrato
heredado. La cookie cifrada/HttpOnly evita exposición normal a frontend y red,
pero una lectura de PostgreSQL permitiría recuperar la credencial lógica. El
Checkout aumenta el impacto potencial porque esa credencial también autoriza
ver PII y el estado preparado del Cart asociado.

Mitigaciones actuales: entropía alta, cookie cifrada y namespaced por Store,
scope Tenant+Store+Cart en cada consulta, CSRF, rate limit, TTL, no-store y
ausencia de tokens en URLs/props/logs. Migrar a digest, rotar credenciales y
separar una credencial de acceso a Order requiere migración explícita futura; no
se introdujo una migración amplia en SF-04. No se encontró bypass bloqueante en
la autorización HTTP actual.

## 9. Pruebas

`PublicCheckoutTest` cubre middleware/CSRF, rate limit y locks; inicio guest;
contacto/facturación; pickup y CASH/COD/NONE; repricing; snapshot fiscal;
ubicación exacta; reservas; idempotencia; recuperación; ausencia de Customer,
Order y Payment; shipping bloqueado; aislamiento; expiración; headers privados
y ausencia de IDs/tokens.

Las suites existentes de Cart, Checkout, Orders, Payments y Tax mantienen la
cobertura profunda de promociones, impuestos, locks PostgreSQL, insuficiencia
concurrente, expiración, copia inmutable y separación Order/Payment.

## 10. Preparación para SF-04.3

SF-04.3 debe crear Order y su plan desde snapshots, mantenerla `UNPAID`, no crear
Payment y emitir una credencial pública almacenada como digest. No debe reutilizar
el token de Cart como consulta permanente ni usar el número de Order como
autorización. El detalle exacto está en `SF-04_2_PICKUP_AND_PAYMENT.md`.
