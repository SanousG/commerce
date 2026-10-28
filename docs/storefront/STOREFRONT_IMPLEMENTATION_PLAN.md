# Storefront — Plan de implementación

**Dependencia:** `docs/storefront/FASE_27_ARCHITECTURE.md`
**Estado:** propuesto; ninguna fase está iniciada ni cerrada

## 1. Principios de ejecución

- Un monolito Laravel 13 con Vue 3/Inertia; sin microservicios, Nuxt, React ni API duplicada.
- Cada fase entrega una capacidad vertical comprobable y no habilita la siguiente automáticamente.
- Rutas centralizadas en `routes/web.php`.
- Backend autoritativo para contexto, precio, promoción, impuesto, stock y estados.
- Las páginas Storefront usan layout/componentes propios y no modifican las 69 pantallas administrativas.
- Antes de cada fase tenant-aware se aplican reglas de aislamiento y pruebas cross-tenant/cross-store.
- Las decisiones de pago, inventario y shipping se cierran antes de aceptar órdenes públicas.

## 2. Dependencias y orden

```text
SF-01 Contexto y publicación pública
  -> SF-02 Catálogo, media y branding
      -> SF-03 Carrito invitado
          -> SF-04 Checkout y creación de orden
              -> SF-05 Confirmación, pago soportado y consulta segura

SF-02 -> SF-06 SEO, SSR y rendimiento
SF-01 -> SF-07 Dominios personalizados
SF-05 -> SF-08 Cuenta de comprador (opcional/post-MVP)
```

## 3. Fases técnicas

## SF-01 — Fundaciones públicas y resolución de Store

**Estado confirmado (2026-10-27): implementado.** Se adoptó una relación 1:1
`storefront_publications` con handle global y publicación explícita en dos
pasos. La ruta temporal exige flag, entorno local/testing y allowlist de Host.
La implementación y operación están documentadas en
`docs/storefront/SF-01_PUBLIC_FOUNDATIONS.md`. Este estado no inicia SF-02.

**Objetivo**
Crear una frontera pública confiable que resuelva Tenant/Store sin IDs del comprador y permita habilitar una Storefront de forma explícita.

**Alcance**

- Definir handle público global y estado de publicación.
- Diseñar/persistir asignaciones de dominio de plataforma; ruta local `/tienda/{publicHandle}`.
- Implementar `PublicStoreContext` y `ResolvePublicStore` fail-closed.
- DTO mínimo de Store: nombre, locale, moneda, timezone y branding básico.
- Separar route group público del grupo `/admin`.
- Aún no mostrar catálogo ni aceptar mutaciones comerciales.

**Módulos involucrados**
Storefront (nuevo adaptador), Stores, Tenancy, Media sólo para referencias de branding.

**Backend necesario**

- Migración(es) acotadas para publicación, handle y/o dominio de plataforma.
- Constraints de unicidad global, FK compuesta Tenant/Store e índice de resolución.
- Middleware y contexto request-scoped con limpieza garantizada.
- Normalización de Host y configuración explícita de proxies/hosts.
- 404 uniforme para Tenant/Store inactivo o Storefront despublicado.

**Frontend necesario**

- `StorefrontLayout` mínimo y página de tienda no disponible/landing vacía.
- Props públicas separadas de `auth`, membresía, permisos y notificaciones admin.
- Tokens CSS propios con fallback seguro de colores.

**Pruebas**

- Resolución por handle válido.
- Host desconocido, handle inexistente, estados inactivos y Storefront apagado.
- Colisión/normalización de hostname y handles reservados.
- Dos Stores del mismo Tenant y dos Tenants: no intercambiar contexto.
- No compartir props administrativas.
- Constraints de base de datos.

**Riesgos**

- Confiar en Host reenviado sin proxy confiable.
- Usar `stores.slug`, que no es global.
- Cookies/sesión compartidas en localhost.
- Activar accidentalmente una tienda sin catálogo/configuración.

**Criterios de aceptación**

1. Una URL pública determina exactamente una Store/Tenant activa sin request IDs.
2. Cambiar host/handle no conserva acceso a recursos de la Store anterior.
3. Admin continúa usando sus middlewares y pantallas sin cambios funcionales.
4. No existe aún ninguna mutación de compra pública.

**Grupo de pantallas para un prompt**
Layout público + estado no disponible + shell de inicio.

---

## SF-02 — Catálogo público, producto, branding y contacto

**Estado confirmado (2026-10-28): implementado.** Essential v1.0 es el theme
default registrado y el catálogo read-only reutiliza Listings, pricing,
promociones, inventario y derivados Media autorizados. La implementación está
documentada en `docs/storefront/SF-02_ESSENTIAL_CATALOG.md`. Este estado no
inicia SF-03.

