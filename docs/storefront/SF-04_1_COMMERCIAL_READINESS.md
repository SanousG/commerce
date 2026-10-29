# SF-04.1 — Preparación comercial para habilitar pedidos públicos

**Fecha de auditoría:** 2026-10-28  
**Estado:** propuesta para aprobación; no habilita creación pública de Orders  
**Dependencias:** `SF-04_PUBLIC_CHECKOUT.md`, Checkout, Orders, Payments,
Inventory, Fulfillment y Stores actuales

## Resumen ejecutivo

El backend ya puede preparar un Checkout `READY`, reservar inventario y copiar
el snapshot a una única Order `CONFIRMED` e `UNPAID`. No puede todavía ofrecer
una promesa comercial completa de entrega y pago al comprador.

La recomendación es iniciar con un **MVP mínimo de recojo en una única dirección
configurada por Store y pago en efectivo al recoger, sin adelanto**. Debe usar un
plan `COD/NONE`, pero COD se presenta al comprador como término de pago, no como
método. El método esperado será `CASH`; no se creará Payment ni captura al crear
la Order. El personal registrará y capturará el pago manual sólo después de
recibir y verificar el efectivo.

Este MVP es viable únicamente si el negocio aprueba inventario tenant-wide y,
para reducir el riesgo operativo, cada Tenant lanzado tiene una sola ubicación
activa de despacho o acepta expresamente que cualquier ubicación activa pueda
abastecer cualquier Store. No se deben crear almacenes por Store para este MVP.

El MVP ampliado puede sumar delivery propio con tarifa fija, zonas y
transferencia manual. Las tarifas positivas están bloqueadas además por el
contrato fiscal v1: sus constraints exigen `shipping_total = 0` y Fiscal rechaza
Orders con shipping positivo. Courier y pasarela quedan en fases posteriores.

Ningún endpoint de creación de Order debe habilitarse hasta aprobar las
decisiones de la sección J y completar los controles de la sección I.

---

## A. Contratos existentes

### A.1 Capacidades reutilizables

| Contrato | Capacidad existente | Límite relevante |
|---|---|---|
| Cart | Cart guest Store-scoped, cálculo autoritativo, TTL y mutaciones idempotentes a nivel público | `carts.token` se almacena en claro; el Cart no reserva stock |
| Checkout | Un Checkout operacional por Cart; contacto, shipping/billing, `SHIPPING`/`PICKUP`, snapshot monetario/fiscal y estados `PENDING → READY` | El adaptador público fuerza `SHIPPING`; no existe opción comercial de entrega ni cotización |
| `PrepareCheckout` | Recalcula catálogo, precio, promoción e impuesto; crea líneas; reserva stock con locks; usa escala 4 | Fuerza `shipping_total = 0.0000` y `grand_total = subtotal` |
| `CreateOrderFromCheckout` | Lock de Checkout, idempotencia por `checkout_session_id`, validación de reservas y copia exacta de snapshots | Crea `CONFIRMED/UNPAID`; no configura entrega, plan de pago ni acceso público |
| `StorePaymentSettings` | Flags Store-owned para transferencia, efectivo, online y COD; tipo/valor de adelanto COD | No guarda instrucciones bancarias, proveedor online ni compatibilidad entrega-pago |
| `OrderPaymentPlan` | Snapshot `PREPAID` o `COD`; total, pago ahora/después y adelanto | No guarda el método esperado (`CASH`/`BANK_TRANSFER`/`ONLINE`) ni vencimiento |
| Payments | Payments manuales pendientes, capturas idempotentes completas y ledger append-only | Sólo backoffice; crear un Payment no confirma pago |
| PaymentIntent/Attempt | Esquema para proveedor, expiración, intentos e IDs externos | No hay servicio público, adaptador, webhook ni proveedor operativo |
| InventoryReservation | Reserva por línea, ubicación y variante; estados, expiración, release y commit parcial | Al crear Order se elimina `expires_at`; no hay expiración automática de Order abandonada |
| Fulfillment | Asignación exacta OrderItem–reserva–ubicación, commits y shipments manuales con tracking | No cotiza ni decide entrega; COD/NONE permite fulfillment con captura cero |
| StoreSettings | Una dirección comercial peruana y datos de contacto por Store | No declara que la dirección sea punto de recojo ni guarda horarios/instrucciones |

