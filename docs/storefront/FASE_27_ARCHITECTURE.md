# Fase 27 — Arquitectura del Storefront

**Estado:** planificación, sin implementación
**Fecha de auditoría:** 2026-10-27
**Alcance:** tienda pública para compradores dentro del monolito Laravel/Inertia existente

## 1. Resumen ejecutivo

La recomendación es mantener un solo despliegue y agregar una **superficie pública separada** dentro del monolito. El Storefront no debe consumir los controladores `/admin` ni reutilizar la selección de Tenant/Store basada en la sesión del administrador. Un middleware público debe resolver una tienda activa a partir de un host verificado o, sólo en desarrollo, de un identificador público en la URL. De esa resolución se derivan siempre Tenant, Store, moneda, locale, zona horaria, branding y alcance de consultas.

Los dominios comerciales ya ofrecen una base sólida: publicación de Listings, precios decimales, promociones, validación dinámica de carrito, reservas de inventario, snapshots de checkout y orden, impuestos incluidos e idempotencia. Lo que falta principalmente es la **capa de entrega pública**, identidad segura de carrito/comprador, proyecciones de catálogo, entrega pública de imágenes, configuración de dominios/SEO y una decisión explícita de pago público.

El MVP recomendado es **guest-first**. Un comprador no es un `User` administrativo. El carrito se vincula mediante un secreto opaco en cookie host-only y la orden puede conservar `customer_id = null`, usando los snapshots de contacto/dirección que ya soportan Checkout y Orders. Las cuentas de compradores se difieren hasta existir un caso de negocio y un diseño de identidad separado.

No debe anunciarse pago online: no hay adaptador, webhook ni flujo público de proveedor implementado. Tampoco debe prometerse tarifa de envío: Checkout fija `shipping_total = 0.0000`. Estos puntos son bloqueos de producto, no tareas de interfaz.

## A. Estado real del backend

### A.1 Inventario por capacidad

| Área | Estado comprobado | Clasificación para Storefront |
|---|---|---|
| Tenancy | Tenant activo y membresía administrativa se resuelven por sesión con `ResolveTenant`; no existe resolución pública | Requiere adaptador público |
| Stores | Store tiene `slug`, estado, moneda, timezone, locale y `StoreSettings`; la selección actual depende de sesión administrativa | Datos reutilizables; resolución pública faltante |
| Store Settings | Nombre visible, descripción corta, contacto, dirección PE, redes, logo, favicon y dos colores | Listo como fuente; requiere proyección y media pública |
| Catalog | Productos, categorías jerárquicas, opciones, variantes, estados y galerías tenant-wide | Modelos reutilizables; consultas públicas faltantes |
| Media | Pipeline seguro, master privado y derivados WebP 300/1200; entrega actual sólo a administradores | Procesamiento listo; controlador público faltante |
| Listings | Listing por Store, slug público, estado publicado, variantes habilitadas y lista de precios efectiva | Núcleo listo; listado/búsqueda pública faltante |
| Pricing | Listas tenant-wide, precisión `numeric(19,4)`, moneda y resolución de precio | Listo para reutilizar vía backend |
| Promotions | Promoción activa por fecha UTC, no acumulable, prioridad determinista y decimal exacto | Listo; hoy es tenant-wide, no store-specific |
| Inventory | Disponibilidad y reservas con locks; suma todos los almacenes activos del Tenant | Reutilizable con limitación tenant-wide |
| Customers | Identidad comercial tenant-wide separada de `users`; direcciones y unicidad email/teléfono | Requiere política pública; no es autenticación |
| Cart | Token aleatorio, TTL, líneas store-scoped, precios/promos/impuestos/stock recalculados | Servicios reutilizables; acceso público seguro faltante |
| Checkout | Idempotencia, contacto/direcciones, snapshots, reserva de inventario y expiración | Servicios reutilizables; HTTP público y UX faltantes |
| Orders | Copia atómica del Checkout READY, snapshots inmutables, idempotencia por checkout | Servicio reutilizable con actor nulo |
| Payments | Ledger e idempotencia; pagos manuales administrativos; flags por Store | No hay pago público/online operativo |
| Fulfillment | Fulfillment parcial, shipment y tracking gestionados por admin | Consulta pública segura faltante; mutaciones son admin |
| Tax | Precios tax-inclusive, `BigDecimal`, snapshots y tasa por fecha local de Store | Listo para reutilizar desde Cart/Checkout |
| Fiscal | Factura/boleta y SUNAT Beta administrativos, sin PDF y sin automatización | Fuera del Storefront MVP |
| SEO | Slug de listing; no hay campos SEO, canonical, sitemap, robots ni SSR configurado | Faltante |
| Dominios | No existe entidad de dominio, verificación DNS ni host confiable | Faltante |