**Objetivo**
Entregar navegación read-only indexable usando exclusivamente Listings elegibles del Store contextual.

**Alcance**

- Inicio derivado de información existente.
- Catálogo paginado, búsqueda textual y categoría.
- Detalle de producto y selección de variante.
- Información/contacto.
- Galerías públicas y favicon/logo.
- Sin carrito en esta fase.

**Módulos involucrados**
Storefront, Stores, Listings, Catalog, Media, Pricing, Promotions, Inventory.

**Backend necesario**

- Queries públicas paginadas y DTOs batch.
- Elegibilidad estricta de Listing/Product/Variant.
- Precio efectivo y promoción desde servicios actuales.
- Disponibilidad server-side como estado de presentación.
- Categorías activas intersectadas con Listings de la Store.
- Controlador público de derivados Media con URL versionada.
- Metadata fallback por Store/producto/categoría.

**Frontend necesario**

- Inicio, catálogo/búsqueda/categoría, detalle de producto y contacto.
- Componentes compartidos: header, footer, product card, price, availability, gallery, variant selector, pagination y empty state.
- Variables CSS sanitizadas desde colores Store Settings; contraste accesible con fallback.
- No reutilizar componentes visuales B2B salvo primitivas realmente neutras.

**Pruebas**

- Sólo Listings PUBLISHED y variantes activas/habilitadas/con precio.
- Slug repetido en otra Store no produce fuga.
- Producto/categoría/media de otro Tenant no aparece por ID ni relación manipulada.
- Precio/promoción vigentes y moneda correcta con precisión 4.
- Variante sin stock y cambios de estado.
- Media READY referenciada; master y assets no referenciados denegados.
- Paginación/filtros validados y presupuesto de queries.
- Render responsive y typecheck/i18n/build.

**Riesgos**

- N+1 al calcular precio/promoción/stock/media.
- Exponer SKU/barcode/stock exacto sin decisión de producto.
- Categorías tenant-wide que no tengan oferta en la Store.
- Cachear promoción o stock por demasiado tiempo.

**Criterios de aceptación**

1. Una Store sólo muestra oferta publicada propia y relaciones tenant-safe.
2. Precio, descuento, moneda y disponibilidad provienen de Laravel.
3. Ninguna URL pública entrega masters Media.
4. Catálogo funciona en mobile, vacío y paginado.

**Grupo de pantallas para un prompt**
Inicio + catálogo/búsqueda/categorías + detalle + contacto, compartiendo el mismo módulo de proyección.

---

## SF-03 — Carrito invitado seguro

**Objetivo**
Permitir construir y recuperar en el mismo navegador un carrito aislado por Store.

**Alcance**

- Crear carrito al primer agregado.
- Agregar, actualizar, eliminar y vaciar líneas.
- Mostrar resumen dinámico, promociones, impuestos incluidos y problemas.
- Recuperación por cookie hasta TTL.
- Sin cuenta, Customer automático ni checkout todavía.

**Módulos involucrados**
Storefront, Cart, Listings, Pricing, Promotions, Inventory, Tax, Stores.

**Backend necesario**

- Adaptador de posesión por token opaco; preferible digest persistido.
- Cookie cifrada host-only y namespacing seguro para ruta local.
- Form Requests públicos y rate limits.
- Re-resolución Store+Listing+Variant antes de servicios Cart.
- DTO de `CartSummary`; traducción de códigos de issue a mensajes públicos sin ocultar el código interno en logs seguros.
- Rotación/revocación al expirar o convertir.

**Frontend necesario**

- Drawer/indicador opcional y página Carrito.
- Estados: cargando, vacío, agotado, despublicado, precio cambiado y configuración fiscal inválida.
- Las cantidades son input; importes retornan del servidor.

**Pruebas**

- CSRF y rate limit.
- Token incorrecto, expirado, convertido y de otra Store/Tenant.
- Manipulación de listing/variant y cantidad límite.
- Reprecio/promoción/stock después de cambios administrativos.
- Cookie no expuesta a JS y sin dominio compartido.
- Concurrencia básica de dos actualizaciones sobre la misma línea.

**Riesgos**

- El token actual se persiste en claro.
- Cart fixation o credencial filtrada a logs.
- Mostrar preview válido y perder stock antes del Checkout; debe explicarse que la reserva ocurre después.
- Configuración Tax incompleta bloquea un carrito válido comercialmente.

**Criterios de aceptación**