### A.2 Invariantes que deben preservarse

- Tenant y Store proceden de `PublicStoreContext`; ningún ID del request es
  autoridad.
- Checkout recalcula; Order copia y nunca recalcula snapshots.
- La Order creada no equivale a Payment confirmado.
- Sólo transacciones `CAPTURE/SUCCEEDED` forman el total pagado.
- Transferencia o efectivo manual empiezan como `Payment PENDING`.
- Cada consulta debe incluir Tenant y, para recursos Store-owned, Store.
- Fulfillment conserva la ubicación exacta de cada reserva.
- No se debe reinterpretar `online_enabled` como pasarela disponible.

### A.3 Bloqueos adicionales encontrados

1. Los constraints fiscales de Checkout y Order v1 exigen
   `shipping_total = 0`; `GenerateFiscalDocument` también rechaza shipping
   positivo porque no existe su tratamiento tributario.
2. Una Order convierte reservas temporales en reservas activas sin expiración.
   Sin política de vencimiento, pedidos públicos abusivos o abandonados pueden
   bloquear stock indefinidamente.
3. `OrderPaymentPlan` no conserva el método que el comprador eligió o espera
   usar. `COD` sólo expresa cuándo se paga.
4. `CreateOrderFromCheckout` es idempotente para duplicados, pero falta una
   idempotencia pública persistente que detecte la misma clave con una selección
   de entrega/pago distinta.
5. `PICKUP` no conserva dirección, horario ni instrucciones de recojo como
   snapshot contractual.

---

## B. Alternativas de entrega

### Matriz de evaluación

| Alternativa | Backend necesario | Configuración por Store | Integración Checkout | Impacto monetario/fiscal | Validaciones | Complejidad y riesgos |
|---|---|---|---|---|---|---|
| **A. Recojo en tienda** | Opción de entrega Store-owned; snapshot de punto e instrucciones; selección pública | Habilitado, una dirección completa, instrucciones, horario/SLA y plazo de recojo | Permitir `PICKUP`; no exigir dirección de envío; recalcular antes de `READY` | Tarifa cero **por regla explícita**, no por el cero técnico. Sin cambio fiscal de shipping si permanece cero | Store/publicación activas; dirección completa; opción vigente; inventario abastecible; compatibilidad con pago | **Baja-media.** Riesgo de reservar en almacén remoto, no-show y cambios de dirección no snapshot |
| **B. Delivery propio con tarifa fija** | Configuración de opción y tarifa; cotizador interno determinista; snapshots monetario y fiscal de shipping | Habilitado, tarifa, moneda, cobertura mínima, instrucciones y SLA | Elegir `SHIPPING`; validar destino; sumar quote al total antes de reservar | Shipping positivo cambia `grand_total`; requiere definir IGV/categoría fiscal y documento | Moneda igual a Store; destino dentro de cobertura; tarifa no negativa; quote vigente | **Media-alta.** Cobertura ambigua y shipping fiscal hoy bloqueado |
| **C. Tarifas por zonas** | Entidades de zona/rate, matcher determinista, prioridad, vigencia y quote snapshot | Zonas activas, códigos geográficos, tarifa, prioridad, exclusiones y SLA | Capturar códigos canónicos, resolver una sola zona y congelar quote | Mismo cambio fiscal que B; cada tarifa forma parte del total contractual | No solapamiento ambiguo; país/ubigeo válidos; zona única; quote vigente; límites de peso/importe sólo si se modelan | **Alta.** Los textos libres actuales no sirven para matching confiable; mantenimiento de ubigeos y overlaps |
| **D. Courier externo** | Puerto/adaptador por proveedor, credenciales cifradas, quote/booking idempotentes, webhooks, labels, cancelación y tracking ingestion | Cuenta por Store, servicios habilitados, origen, packaging y secretos | Cotizar con expiración; revalidar al confirmar; manejar caída del proveedor | Tarifas, impuestos, diferencias de cotización y conciliación | Firma de webhook, allowlist de estados, retries, deduplicación y timeout/fallback | **Muy alta.** Dependencia externa, PII, drift de tarifa, labels y estados fuera de sincronía |