### A.2 Funcionalidades listas para reutilizar

1. `FindPublishedListing` como punto inicial de lookup store-scoped por slug.
2. `ListingEligibility` para exigir Store, Product y Variant activos, listing publicado, variante habilitada y precio existente.
3. `ListingPriceResolver`, `PriceResolver` y `EffectivePriceCalculator` para precio base/final y promoción vigente.
4. `TenantInventoryAvailability` para disponibilidad agregada del Tenant.
5. `CreateCart`, mutaciones de Cart, `CartCalculator` y `CartValidator`.
6. `StartCheckout`, `UpdateCheckout`, `PrepareCheckout`, reserva y liberación/expiración de inventario.
7. `CreateOrderFromCheckout`, que admite actor administrativo nulo y conserva snapshots.
8. `TaxCalculator` y resolución temporal de tasas.
9. Modelos/snapshots de Order y estados de Shipment para futuras consultas públicas.
10. Pipeline de Media y derivados ya generados.

La reutilización significa invocar servicios de aplicación desde nuevos adaptadores públicos. No significa publicar clases Eloquent completas ni llamar rutas administrativas desde Vue.

### A.3 Funcionalidades que requieren adaptadores públicos

- Resolución de contexto por host/ruta y middleware fail-closed.
- Proyecciones DTO de tienda, catálogo, detalle, carrito, checkout y orden.
- Form Requests públicos sin permisos RBAC administrativos.
- Controladores Inertia públicos y rutas con nombres separados.
- Consultas paginadas de Listings publicados, categorías y búsqueda.
- Resolución batch de precios, descuentos, disponibilidad e imágenes para evitar N+1.
- Entrega de derivados Media autorizados por referencia pública.
- Cookie/token de carrito y autorización de cada mutación.
- Orquestador público Checkout → Order y selección de términos de pago soportados.
- Confirmación/consulta de orden mediante una credencial pública independiente.
- Metadata por página, canonical, robots y sitemap.

### A.4 Funcionalidades faltantes

1. Estado explícito `storefront_enabled` o equivalente para publicar/despublicar toda la tienda.
2. Registro y verificación de subdominio/dominio personalizado, canonical y redirecciones.
3. Identificador público global para subdominio y ruta local; `stores.slug` sólo es único dentro del Tenant.
4. Endpoints públicos y páginas Storefront.
5. Política de exposición Media y URLs versionadas/inmutables.
6. SEO editable para Store, Listing/Product y Category; actualmente sólo hay slugs y descripción de producto.
7. Home merchandising (hero, colecciones, destacados); no existe contenido administrable.
8. Tarifas/zonas/métodos de envío e impuesto de envío.
9. Pago online real, webhooks y credenciales de proveedor; no asumir que los enums son integraciones.
10. Instrucciones de transferencia bancaria y proceso público de comprobante.
11. Credencial segura para consulta de orden y mecanismo de entrega por email/SMS.
12. Identidad/autenticación de comprador, recuperación cross-device y merge de carritos.
13. Política segura `FindOrCreateCustomer`; el módulo declara que aún no existe.
14. Campos de consentimiento/privacidad y política de retención de PII.
15. SSR de Inertia o estrategia alternativa de HTML indexable. El build actual sólo configura entrada cliente.

### A.5 Funcionalidades exclusivas del administrador

- Gestión de Tenants, Stores, equipo, RBAC y selección de contexto administrativo.
- CRUD y publicación de Catalog/Listings, Pricing, Promotions, Media e Inventory.
- Gestión de Customers y direcciones como backoffice.
- Listado operativo de carritos/checkouts/órdenes.
- Captura manual, refunds, conciliación y settings de Payments.
- Fulfillment, creación y transición de Shipments.
- Tax/Fiscal/SUNAT, series, documentos y artifacts privados.
- Returns/Exchanges, Procurement, Costing, Audit, Analytics y Notifications.

Ninguna ruta `/admin` se expondrá o reutilizará como endpoint del comprador.

