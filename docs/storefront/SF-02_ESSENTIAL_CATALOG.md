# SF-02 — Catálogo público Essential v1.0

**Estado:** implementado
**Fecha:** 2026-10-28
**Alcance:** Home, catálogo, categorías, búsqueda, paginación, producto, variantes, precio/promoción, disponibilidad, media y branding; sin carrito ni checkout

## 1. Referencias Stitch verificadas

Se inspeccionaron mediante Stitch MCP las seis pantallas del proyecto `13874730587997489426`, sus exportaciones HTML y capturas. Stitch no fue modificado y ninguna pantalla ADM se usó como referencia pública.

| Pantalla | Screen ID | Exportación física | Uso |
|---|---|---:|---|
| ESSENTIAL-01 Home Desktop | `4e843ab1251747b58ce0dbed322e5741` | 2560×5708 | Header, hero, categorías, grilla y footer |
| ESSENTIAL-02 Home Mobile | `2697a265383347ef88cd6b6b9af624ed` | 780×7448 | Navegación, búsqueda y ritmo mobile |
| ESSENTIAL-03 Catalog Desktop | `f03e75b16f96440596a17c7ec1c09725` | 2560×6754 | Cabecera, categorías, cards y paginación |
| ESSENTIAL-04 Catalog Mobile | `cde38245d735470a8df22323193142aa` | 780×4380 | Chips horizontales y grilla compacta |
| ESSENTIAL-05 Product Detail Desktop | `d96695320f6c49e6a377c1e85ebebf48` | 2560×5026 | Breadcrumbs, galería, precio y opciones |
| ESSENTIAL-06 Product Detail Mobile | `b11c0e49d7f8465683c5bc148f76c56e` | 780×4862 | Apilado mobile y targets táctiles |

Las dimensiones son exportaciones de alta densidad; se implementaron breakpoints reales para 1440 px, 390 px y mínimo 320 px. Se conservaron Inter, superficies claras, bordes, radios y jerarquía Essential. Se retiraron datos ficticios del export: `NOVA STORE`, temporada 2025, beneficios, filtros de disponibilidad, carrito, productos complementarios y textos promocionales.

## 2. Arquitectura multi-theme

`Storefront` continúa como capa pública de proyección; Catalog, Listings, Pricing, Promotions, Inventory, Media y Stores conservan datos y reglas.

- `config/storefront-themes.php` y `ThemeRegistry` mantienen un allowlist estático. `essential` está disponible, es default y fallback seguro.
- `resources/js/storefront/themeRegistry.ts` importa páginas conocidas estáticamente. Un valor de base de datos nunca se transforma en ruta de importación.
- `resources/js/storefront/themes/essential/` contiene layout, páginas y componentes.
- Una segunda plantilla se agrega a ambos registros y consume los mismos DTO públicos; no duplica servicios comerciales.

No se creó tabla, marketplace, licencia, cobro o editor de themes. Como Store aún no tiene selección persistida, `essential` es siempre el theme efectivo.

FASE 29.1 presenta este mismo theme efectivo como **Essential** en Store
Settings. El aprovisionamiento automático no añade selección persistida ni
altera el registro de themes.

## 3. Endpoints públicos

Todos conservan los middlewares `storefront.development` y `storefront.public` de SF-01: sólo local/testing, flag explícito y Host permitido.

```text
GET /tienda/{publicHandle}
GET /tienda/{publicHandle}/productos?q=
GET /tienda/{publicHandle}/categorias/{categorySlug}?q=
GET /tienda/{publicHandle}/productos/{listingSlug}
GET /tienda/{publicHandle}/media/{derivativeChecksum}/{thumbnail|medium}
```

Los parámetros públicos no eligen Tenant o Store. `PublicStoreContext` deriva ambos desde la publicación y cada slug se consulta nuevamente dentro de ese contexto.

## 4. Visibilidad y proyecciones

Un producto aparece sólo con Listing `PUBLISHED` de la Store contextual, Product `ACTIVE`, una variante `ACTIVE` habilitada y una lista efectiva `ACTIVE` con precio. Las categorías son tenant-wide, pero sólo se proyectan si están `ACTIVE` y contienen oferta elegible de esa Store. El filtro usa categoría exacta, sin descendientes implícitos.