### Recomendación de entrega

- **MVP mínimo:** sólo A, una dirección de recojo por Store y tarifa cero
  explícitamente configurada.
- **MVP ampliado:** A + B; sumar C sólo después de adoptar códigos geográficos
  canónicos y cerrar fiscalidad.
- **Posterior:** D en fase propia. Los `shipments` actuales pueden recibir
  tracking manual, pero no constituyen una integración courier.

No se recomienda usar `StoreSettings.address_*` directamente como promesa
mutable. Puede ser el origen para configurar el primer punto, pero Checkout y
Order deben guardar el snapshot mostrado al comprador.

---

## C. Alternativas de pago

| Opción | Backend actual | Desarrollo faltante | Estado de Order/Payment |
|---|---|---|---|
| **Pago al recoger** | `CASH`, Payment manual y plan `COD/NONE` existen | Selección pública, snapshot de método esperado, compatibilidad con `PICKUP`, instrucciones y regla de handoff | Order `CONFIRMED/UNPAID`; sin Payment al colocar. En caja: Payment `PENDING`, luego captura sólo tras verificar efectivo |
| **Contraentrega** | `COD/NONE` permite `amount_due_now = 0`; Fulfillment puede avanzar | Delivery aprobado, método esperado, instrucciones operativas, conciliación de cobro y tratamiento de fallos/rechazos | Order sigue `UNPAID` al despachar. Captura manual sólo después de confirmar el dinero recibido |
| **Transferencia manual** | `BANK_TRANSFER` crea Payment `PENDING`; PREPAID bloquea Fulfillment hasta capturas suficientes | Instrucciones bancarias Store-owned y versionadas, referencia/comprobante opcional, SLA/vencimiento y revisión administrativa | Nunca pagada por declaración del comprador. Sólo `CaptureManualPayment` tras verificar abono |
| **Adelanto COD** | Cálculo porcentual/fijo y elegibilidad por `amount_due_now` ya existen | Mecanismo público real de cobro, conciliación, expiración, reintento y refund; selección de método | No habilitar mientras no haya capturas exitosas por al menos `amount_due_now` |
| **Pasarela online** | Tablas Intent/Attempt/Payment/Transaction y providers enumerados | Adaptador real, credenciales, intent lifecycle, retorno seguro, webhooks firmados e idempotentes, reconciliación y refunds | Order y Payment permanecen separados; el navegador nunca declara éxito financiero |

### Reglas de exposición pública

- Mostrar únicamente combinaciones aprobadas de entrega + término + método.
- `cash_enabled` habilita el canal de efectivo; `cod_enabled` habilita el término
  diferido. Para pago al recoger/contraentrega se requieren ambos y adelanto
  `NONE` en el MVP mínimo.
- No crear un Payment de efectivo al colocar la Order: no existe dinero aún.
- Una transferencia declarada o con comprobante continúa pendiente.
- No permitir PREPAID sin un camino real para completar y conciliar el pago.
- No inferir pasarela de `online_enabled`; ese flag debe permanecer oculto al
  público hasta existir un proveedor operativo.

---

## D. Adelantos COD

### Lo que ya funciona

`ConfigureOrderPaymentTerms` puede copiar la configuración de Store a un
`OrderPaymentPlan`. `CalculateCodDeposit` calcula a escala 4 `NONE`, porcentaje
o importe fijo y rechaza adelantos mayores al total. Fulfillment exige capturas
exitosas por al menos `amount_due_now`.

### Lo que impide habilitarlos

- No hay cobro público online.
- No hay flujo público de transferencia/comprobante para adelanto.
- No hay conciliación automática ni deadline del adelanto.
- No hay política de cancelación y liberación de reservas cuando el adelanto no
  llega.
- Un `PaymentIntent` persistido no equivale a una captura.

### Política propuesta