## B. Arquitectura pública propuesta

### B.1 Forma del monolito

Crear en una fase posterior `app/Modules/Storefront/{Application,Http}` como **módulo de entrega y orquestación**, no como dueño duplicado de Product, Price, Cart u Order. Sus dependencias deben apuntar hacia contratos/servicios de aplicación de los dominios existentes:

```text
HTTP público / Inertia
  -> ResolvePublicStore
  -> PublicStoreContext (Tenant + Store + dominio/canonical)
  -> Storefront queries / DTOs / orchestration
  -> Listings, Catalog, Media, Pricing, Promotions, Inventory,
     Cart, Checkout, Orders, Payments
```

- Las rutas continúan centralizadas en `routes/web.php`.
- No se crea API separada ni SPA desacoplada para el MVP.
- Vue recibe DTOs mínimos; Laravel calcula reglas y importes.
- Los dominios existentes conservan propiedad de datos y transiciones.
- Los componentes se ubicarán bajo `resources/js/pages/storefront` y `components/storefront`; no se alterará `AdminLayout`.
- El Storefront tendrá layout, traducciones y tokens visuales propios.

### B.2 Contextos separados

El contexto administrativo actual representa una membresía y selección de sesión. El contexto público representa una tienda publicada, no una autorización de empleado. No deben mezclarse sus resolutores.

`ResolvePublicStore` debe:

1. Normalizar `Host`: minúsculas, sin puerto, IDN según una política definida y sin confiar en `X-Forwarded-Host` salvo proxies configurados.
2. Buscar una asignación activa y verificada por hostname; en desarrollo, resolver el handle de ruta.
3. Cargar Store y Tenant por una misma relación y validar ambos como `ACTIVE`.
4. Exigir publicación global del Storefront.
5. Establecer un contexto request-scoped y limpiarlo en `finally`, como los middlewares actuales.
6. Derivar moneda, timezone, locale y Settings exclusivamente del Store resuelto.
7. Responder 404 genérico para host/handle inexistente, suspendido o no publicado.

No se aceptan `tenant_id` ni `store_id` del navegador. Los UUID enviados para listing, variant, cart, checkout u order sólo pueden **reducir** una consulta ya limitada por el contexto público.

## C. Estrategia de dominios y subdominios

### C.1 Alternativas

| Alternativa | Ventajas | Limitaciones/riesgos | Uso recomendado |
|---|---|---|---|
| `{handle}.commerce.com` | HTTPS wildcard, onboarding y canonical simples; cookies host-only | Requiere handle global, DNS wildcard y reservar nombres del sistema | Producción inicial |
| `mitienda.com` | Marca propia y mejor confianza/SEO | Verificación DNS, certificados, takeover, canonical y soporte operativo | Posterior al subdominio, misma arquitectura |
| `localhost/tienda/{handle}` | Sin DNS, fácil para desarrollo | Cookies comparten host; el slug actual no es global; no representa CORS/TLS real | Sólo desarrollo/testing |

### C.2 Modelo recomendado

Planificar una entidad store-owned, por ejemplo `store_domains`, con:

- `tenant_id`, `store_id` con foreign key compuesta.
- `hostname` normalizado y único globalmente.
- `type`: plataforma o personalizado.
- `status`: pendiente, verificando, activo, fallido/deshabilitado.
- `is_canonical` con un solo canonical activo por Store.
- prueba de propiedad/valor DNS y `verified_at`.
- timestamps operativos; nunca almacenar secretos TLS en esta tabla.

Además, el Store necesita un **handle público global** o una regla de subdominio equivalente. No se debe usar `stores.slug` directamente mientras sólo sea único por Tenant. La ruta local será `/tienda/{publicHandle}` y deberá resolver por ese índice global, no por un `tenant_id` de query string.

Orden recomendado:

1. Ruta local en desarrollo y pruebas.
2. Subdominio de plataforma con wildcard.
3. Dominio personalizado una vez resueltos DNS, TLS, proxy y takeover.

Para múltiples dominios de una Store, sólo el canonical se indexa. Los alternos hacen redirect 301/308 preservando path y query permitidos. Cart/Checkout nunca se cachean en la redirección.

## D. Resolución segura Tenant/Store

### D.1 Invariantes

