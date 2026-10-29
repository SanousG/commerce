# SF-03 — Carrito público Essential

**Estado:** implementado

## Contrato y límites

SF-03 reutiliza `Cart`, `CartItem`, `CartState`, `AddCartItem`,
`UpdateCartItemQuantity`, `RemoveCartItem`, `ClearCart` y `CartCalculator`. El
Storefront sólo resuelve identidad pública, aplica el contexto de la Store y
proyecta DTO públicos. Los controladores administrativos no se exponen.

El carrito no crea `Customer`, reservas, Checkout, Order ni Payment. Agregar un
artículo no reserva inventario. Cada consulta recalcula elegibilidad, precio,
promoción, moneda, disponibilidad e impuestos con los servicios comerciales
existentes.

## Identidad guest

`CreateCart` conserva su token aleatorio de 256 bits. El navegador lo recibe
únicamente en una cookie procesada por `EncryptCookies` con estas propiedades:

- nombre derivado del handle público para separar Stores bajo el mismo host;
- path `/tienda/{publicHandle}`;
- `HttpOnly` y `SameSite=Lax`;
- `Secure` cuando la solicitud usa HTTPS;
- expiración alineada con `Cart.expires_at`.

El token no aparece en URLs, props Inertia, HTML, logs ni mensajes. La búsqueda
siempre combina token, Store y Tenant resueltos desde `PublicStoreContext`. Una
cookie manipulada, expirada, convertida o perteneciente a otra Store se ignora.
El siguiente agregado crea una identidad nueva.

El token continúa persistido en claro por el contrato previo de Cart. La cookie
cifrada reduce exposición en tránsito y frontend, pero migrar a digest
persistido requiere una futura migración y rotación explícita.

## Mutaciones, CSRF y concurrencia

Las rutas pertenecen al grupo `web`, conservan CSRF y usan el limitador
`public-cart`. Cada mutación exige un UUID `mutation_id`. Las rutas bloquean la
sesión durante diez segundos y guardan las últimas 50 mutaciones exitosas por
Store en la sesión server-side; repetir el mismo UUID no vuelve a aplicar la
operación. Los bloqueos `FOR UPDATE` del módulo Cart serializan sesiones que
comparten la cookie y protegen cantidades y estado.

El frontend deshabilita controles mientras hay una solicitud. Laravel vuelve a
validar cantidad, listing, variante, precio y disponibilidad; Vue nunca envía
ni calcula importes autoritativos.

## Endpoints

Todos usan el prefijo temporal `/tienda/{publicHandle}` y los middlewares
`storefront.development`, `storefront.public` y `storefront.cart`:

| Método | Ruta | Operación |
| --- | --- | --- |
| `GET` | `/carrito` | Mostrar el carrito vigente o estado vacío |
| `POST` | `/productos/{listingSlug}/carrito` | Crear/recuperar y agregar variante |
| `PATCH` | `/carrito/articulos/{lineKey}` | Actualizar cantidad |
| `DELETE` | `/carrito/articulos/{lineKey}` | Eliminar línea |
| `DELETE` | `/carrito` | Vaciar carrito |

`variant_key` y `lineKey` son referencias HMAC opacas. No son IDs internos ni
credenciales de posesión. La única credencial del carrito permanece en cookie.

## Presentación pública

`PublicCartPresenter` elimina IDs de Cart, Tenant, Store, Listing, Product,
Variant y Media. Expone sólo nombres, opciones, imagen pública autorizada,
cantidad, disponibilidad, importes recalculados, códigos de problema y una
clave opaca de línea.

Essential incorpora:

- agregado desde Product Detail;
- contador real en `StoreHeader`;
- carrito desktop de dos columnas y resumen sticky;
- tarjetas compactas y controles táctiles de 44 px en mobile;
- edición, eliminación, vaciado, alertas y estado vacío;
- importes localizados para `es-PE` y `en`;
- CTA de checkout deshabilitado con referencia explícita a SF-04.

Los diseños de referencia fueron ESSENTIAL-07 y ESSENTIAL-08. No se modificó
el proyecto Stitch ni se añadió lógica visual al dominio Cart.

## Pruebas

`PublicCartTest` cubre rutas web, rate limit, bloqueo de sesión, cookie,
creación, recuperación, CRUD, límites, disponibilidad, listing no elegible,
precios, promociones, impuestos, expiración, token manipulado, idempotencia,
dos Stores bajo el mismo host, aislamiento y ausencia de IDs o secretos.

Las pruebas existentes de Cart siguen cubriendo constraints PostgreSQL,
aislamiento tenant/store, bloqueo de filas, expiración, cálculo monetario y la
ausencia de reservas.

## Preparación para SF-04

SF-04 podrá consumir el `Cart` activo recuperado por la misma identidad, pero
deberá recalcularlo antes de iniciar Checkout. La reserva, snapshots fiscales,
datos del comprador, entrega y creación idempotente de Order permanecen fuera
de SF-03. El CTA actual no inicia ninguna operación.