1. MVP mínimo: `cod_deposit_type = NONE` obligatorio para opciones públicas.
2. Si la Store tiene porcentaje/fijo configurado, la combinación COD queda
   indisponible públicamente; no se degrada silenciosamente a cero.
3. MVP ampliado: habilitar adelanto sólo con transferencia verificada o pasarela
   real, deadline explícito y cancelación segura de Order no pagada.
4. Fulfillment no puede comenzar hasta que el ledger contenga capturas
   `SUCCEEDED >= amount_due_now`.
5. Reembolso o cancelación posterior usa Payments/Refunds; nunca se elimina ni
   edita una transacción del ledger.

---

## E. Inventario compartido

### Comportamiento actual

La disponibilidad agrega todas las ubicaciones activas del Tenant. La reserva
bloquea ubicaciones, variantes y niveles en orden estable, asigna primero la
ubicación default y puede dividir una línea entre varias ubicaciones. No existe
relación Store–InventoryLocation. Varias Stores del mismo Tenant compiten por el
mismo stock y los locks/reservas evitan sobreventa concurrente.

### Cuándo es aceptable para el MVP

Se puede mantener inventario tenant-wide si el negocio confirma todo lo
siguiente:

- el stock es realmente compartido entre Stores;
- cualquier ubicación activa está autorizada a abastecer cualquier Store;
- operaciones puede consolidar reservas divididas o crear varios fulfillments;
- el recojo puede prepararse desde la ubicación asignada o transferirse al punto
  prometido dentro del SLA;
- no existen precios de stock, propiedad o exclusividad por canal;
- desactivar una ubicación impide nuevas reservas, sin invalidar silenciosamente
  Orders existentes.

Para el primer lanzamiento se recomienda **una sola ubicación activa por
Tenant**. Si hay varias, debe existir aprobación operativa documentada y pruebas
de pedidos divididos. Esta es una restricción de lanzamiento, no una nueva
arquitectura de almacenes.

### Cuándo se necesitaría scope por Store

Sólo si una Store no puede vender stock de ciertas ubicaciones, el punto de
recojo debe reservar exclusivamente un almacén, o existen canales con stock
dedicado. En ese caso se justificaría una relación Store–Location o una política
de abastecimiento. No debe añadirse preventivamente.

### Riesgo de reservas posteriores a Order

`CreateOrderFromCheckout` pone `expires_at = null`. El MVP público necesita una
política anti-abuso para Orders COD impagas: deadline de recojo/aceptación,
cancelación idempotente sólo si continúa `UNFULFILLED` y sin capturas, release de
reservas, auditoría y notificación. La cancelación nunca debe tocar reservas ya
comprometidas por Fulfillment.

---

## F. Seguridad y confirmación

### F.1 Creación idempotente y atómica

Implementar un orquestador Storefront que, dentro de una transacción:

1. resuelva Tenant/Store/Cart/Checkout desde contextos y credenciales;
2. bloquee el Checkout `READY` no vencido;
3. valide la opción de entrega y pago ya congeladas;
4. aplique una clave pública persistente con hash del payload;
5. invoque `CreateOrderFromCheckout`;
6. configure `OrderPaymentPlan` y método esperado;
7. cree la credencial pública de Order por digest;
8. devuelva siempre la misma Order para un replay idéntico;
9. rechace la misma clave con payload distinto.

La unique `orders.checkout_session_id` sigue siendo la última defensa contra
duplicados. Un fallo al crear plan o credencial debe revertir también la Order;
no se permite una Order pública parcialmente configurada.

### F.2 Credenciales públicas

- Generar al menos 256 bits aleatorios con CSPRNG.
- Persistir sólo `SHA-256`/HMAC digest, nunca el bearer recuperable.
- Asociar digest a `tenant_id + store_id + order_id + purpose`.
- Incluir expiración, revocación, último uso y, si aplica, consumo único.
- Comparar en tiempo constante y responder 404 uniforme a inválido, expirado,
  revocado, otra Store u otro Tenant.
- Entregar la confirmación inmediata mediante cookie cifrada, HttpOnly,
  host-only, `Secure` en HTTPS y `SameSite=Lax`.