- `PublicStoreContext.store.tenant_id === PublicStoreContext.tenant.id`.
- Tenant y Store deben estar activos y el Storefront publicado.
- Toda consulta pública incluye `tenant_id` **y** `store_id` cuando el recurso es store-owned.
- Product/Category/Media son tenant-wide, pero sólo se alcanzan a través de un Listing publicado en el Store resuelto.
- Una variante debe pertenecer al producto del Listing, estar activa y habilitada en ese Listing.
- Precio y moneda salen de la lista efectiva y deben corresponder a la moneda comercial aceptada por el flujo.
- Cart, Checkout, Order, Payment, Fulfillment y Shipment se cargan dentro de Tenant + Store antes de validar credenciales del comprador.
- Las respuestas 404 no deben permitir inferir si un UUID existe en otra tienda.

### D.2 Configuración derivada

| Valor | Autoridad |
|---|---|
| Tenant | Relación del dominio/handle verificado hacia Store |
| Store | Registro resuelto por host o handle, nunca request ID |
| Moneda | `stores.currency` y lista de precios efectiva; sin conversión |
| Timezone | `stores.timezone`, usada para reglas temporales/tax |
| Locale | `stores.locale`, con fallback de aplicación |
| Branding/contacto | `store_settings` de la Store resuelta |
| Pago permitido | `store_payment_settings`, limitado a capacidades realmente implementadas |
| Catálogo | Listings publicados de esa Store |

### D.3 Cookies y sesión

- Cookie de carrito host-only, `HttpOnly`, `Secure` en producción, `SameSite=Lax`, cifrada/firmada por Laravel.
- No configurar una cookie compartida `.commerce.com`; reduce el radio de fuga entre tiendas.
- En desarrollo, donde todas las tiendas comparten `localhost`, usar nombre/mapa namespaced por `publicHandle` validado.
- Rotar la credencial al convertir el carrito y no incluirla en URL, logs o analytics.
- Preferir persistir sólo un digest del bearer token en una evolución del esquema. El token actual se guarda en claro y fue diseñado para el backoffice.
- CSRF de Laravel sigue siendo obligatorio para mutaciones Inertia.

## E. Inventario de pantallas públicas

| Pantalla | MVP | Contenido/acciones |
|---|---:|---|
| Inicio de tienda | Sí | Identidad, descripción, categorías y productos recientes/destacados con regla inicial simple |
| Catálogo | Sí | Listings publicados, paginación y orden definido por backend |
| Búsqueda y categorías | Sí, dentro del catálogo | Query textual y categoría activa; filtros avanzados después |
| Detalle de producto | Sí | Galería, descripción, variantes habilitadas, precio/descuento y disponibilidad |
| Carrito | Sí | Líneas, cantidades, problemas, resumen recalculado y CTA checkout |
| Checkout | Sí | Contacto, entrega/dirección, revisión, preparación y creación idempotente |
| Confirmación de pedido | Sí | Número, snapshots, estado y siguiente paso permitido; acceso con credencial segura |
| Información/contacto | Sí, puede ser sección/página única | Contacto, dirección, redes y WhatsApp configurados |
| Consulta de pedido | Después | Sólo con magic link/token de alta entropía; no con número+email solamente |
| Cuenta/registro/login comprador | Después | Requiere identidad separada, recuperación y ownership de órdenes |
| Favoritos, reseñas, comparador | No | Fuera del MVP |
| Devolución autoservicio | No | Returns actual es backoffice |

No hay modelo de banners, colecciones ni páginas CMS. El inicio MVP debe derivarse de datos existentes y no introducir controles ficticios.

## F. Flujos de compradores

### F.1 Compra invitada recomendada

1. Middleware resuelve PublicStoreContext.
2. Al primer agregado se crea Cart con Store/Tenant derivados y se entrega credencial en cookie.
3. Cada acceso carga Cart por Store + Tenant + token/digest, exige estado ACTIVE y renueva TTL según reglas actuales.
4. Agregar/modificar/eliminar invoca servicios Cart existentes; IDs se vuelven a resolver dentro del Store.
5. Cart se recalcula en cada lectura/mutación relevante. Vue nunca envía precio, descuento, impuesto ni disponibilidad como autoridad.
6. `StartCheckout` usa clave de idempotencia generada por cliente y asociada a esa intención.
7. El comprador completa snapshots de contacto y dirección; no es obligatorio crear Customer.
8. `PrepareCheckout` recalcula, valida impuestos y reserva inventario.
9. Un orquestador crea Order una sola vez y configura sólo términos/métodos públicos soportados.
10. La confirmación usa credencial separada o derivada de una sesión de checkout rotada; no expone otros pedidos.