1. El comprador sólo puede mutar el carrito poseído dentro de la Store actual.
2. El servidor ignora cualquier precio/descuento/impuesto enviado.
3. Refrescar o volver en el mismo navegador recupera el carrito vigente.
4. Otra Store/host no puede leerlo aunque reciba su UUID.

**Grupo de pantallas para un prompt**
Integración “agregar al carrito” del detalle + indicador/drawer + página Carrito.

---

## SF-04 — Checkout invitado y creación idempotente de Order

**Objetivo**
Convertir un Cart válido en una Order inmutable preservando validaciones, snapshots y reservas.

**Precondiciones de producto**

- Decidir inventario tenant-wide versus ubicaciones por Store.
- Decidir Shipping/Pickup y confirmar que shipping cero es aceptable o implementar su dominio en una fase separada.
- Decidir qué término de pago permite colocar la orden.
- Aprobar política de PII, consentimiento y retención.

**Alcance**

- Inicio/reanudación de un Checkout operativo por Cart.
- Contacto y snapshots shipping/billing.
- Revisión server-side.
- Preparación READY, reserva y expiración.
- Creación idempotente de Order.
- Sin registro de comprador y sin proveedor online.

**Módulos involucrados**
Storefront, Cart, Checkout, Orders, Inventory, Tax, Pricing, Promotions, Customers opcionalmente.

**Backend necesario**

- Autorización de Checkout por Store + credencial de carrito/checkout.
- Form Request público con normalización y mensajes apropiados.
- Claves de idempotencia por intento; protección contra payload distinto si aplica.
- Orquestador transaccional `PrepareCheckout` → `CreateOrderFromCheckout`, respetando la separación existente.
- Política explícita de expiración/cancelación y release de reservas.
- No crear/adjuntar Customer en MVP salvo decisión aprobada.
- Credencial de acceso a confirmación rotada después de Order.

**Frontend necesario**

- Checkout agrupado: contacto, entrega/dirección, facturación, revisión y submit.
- Estado de reserva/expiración y recuperación ante errores reintentables.
- Nunca marcar éxito sólo por estado local; obtener Order desde servidor.

**Pruebas**

- Guest con `customer_id = null` de extremo a extremo.
- Contacto/dirección inválidos; shipping/pickup según decisión.
- Tax/price/promotion/stock cambian antes de preparar.
- Reservas exactas, insuficiencia concurrente, expiración y liberación.
- Doble submit crea una sola Order.
- Snapshot decimal/tax se copia sin recalcular.
- Cross-store/cart/checkout/order y token inválido.
- PII no aparece en logs, notificaciones operativas ni URLs.

**Riesgos**

- Aceptar orden sin método de pago comprensible.
- Reserva de 15 minutos y mala UX ante expiración.
- Shipping cero puede ser legal/comercialmente incorrecto.
- La asignación usa cualquier almacén activo del Tenant.

**Criterios de aceptación**

1. Un Cart guest válido produce exactamente una Order CONFIRMED.
2. Totales y tax snapshots coinciden a escala 4.
3. Stock queda reservado y la Order conserva reservas según contrato existente.
4. No se crea una identidad Customer insegura.
5. Reintentos no duplican Checkout, Order ni reservas.

**Grupo de pantallas para un prompt**
Checkout completo (contacto + direcciones + revisión) como una sola experiencia modular.

---

## SF-05 — Pago soportado, confirmación y acceso seguro al pedido

**Objetivo**
Comunicar el siguiente paso real de pago y permitir ver una orden sin enumeración ni sesión administrativa.

**Precondición**
Elegir una opción explícita. Ninguna está automáticamente aprobada:

1. **COD** usando configuración existente, si el negocio lo permite.
2. **Transferencia pendiente**, sólo después de modelar instrucciones y flujo público.
3. **Catálogo/orden sin cobro online**, con procesamiento manual comunicado.
4. Proveedor online: requiere una fase propia futura, no se incluye aquí.

**Alcance**

- Configurar plan de pago soportado al crear la Order.
- Página de confirmación con estado real.
- Token de acceso a Order almacenado como digest, revocable y store-scoped.
- Email/magic link si se aprueba el canal.
- Mostrar tracking sólo cuando exista y sea seguro.

**Módulos involucrados**
Storefront, Orders, Payments, Fulfillment, Notifications/Mail sólo si se aprueba email.

**Backend necesario**

- Orquestación de `ConfigureOrderPaymentTerms` cuando corresponda.
- Modelo/tabla de accesos públicos a Order o contrato equivalente.
- Proyección pública de Order/Shipment sin datos internos.
- Rate limit, expiración/revocación y respuesta uniforme.
- No crear Payments/Captures ficticios.