- Un magic link futuro debe intercambiar un token corto de propósito único por
  cookie y redirigir a URL limpia; el token no permanece en canonical, analytics
  ni referrer.

### F.3 ESSENTIAL-11/12

ESSENTIAL-11 y ESSENTIAL-12 deben representar la misma confirmación responsive
(desktop/mobile), no dos contratos. La pantalla sólo se habilita después de leer
la Order desde el servidor con la credencial anterior y debe mostrar:

- número de Order como referencia, nunca como autorización;
- estado comercial, de pago y fulfillment por separado;
- modalidad/dirección o destino enmascarado y snapshot monetario;
- siguiente paso real: recojo y pago pendiente, transferencia pendiente o pago
  online confirmado según ledger;
- tracking únicamente si existe y está autorizado.

No debe mostrar ledger, UUID, IDs de Tenant/Store, referencias privadas,
historial interno, costos, artifacts fiscales ni PII completa. Debe usar
`Cache-Control: no-store, private`, `Referrer-Policy: no-referrer`,
`X-Robots-Tag: noindex, nofollow` y no cargar analytics de terceros. Una página
local de “éxito” sin relectura autorizada de Order no satisface ESSENTIAL-11/12.

### F.4 PII

- Solicitar sólo datos necesarios para la modalidad elegida; recojo no necesita
  dirección residencial.
- No incluir nombres, email, teléfono, dirección ni tokens en URLs, logs,
  métricas, claves de rate limit o notificaciones.
- Definir retención, acceso administrativo, finalidad, consentimiento y proceso
  de eliminación/anonimización conforme a obligaciones fiscales y comerciales.
- Serializar DTOs públicos allowlist; nunca modelos Eloquent completos.
- Mantener CSRF, rate limit por IP + Store y bloqueo/replay protection.

### F.5 Token de Cart almacenado en claro

La cookie cifrada mitiga exposición al navegador, pero una lectura de base de
datos recupera la credencial que permite ver el Checkout y su PII. Antes o junto
al lanzamiento se recomienda migrar a `token_digest`:

1. agregar digest nullable y unique;
2. backfill `hash(token)` para carts vigentes;
3. buscar por digest del token presentado;
4. generar nuevos carts guardando sólo digest;
5. tras una ventana compatible, eliminar/restringir la columna recuperable;
6. rotar/revocar al convertir Cart y usar otra credencial para Order.

El token de Checkout actual no debe reutilizarse como acceso permanente a
Order.

### F.6 Aislamiento obligatorio

Cada nueva tabla lleva `tenant_id` y, si es Store-owned, `store_id`, con FKs
compuestas e índices coherentes. Toda lectura prueba: otra Store del mismo
Tenant, otro Tenant, publicación/Store/Tenant inactivos, token equivocado,
revocado y expirado. Ningún `tenant_id` o `store_id` del frontend amplía scope.

---

## G. Propuesta MVP mínimo

### Oferta comercial

- Entrega: **recojo en una única dirección configurada**, costo `0.0000`
  explícitamente aprobado.
- Pago: **efectivo al recoger**, `PaymentTerms::COD`, depósito `NONE`, método
  esperado `CASH`.
- Inventario: tenant-wide; preferentemente una ubicación activa.
- Comprador: guest, `customer_id = null`.
- Confirmación: ESSENTIAL-11/12 con credencial digest y estado `UNPAID` real.

### Flujo

1. Store publica recojo sólo si dirección, instrucciones y política están
   completas, `cash_enabled + cod_enabled` están activos y depósito es `NONE`.
2. Checkout permite `PICKUP`, omite dirección de envío y congela punto/costo.
3. Submit idempotente crea Order, plan COD, método esperado y acceso público en
   una transacción.
4. La confirmación informa “pedido recibido/confirmado, pago pendiente al
   recoger”; nunca “pagado”.
5. En el recojo, personal verifica efectivo, crea Payment manual y lo captura.
6. La operación no entrega/completa el pickup sin registrar el cobro, salvo una
   política explícita de excepción auditada.
7. Un no-show vence según política y libera reservas de forma segura.

### Migraciones futuras mínimas