### F.2 Customers

- `Customer` es identidad comercial tenant-wide, no cuenta ni sesión.
- Para el primer MVP, mantener `customer_id = null` es válido en Cart, Checkout y Order.
- No buscar y adjuntar automáticamente por email/teléfono: podría revelar o apropiarse de una identidad existente.
- Una fase posterior podrá crear/adjuntar Customer después de consentimiento y con un servicio idempotente que maneje carreras de unicidad y estados BLOCKED/ARCHIVED.
- Direcciones introducidas en Checkout ya se copian como snapshots. Guardarlas en la libreta de Customer debe ser opcional y posterior.

### F.3 Recuperación

- MVP: recuperación en el mismo navegador mediante cookie hasta expiración.
- Cross-device: diferir hasta disponer de email verificado/magic link o cuenta de comprador.
- No enviar tokens en query strings persistentes salvo links firmados, expirables y de un solo propósito.

### F.4 Acceso a pedidos

- Confirmación inmediata: autorización por una credencial opaca asociada a Store + Order.
- Consulta posterior: token aleatorio almacenado como digest, expiración/revocación y entrega a un canal verificado.
- No usar UUID, número de orden, email o teléfono como único factor.
- Exponer sólo snapshots del comprador, estados de pago/fulfillment y tracking permitido; nunca ledger interno, notas, costos, artifacts fiscales o datos de otros clientes.

## G. Integraciones reutilizables

### G.1 Catálogo público

La consulta pública base debe partir de `store_listings` del Store contextual y exigir:

```text
listing=PUBLISHED
AND store=ACTIVE
AND tenant=ACTIVE
AND product=ACTIVE
AND EXISTS variante ACTIVE + habilitada + precio resoluble
```

Para cada página, cargar en batch productos, categorías activas, variantes habilitadas, opciones, lista efectiva, promoción ganadora, disponibilidad agregada y media READY. No serializar modelos completos.

Categorías son tenant-wide. Una categoría sólo aparece públicamente si está activa y tiene al menos un Listing público elegible en esa Store. Los descendientes no son implícitos para promociones; la UX de navegación puede mostrar jerarquía, pero debe definir si filtrar una categoría incluye descendientes.

### G.2 Precio, promoción, impuesto y stock

- Mostrar `baseAmount`, `finalAmount`, descuento y moneda calculados por Laravel.
- Mantener strings decimales; no usar `number` JS para recomputar importes.
- El catálogo puede mostrar disponibilidad como estado (`disponible`, `agotado`, `pocas unidades`) y evitar divulgar inventario exacto si no es necesario.
- Cart es preview dinámico; Checkout READY y Order son snapshots.
- Impuesto es incluido en precio y se descompone server-side.
- La disponibilidad actual agrega almacenes activos de todo el Tenant y la reserva asigna default-first. Si una Store no puede vender desde todos, se necesita una nueva relación/configuración antes del lanzamiento.

### G.3 Media pública

- Resolver únicamente Media READY alcanzable desde branding de la Store o desde Product/Variant de un Listing público contextual.
- Servir `THUMBNAIL`/`MEDIUM`, nunca master.
- URL versionada por checksum o versión inmutable; `Cache-Control: public, immutable` sólo cuando la autorización ya quedó codificada en una URL no reutilizable para otra relación.
- Fallback: galería de variante si existe, si no galería de producto; ALT según contrato de Media.
- No reutilizar `/admin/media/*`.

### G.4 Checkout, Order, Payment y Fulfillment

- Checkout y Order pueden reutilizar sus servicios centrales sin recalcular snapshots en Order.
- `CreateOrderFromCheckout` ya es idempotente por `checkout_session_id` y admite actor nulo.
- Payments sólo tiene creación/captura manual administrativa. `online_enabled` no ofrece un checkout online.
- COD tiene cálculo/configuración de plan reutilizable si está habilitado, pero falta selección pública y definición de UX.
- Bank transfer carece de instrucciones comerciales en Store Settings; no debe exponerse hasta modelarlas.
- Fulfillment no participa en la compra pública; sus estados y tracking pueden alimentar una consulta posterior read-only.

## H. Funcionalidades faltantes priorizadas

### Bloqueantes antes de vender