**Frontend necesario**

- Confirmación, instrucciones de siguiente paso y estado básico.
- Consulta segura opcional desde magic link.
- No mostrar links admin, referencias externas privadas, historial interno o artifacts fiscales.

**Pruebas**

- Token válido/incorrecto/expirado/revocado y de otra Store/Tenant.
- Número de orden o email aislados no autorizan acceso.
- COD habilitado/deshabilitado y depósito decimal.
- Estados payment/fulfillment/tracking proyectados correctamente.
- `noindex`, `no-store`, referrer policy y ausencia de token en analytics.

**Riesgos**

- Confundir “orden confirmada” con “pago capturado”.
- Filtrar PII o tracking.
- Magic link reenviado; limitar alcance y permitir revocación.

**Criterios de aceptación**

1. La UI describe sólo un método realmente soportado.
2. Un tercero no puede consultar una Order con datos predecibles.
3. La consulta nunca cruza Store/Tenant ni expone superficies admin.
4. Estados mostrados proceden de Orders/Payments/Fulfillment.

**Grupo de pantallas para un prompt**
Confirmación + consulta segura + estado/tracking de pedido.

---

## SF-06 — SEO, SSR y rendimiento medido

**Objetivo**
Hacer indexables y rápidas las páginas read-only sin poner en riesgo aislamiento o frescura comercial.

**Alcance**

- Canonical, metadata, Open Graph, JSON-LD, robots y sitemap por Store.
- Evaluar e implementar Inertia SSR en el mismo proyecto si las pruebas operativas lo aprueban.
- Optimización de queries, imágenes y Core Web Vitals.
- Caché selectiva sólo con evidencia.

**Módulos involucrados**
Storefront, Stores, Listings, Catalog, Media, Pricing/Promotions para límites de TTL.

**Backend necesario**

- Generadores store-scoped de metadata, sitemap y robots.
- Cache keys con Store/version/locale/filtros y purge específico.
- Headers diferenciados: público para contenido versionado; no-store para compra/PII.
- Instrumentación de query count y tiempos sin registrar PII.

**Frontend necesario**

- Head tags por página, responsive images, dimensiones y lazy loading.
- Reducir JS de Storefront; evitar importar navegación/admin.
- Skeletons sin layout shift y controles mobile accesibles.

**Pruebas**

- Canonical correcto por subdominio/dominio alterno.
- Sitemap nunca mezcla Stores ni incluye despublicados.
- Cache hit de una Store no responde contenido de otra.
- SSR hydration y fallback cliente.
- Lighthouse/performance budget acordado y query budget.

**Riesgos**

- Cache cross-tenant.
- HTML SSR con contexto persistente en proceso largo.
- Metadata stale tras despublicar.
- Cachear precio/stock como contenido estable.

**Criterios de aceptación**

1. HTML inicial contiene metadata/canonical correctos.
2. Páginas privadas son noindex/no-store.
3. Tests demuestran aislamiento de caché.
4. Presupuestos mobile acordados se cumplen en datos representativos.

**Grupo de pantallas para un prompt**
No nuevas pantallas; mejora transversal de Inicio/Catálogo/Detalle/Contacto.

---

## SF-07 — Dominios personalizados

**Objetivo**
Permitir hosts del comerciante sin duplicar Storefront ni debilitar la resolución.

**Alcance**

- Alta administrativa de dominio, challenge DNS, verificación y canonical.
- Integración con la infraestructura TLS/proxy elegida.
- Redirect de hosts alternos y desactivación segura.
- No automatizar proveedor DNS/TLS hasta elegirlo explícitamente.

**Módulos involucrados**
Stores y Storefront; UI administrativa de Store sólo en la extensión aprobada.

**Backend necesario**

- Estado de lifecycle y verificación idempotente.
- Job seguro store/tenant-aware si la verificación es asíncrona.
- Protección contra takeover y hostname reservado.
- Auditoría de cambios administrativos.

**Frontend necesario**

- Una sección administrativa específica para dominio, no rediseño general.
- Estados e instrucciones DNS del proveedor elegido.

**Pruebas**

- Propiedad, expiración/revocación, colisión y takeover.
- Host unicode/punycode, case, punto final y puerto.
- Tenant/Store inactivos y dominio activo.
- Canonical/redirect y cookies host-only.

**Riesgos**

- Operación TLS fuera de Laravel.
- DNS stale y reasignación de dominios.
- SEO duplicado durante cambio de canonical.

**Criterios de aceptación**

1. Sólo un dominio verificado resuelve una Store.
2. Perder verificación deshabilita acceso según política.
3. Canonical y cookies no mezclan hosts.
4. Subdominio de plataforma sigue como fallback controlado.