Nombres finales sujetos al diseño de implementación; la capacidad requerida es:

1. **Configuración de entrega Store-owned** (`store_delivery_options` o
   equivalente): tipo, enabled, tarifa explícita, dirección/instrucciones y
   versionado/concurrencia.
2. **Snapshots de entrega en Checkout y Order:** opción, nombre, costo,
   dirección/instrucciones y deadline aplicable.
3. **Método esperado en `order_payment_plans`** o snapshot Payments equivalente,
   con constraint de compatibilidad.
4. **Accesos públicos a Order:** digest, Tenant, Store, Order, purpose,
   expiración/revocación/uso.
5. **Idempotencia pública persistente:** clave + fingerprint + Checkout/Order,
   unique Store-scoped.
6. **Deadline de Order/pago/recojo** que soporte cancelación segura y release.
7. **Digest de credencial de Cart**, con migración compatible.

No requiere migración fiscal de shipping mientras el único costo de recojo sea
cero y esté explícitamente configurado.

---

## H. Propuesta MVP ampliado

### Oferta comercial

- Recojo + delivery propio con tarifa fija.
- Opcionalmente zonas, después de códigos geográficos canónicos.
- Efectivo al recoger, contraentrega sin adelanto y transferencia bancaria
  manual verificada.
- Adelanto COD sólo después de implementar cobro/conciliación segura.
- Pasarela y courier continúan fuera del MVP ampliado.

### Desarrollo y migraciones adicionales

- Tarifas/zonas Store-owned, vigencia, prioridad, moneda y cobertura.
- Códigos ubigeo/canónicos en destino; los textos quedan como snapshot visual.
- Quotes de entrega con fingerprint, expiración y fuente/version de tarifa.
- Snapshot fiscal de shipping: categoría, tasa, base gravada/exonerada/inafecta e
  impuesto incluido; actualización de constraints de Checkout/Order.
- Evolución de FiscalDocument para representar shipping positivo y sus totales.
- Configuración bancaria versionada: beneficiario, banco, cuenta/CCI en formato
  validado, instrucciones y moneda.
- Referencia/comprobante público opcional con almacenamiento privado tenant-safe,
  límites y revisión; nunca captura automática.
- Deadline PREPAID/transferencia y cancelación automática idempotente.
- Matriz entrega-pago explícita por Store, no inferencias dispersas de flags.
- Para zonas: constraints de prioridad y una política inequívoca ante overlap.

### Exclusiones

- API de courier, compra de etiqueta, tracking webhook y tarifas dinámicas.
- Captura de tarjeta propia.
- Adelantos sin conciliación.
- Cuenta de comprador, Customer automático y recuperación por número+email.

---

## I. Cambios técnicos necesarios

### I.1 Orden recomendado por dependencias

1. **Cerrar decisiones comerciales y fiscales** de la sección J.
2. **Modelar configuración y snapshots de entrega**, empezando por pickup.
3. **Modelar selección de pago y compatibilidad**; mantener COD separado de
   `CASH/BANK_TRANSFER/ONLINE`.
4. **Definir deadline y cancelación segura** para evitar stock cautivo.
5. **Añadir digest de Cart y acceso público de Order**.
6. **Crear orquestador público atómico e idempotencia persistente**.
7. **Implementar confirmación ESSENTIAL-11/12** y proyección pública mínima.
8. **Agregar cobertura cross-tenant/cross-store, concurrencia, replay y PII**.
9. **Habilitar pickup + cash/COD-NONE** sólo detrás de readiness por Store.
10. **Evolucionar fiscalidad de shipping** antes de tarifa fija o zonas.
11. **Añadir transferencia manual** con instrucciones y conciliación.
12. **Pasarela y courier** en fases independientes posteriores.

### I.2 Coordinación de módulos

- Storefront orquesta, pero no se apropia de cálculo, ledger, stock u Order.
- Una capacidad acotada de Shipping/Delivery puede poseer opciones y quotes; no
  se debe crear una integración courier anticipada.