1. Contexto público y publicación global de Store.
2. Handle/dominio confiable y canonical.
3. Proyecciones públicas de catálogo + media.
4. Posesión segura del carrito y checkout.
5. Orquestación pública de Order y decisión de método/términos de pago.
6. Decisión sobre alcance de inventario por Store.
7. Políticas de privacidad, consentimiento y retención de PII.

### Importantes, no bloqueantes para catálogo inicial

- SEO editable y SSR/prerender evaluado.
- Consulta posterior de orden.
- Custom domains.
- Customer persistente y direcciones guardadas.
- Shipping real.
- Merchandising/CMS de inicio.

## I. Seguridad

### I.1 Riesgos principales y controles

| Riesgo | Control propuesto |
|---|---|
| Host header spoofing | Hosts permitidos/verificados, proxy confiable explícito, normalización y 404 fail-closed |
| Custom-domain takeover | Verificación DNS periódica, estado activo, reclamación exclusiva y deshabilitar al perder propiedad |
| IDOR cross-store | Resolver contexto primero; queries con Tenant+Store; 404 uniforme; pruebas cruzadas |
| Fuga por caché | Key incluye `store_id`, locale, moneda, versión y filtros; no cachear datos privados; purge store-specific |
| Robo/fijación de carrito | 256 bits, cookie cifrada host-only, digest en DB, rotación al convertir, TTL y no logs |
| CSRF | Middleware web y token CSRF en todas las mutaciones |
| Enumeración de órdenes | Bearer de alta entropía/magic link, rate limit y respuesta uniforme |
| Manipulación de precio/stock | Ignorar importes cliente; recalcular/lock/reservar server-side |
| Carrera/doble pedido | Idempotency keys, locks existentes y unique checkout→order |
| PII en logs/analytics | Redacción, no incluir payloads de direcciones/tokens y limitar props públicas |
| Media privada expuesta | Sólo derivados y sólo referencias públicas contextualizadas; nunca storage key/master |
| HTML/XSS en descripciones | Tratar descripción como texto o sanitizar server-side si se decide rich text |
| Abuso de búsqueda/cart/checkout | Rate limiting por IP + Store + secreto cuando exista; límites de query/cantidad |
| Sesión admin confundida | Guard comprador separado; nunca inferir Customer desde `Auth::user()` |

### I.2 Cobertura obligatoria

Cada endpoint público tenant-aware debe probar al menos: otra Store del mismo Tenant, otro Tenant, Store/Tenant inactivo, listing despublicado, variante deshabilitada, credencial equivocada, host no registrado y caché sin colisión. Las pruebas de mutación incluyen CSRF, idempotencia y carreras relevantes.

## J. SEO y rendimiento

### J.1 URLs y metadata

URL canónica sugerida:

```text
/                         inicio
/productos                catálogo/búsqueda
/categorias/{slug}        categoría
/productos/{listingSlug}  detalle
/carrito                  carrito (noindex)
/checkout                 checkout (noindex)
/pedido/{accessToken?}    confirmación/consulta (noindex; evitar token canónico)
/contacto                 información/contacto
/sitemap.xml
/robots.txt
```

- El hostname forma parte de la identidad de Store y del canonical.
- Producto usa `storefront_slug`, no `products.slug`.
- Metadata MVP puede tener fallback: nombre/descripcion de Product y nombre/descripcion de Store.
- Campos SEO explícitos son una evolución posterior y deben ser Store/Listing-specific cuando corresponda.
- Sitemap contiene únicamente canonical, Store activa y Listings elegibles; no carrito/checkout/orden.
- Páginas vacías, filtros arbitrarios, búsqueda y estados privados usan `noindex` según política.

### J.2 Renderizado

Inertia cliente entrega HTML inicial con payload, pero el proyecto no tiene entrada/servidor SSR configurado. Para SEO competitivo se recomienda evaluar **Inertia SSR en el mismo monolito**, sin Nuxt y sin cambiar reglas al cliente. Debe hacerse después de estabilizar las proyecciones públicas. Como mínimo, Laravel debe emitir `<title>`, description, canonical, OG y JSON-LD correctos en la respuesta inicial.

### J.3 Caché

Comenzar sin caché de consultas comerciales hasta medir. Luego:

```text
sf:{storeId}:v{contentVersion}:{locale}:home
sf:{storeId}:v{contentVersion}:{locale}:catalog:{filtersHash}:p{page}
sf:{storeId}:v{contentVersion}:{locale}:listing:{listingId}
```