La búsqueda consulta nombre y descripción (máximo 100 caracteres). La paginación es server-side, 12 Listings por página. Home usa una regla derivada segura: hasta cuatro categorías y los ocho Listings elegibles publicados más recientemente; no existe merchandising ficticio.

## 5. Precios, promociones y disponibilidad

`PublicCatalog` reutiliza `ListingPriceResolver::priceListsFor()`, `EffectivePriceCalculator::calculateMany()` agrupado por lista y `TenantInventoryAvailability::forVariants()`. Laravel entrega strings decimales escala 4, moneda, base, final, descuento, promoción ganadora y disponibilidad. Vue sólo presenta resultados; no recalcula reglas.

No se exponen códigos internos, SKU, barcode ni stock exacto en UI. Inventario continúa tenant-wide, según el contrato actual. Una política de almacenes por Store sigue siendo gate previo a SF-04.

## 6. Media pública

El endpoint acepta un checksum SHA-256 de derivado y sólo `THUMBNAIL`/`MEDIUM`; nunca acepta IDs ni sirve master. Valida Tenant/Store, Media `READY`, branding actualmente asociado o Product/Variant alcanzable desde un Listing elegible. Para variante exige estado activo, habilitación y precio.

Las respuestas usan `nosniff`, CSP sandbox y `Cache-Control: private, no-store`. No se cachean para garantizar revocación. Una CDN/caché inmutable requerirá URL de relación versionada y purge store-specific en SF-06.

## 7. Branding, componentes y responsive

`StoreSettings` autoriza nombre, descripción, logo/favicon, contacto, dirección, redes y colores. Store autoriza moneda, timezone y locale. Los defaults son Primary `#0F172A`, Accent `#2563EB`, Secondary `#0284C7`, Background `#F8FAFC`, Surface `#FFFFFF`, Border `#E2E8F0` e Inter.

No se inventan hero editable, tipografía, orden, banners o políticas. El hero usa nombre/descripcion y una composición CSS. Essential incluye `StoreHeader`, `StoreFooter`, `HeroSection`, `CategoryCard`, `ProductCard`, `ProductGrid`, `PriceDisplay`, `AvailabilityBadge`, `ProductGallery`, `VariantSelector`, `SearchBar`, `PublicPagination` y `EmptyState`.

Hay skip link, foco visible, targets de 44 px, imágenes dimensionadas/lazy, grillas 2/3/4 columnas y scroll controlado. Los mensajes existen en `es-PE` y `en`; nombres persistidos no se traducen.

## 8. SEO, rendimiento y pruebas

Home/producto emiten título y descripción disponible; búsqueda usa `noindex,follow`. Canonical, sitemap, robots completos, Open Graph, JSON-LD y SSR quedan en SF-06.

Cards reciben DTO mínimos sin variantes/galerías. La cobertura valida Home, catálogo, categoría, búsqueda, paginación, detalle, precio, promoción, disponibilidad, media, despublicación, Product archivado, aislamiento cross-Store/cross-Tenant, branding, props sensibles, Host/Store/Tenant/publicación SF-01, theme fallback y un presupuesto de 35 consultas por página completa.

`RefreshDatabase` sólo se ejecuta dentro de Compose después del guard `pgsql.testing/commerce_testing`. La migración SF-01 está registrada como `Ran` en el batch 2 de `pgsql/commerce`; durante la consolidación sólo se verificó su estado y no se volvió a ejecutar.

## 9. Limitaciones y preparación para SF-03

- No hay carrito, checkout, Order, pago, shipping, ratings, reviews, filtros avanzados ni ordenamiento.
- El selector sólo actualiza precio, disponibilidad y galería ya resueltos por Laravel; no simula agregar.
- No hay CMS de hero/colecciones ni selección persistida de theme.
- La ruta sigue siendo temporal y no sirve producción; dominios pertenecen a SF-07.
- SF-03 puede reutilizar contexto, URLs y DTOs. Debe agregar posesión guest por cookie opaca, revalidación server-side y mutaciones CSRF.