- Checkout posee el snapshot temporal; Orders recibe copia inmutable.
- Payments posee términos, método esperado, Payments y transacciones.
- Inventory/Fulfillment conserva reservas, ubicaciones y commits actuales.
- Fiscal define la representación tributaria antes de aceptar shipping positivo.

### I.3 Readiness fail-closed por Store

El CTA final sólo aparece si una evaluación server-side confirma simultáneamente:

- publicación, Tenant y Store activos;
- modalidad de entrega activa, completa y cotizable;
- combinación de término/método soportada;
- COD sin adelanto para el MVP mínimo;
- dirección/política de recojo completas;
- inventario dentro de la política aprobada;
- configuración fiscal válida;
- expiración y acceso público configurados.

Un cambio administrativo posterior invalida nuevas preparaciones, pero no altera
snapshots de Checkout `READY` u Orders existentes.

---

## J. Decisiones comerciales pendientes

Todas requieren aprobación antes de programar el endpoint final:

1. ¿Se aprueba recojo como primera y única modalidad?
2. ¿La dirección de `StoreSettings` es el único punto de recojo o se necesita
   uno diferente? ¿Cuáles son horario, instrucciones y SLA?
3. ¿El recojo es explícitamente gratuito y cómo se comunica?
4. ¿Se acepta efectivo al recoger como primera combinación y se exige
   `cod_deposit_type = NONE`?
5. ¿En qué momento operativo se registra/captura efectivo y quién puede hacerlo?
6. ¿Cuánto tiempo se reserva stock para pickup y cuándo se cancela un no-show?
7. ¿Se permite una sola ubicación activa por Tenant en lanzamiento o se acepta
   abastecimiento tenant-wide y pedidos divididos?
8. ¿Quién consolida stock de una ubicación distinta al punto de recojo y bajo
   qué SLA?
9. ¿Qué PII es obligatoria para recojo y cuál es su retención/consentimiento?
10. ¿La Order pública nace `CONFIRMED` como hoy o el negocio requiere aceptación
    manual? Cambiarlo afectaría el contrato existente y no se asume.
11. Para delivery fijo/zonas: cobertura, tarifa, gratuidad excepcional, SLA y
    tratamiento tributario/SUNAT del cargo.
12. Para transferencia: banco, beneficiario, moneda, instrucciones, deadline,
    comprobante y responsables de conciliación.
13. ¿Se enviará confirmación por email? Si sí, definir verificación, expiración,
    reenvío, revocación y contenido sin PII innecesaria.
14. ¿Cuál es la vigencia de la credencial de consulta y cómo solicita el
    comprador revocación/recuperación?
15. ¿Qué combinación de estados se mostrará como “pedido recibido”, “listo para
    recoger”, “pagado” y “entregado” sin mezclar Order, Payment y Fulfillment?

---

## K. Fase de implementación recomendada

### Fase SF-04.2 — Pedido público mínimo

Implementar exclusivamente configuración/snapshot de pickup, cash + COD/NONE,
deadline, digest/idempotencia, orquestación Order-plan-acceso y confirmación
ESSENTIAL-11/12. Esta fase debe terminar con pruebas y revisión antes de iniciar
otra modalidad.

### Fase SF-04.3 — Delivery propio y transferencia

Después de aprobar fiscalidad: tarifa fija, eventualmente zonas, snapshot fiscal
de shipping, transferencia pendiente e instrucciones/conciliación.

### Fases posteriores independientes

- **Payments online:** proveedor, webhooks, conciliación, refunds y recién luego
  adelantos COD online.
- **Courier:** quotes externos, booking, labels, webhooks y tracking ingestion.
- **Consulta/magic link ampliado:** recuperación cross-device y ciclo de
  revocación, sin convertir Customer en cuenta de usuario.

## Conclusión

La ruta más corta y segura no es declarar shipping gratuito ni simular un pago:
es una promesa acotada de recojo, tarifa cero explícita, efectivo al recoger y
COD sin adelanto. Incluso esa ruta requiere snapshots, vencimiento de reservas,
idempotencia persistente y credencial de confirmación por digest. Hasta que esas
capacidades y decisiones estén cerradas, SF-04 debe permanecer fail-closed para
creación pública de Orders.