- Nunca usar sólo slug, hostname o `tenant_id`.
- Precio/promoción/stock tienen distinta volatilidad; no cachearlos junto al contenido por tiempos largos.
- Promociones con inicio/fin requieren TTL limitado al próximo límite temporal.
- Disponibilidad puede ser uncached o de TTL muy corto y siempre se revalida al mutar Cart/Checkout.
- Cart, Checkout, Order, Customer y tokens: `private, no-store`; nunca full-page cache.
- Redis existe, pero no se adopta por defecto sin una fase/medición explícita.

### J.4 Imágenes y mobile

- Derivados WebP existentes, dimensiones declaradas para evitar CLS y `loading=lazy` fuera del primer viewport.
- Imagen principal con prioridad controlada; galerías y listados lazy.
- Paginación server-side con límites acotados; no descargar catálogo completo.
- Consultas batch para precio/promoción/inventario/media; presupuesto de queries en pruebas.
- Diseño mobile-first, targets táctiles, resumen sticky prudente y checkout de pocos campos.

## K. Decisiones pendientes

1. Nombre de dominio base de plataforma y lista de subdominios reservados.
2. Handle público global: nuevo campo o asignación de dominio de plataforma.
3. Quién habilita/deshabilita el Storefront y cuáles son sus precondiciones.
4. Si cada Store vende inventario de todo el Tenant o sólo de ubicaciones asignadas.
5. Método público inicial: COD, orden pendiente con transferencia, o catálogo sin venta hasta integrar proveedor.
6. Si Pickup estará disponible; el backend lo soporta, pero faltan ubicaciones/instrucciones públicas.
7. Política de envío, costo e impuesto; actualmente siempre cero.
8. Momento y consentimiento para crear/adjuntar Customer.
9. Canal de confirmación y recuperación de orden (email inicialmente recomendado).
10. Política de exposición de stock exacto.
11. Inclusión de descendientes al filtrar categorías.
12. Necesidad de rich text; por defecto mantener texto plano.
13. Campos SEO editables y responsable de su administración.
14. Cuándo habilitar custom domains y quién opera DNS/TLS.
15. SSR desde MVP o inmediatamente después de validar el flujo transaccional.

## L. Recomendaciones técnicas

1. Implementar primero contexto público + catálogo read-only; no comenzar por Checkout.
2. Mantener Storefront como capa de proyección/orquestación y no duplicar reglas de dominio.
3. Usar DTOs explícitos con strings monetarios y enums públicos acotados.
4. Incorporar `PublicStoreContext` separado del contexto administrativo, aunque pueda contener las mismas entidades.
5. Diseñar `store_domains` y handle con constraints de unicidad y pruebas de host.
6. Publicar sólo derivados Media y conservar masters privados.
7. Adoptar guest checkout antes que una nueva autenticación de compradores.
8. Bloquear en UI y backend capacidades no operativas: online payment, shipping rate y consulta insegura.
9. Resolver la política de inventario por Store antes de aceptar órdenes reales.
10. Mantener rutas públicas y admin claramente diferenciadas y con Form Requests propios.

## M. Evidencia principal auditada

- Rutas y middleware: `routes/web.php`, `bootstrap/app.php`.
- Contextos: `Tenancy/Application/TenantContext.php`, `Stores/Application/StoreContext.php`.
- Contratos de dominio: README de Tenancy, Stores, Catalog, Media, Promotions, Customers, Checkout, Orders, Payments, Fulfillment, Tax y Fiscal.
- Listing: `FindPublishedListing`, `ListingEligibility`, `ListingPriceResolver`, `PublishListing`.
- Compra: servicios de Cart, `StartCheckout`, `PrepareCheckout`, `CheckoutInventoryAllocator`, `CreateOrderFromCheckout`.
- Persistencia: migraciones de Stores, Catalog, Pricing, Inventory, Promotions, Listings, Customers, Cart, Checkout, Orders, Payments, Fulfillment, Tax snapshots, Media y Store Settings.
- Frontend/build: `resources/js/app.ts`, `resources/css/app.css`, `vite.config.js`, `package.json`.

Los módulos Listings, Pricing, Inventory y Cart no tienen README de dominio actualmente; su estado se auditó desde servicios, modelos, migraciones, controladores y pruebas.