**Grupo de pantallas para un prompt**
Únicamente configuración administrativa de dominio y estados de verificación.

---

## SF-08 — Identidad de comprador y recuperación cross-device (post-MVP opcional)

**Objetivo**
Añadir cuentas sólo si los datos de uso justifican recuperación, libreta de direcciones e historial.

**Alcance**

- Diseñar identidad de comprador separada de membresías administrativas y de Customer comercial.
- Login/magic link, recuperación, ownership de Orders, merge de Cart y direcciones guardadas.
- No reutilizar automáticamente `users` ni crear JWT.

**Módulos involucrados**
Nuevo límite de identidad comprador por decidir, Customers, Cart, Checkout, Orders, Stores.

**Backend necesario**

- Threat model y decisión de modelo de identidad.
- Sesión/cookie Laravel, rate limits, verificación y anti-enumeración.
- Vínculo explícito y tenant-safe Buyer ↔ Customer, si se aprueba.
- Reglas de merge y revocación de sesiones.

**Frontend necesario**

- Registro/login/recuperación, cuenta, direcciones y pedidos.
- Mantener guest checkout disponible salvo decisión contraria.

**Pruebas**

- Account takeover, enumeración, sesión, CSRF y rate limits.
- Una identidad en varias Stores/Tenants según política aprobada.
- Merge concurrente de carritos y ownership histórico.

**Riesgos**

- Rediseñar Auth prematuramente.
- Confundir empleado, User, Customer y comprador.
- Aumentar obligaciones de privacidad/retención.

**Criterios de aceptación**

Se definirán sólo tras aprobar el ADR de identidad; esta fase no es parte del MVP inicial.

**Grupo de pantallas para un prompt**
Auth comprador + Cuenta/Direcciones/Pedidos, después del ADR y nunca junto con Auth admin.

## 4. Primera fase recomendada

Comenzar por **SF-01 — Fundaciones públicas y resolución de Store**. Es la dependencia de todas las demás y reduce el mayor riesgo: que una página pública determine el Tenant/Store desde input manipulable o desde una sesión administrativa.

Antes de codificar SF-01 deben cerrarse sólo estas decisiones:

1. Dominio base de plataforma y nombres reservados.
2. Forma del handle público global.
3. Campo/estado que habilita el Storefront.
4. Si la primera entrega se probará únicamente en `/tienda/{publicHandle}`.

SF-01 no debe incluir catálogo, carrito ni Checkout. Su entrega es pequeña, comprobable y permite revisar aislamiento antes de exponer datos comerciales.

## 5. Gates de producto antes de SF-04

No iniciar la compra pública hasta documentar:

- alcance de almacenes por Store;
- shipping/pickup y costo real;
- método/términos de pago admitidos;
- texto de consentimiento, privacidad y retención;
- canal de confirmación de Order;
- tratamiento de Customer guest.

Si esos gates no están aprobados, el Storefront puede avanzar hasta un catálogo read-only (SF-02) sin simular capacidades.

## 6. Matriz resumida de entregas

| Fase | Entrega visible | ¿Migración probable? | ¿Acepta dinero/orden? |
|---|---|---:|---:|
| SF-01 | URL pública y branding base | Sí | No |
| SF-02 | Inicio, catálogo, producto, contacto | No necesariamente | No |
| SF-03 | Carrito invitado | Probable para digest de token | No |
| SF-04 | Checkout y Order | Posible para acceso público | Sí, sólo tras gates |
| SF-05 | Confirmación/pago soportado/consulta | Sí | Según decisión |
| SF-06 | SEO/SSR/performance | No necesariamente | No cambia flujo |
| SF-07 | Custom domains | Ya previsto en SF-01 o extensión | No cambia flujo |
| SF-08 | Cuenta comprador | Sí | Post-MVP |

## 7. Trabajo deliberadamente fuera del plan MVP

- Microservicios, frontend separado, Nuxt o React.
- Supabase/Auth externo, JWT o refresh tokens.
- Nuevas pasarelas o webhooks sin proveedor elegido.
- Shipping rates, carriers, labels o tracking ingestion automáticos.
- CMS completo, reviews, wishlists y marketplace.
- Devoluciones o refunds autoservicio.
- Generación fiscal automática, PDF fiscal o SUNAT producción.
- Redis/Horizon/CDN añadidos sólo por disponibilidad técnica.

## 8. Condición de cierre de Fase 27

Fase 27 queda limitada a estos documentos. Ninguna fase SF está iniciada, ninguna decisión pendiente se considera aprobada y no se autoriza implementación automática.
