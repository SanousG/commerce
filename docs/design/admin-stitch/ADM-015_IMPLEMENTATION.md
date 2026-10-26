# ADM-015 — Edit Product implementation

Fecha: 2026-10-25. Fases: 26.9 y 26.11.

## Referencias

- Proyecto Stitch: `projects/13874730587997489426`.
- Desktop funcional: `0e30c4efc15744069f6c01dc74b6689b`.
- Mobile funcional: `7c709b3ef7f94832856782397306bd93`.
- Design System: Executive Precision B2B.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

Los artefactos Stitch se utilizaron como orientación visual. Laravel, sus
FormRequests, servicios, permisos y tests permanecen como fuente de verdad.

## Contrato funcional auditado

Product y todas las relaciones administradas aquí son Tenant-wide. ADM-015 usa
`TenantContext`; no requiere ni recibe `StoreContext`.

### Props Inertia

- Product: atributos canónicos, `category_ids`, Options con Values, Variants,
  galerías Product/Variant y precio de la lista default cuando está autorizado.
- Category disponibles: no archivadas, Tenant-scoped, con `parent_id`.
- TaxCategory: solo categorías ACTIVE cuando el usuario puede ver o gestionar
  impuestos. La modificación exige `taxes.manage`.
- Pricing: lista default, moneda y `can_update`, solo con `pricing.view`.
- Media: URLs privadas del derivado THUMBNAIL de 300 px, solo con `media.view`.

El controller ya usa eager loading para Categories, Options/Values,
ProductMedia/MediaAsset, Variants/OptionValues/Media y precios filtrados a las
Variants del Product. FASE 26.11 no amplió consultas ni payload.

### Operaciones independientes conservadas

| Operación | Método y endpoint | Autorización/contrato |
|---|---|---|
| Product | `PUT /admin/products/{product}` | `products.update`; nombre, descripción, estado, TaxCategory autorizada y Categories |
| Crear Option | `POST /admin/products/{product}/options` | `products.update`; nombre + 1–50 Values |
| Crear Variant | `POST /admin/products/{product}/variants` | `products.update`; identificadores, estado, default y Option Values |
| Actualizar Variant | `PUT /admin/products/{product}/variants/{variant}` | `products.update`; una Variant por request |
| Adjuntar/actualizar Product image | `POST /admin/products/{product}/media` | `products.update` + `media.view` |
| Separar Product image | `DELETE /admin/products/{product}/media/{asset}` | `products.update` + `media.view` |
| Orden/primary Product gallery | `PATCH /admin/products/{product}/media-order` | `products.update` + `media.view` |
| Adjuntar/separar/reordenar Variant image | `/admin/product-variants/{variant}/media*` | `products.update` + `media.view` |
| Guardar precio | `PUT /admin/pricing/price-lists/{list}/variants/{variant}` | `pricing.update`; decimal string, máximo 4 decimales |
| Quitar precio | `DELETE` sobre el mismo recurso | `pricing.update` |

No existen endpoints para editar, reordenar o borrar Options; por ello se
presentan como resúmenes read-only. Tampoco existen bulk save, generación de
combinaciones o asignación automática de Media por Color.

## Rediseño visual — FASE 26.11

### Encabezado y navegación

- Breadcrumb `Catalog / Products / {Product} / Edit`.
- PageHeader con nombre real, contexto `All company` y regreso a Products.
- Metadata compacta para Status, slug, default SKU, número de Variants e
  imágenes de Product.
- Navegación ligera por anclas a Product information, Options, Variants,
  Product images y Default pricing. No es sticky ni sustituye el drawer.
- Flash compartido mediante `StatusNotice` después de cada operación.

### Product information

- Card blanca con nombre, descripción, Status, TaxCategory autorizada y
  `ProductCategorySelector`.
- Contadores reales de límites, errores junto al campo y estado read-only.
- Categories conserva selección múltiple, jerarquía, empty state, RBAC y
  validación Tenant-scoped.
- Existe una sola acción general `Save product`; no hay Save Details/Taxonomy
  duplicados.

### Product options

- Resúmenes compactos de Options y Values existentes.
- Formulario independiente Add option con sus propios datos, processing y
  errores de `name`, `values` y `values.*`.
- No se muestran operaciones de mutación inexistentes.

### Product variants

- Formulario independiente para crear una Variant.
- Cada Variant es una card y conserva su propio `PUT`, processing y mapa de
  errores.
- Campos reales: display name, SKU, Barcode, Status, default y Option Values.
- `StatusBadge`, default marker y SKU técnico facilitan el escaneo.
- No hay bulk save ni combinaciones automáticas.

### Product y Variant images

- Galería Product con thumbnail privado, primary, orden, ALT contextual y
  detach. Detach no elimina el MediaAsset.
- Galería Variant con orden, detach y selector de Media independiente.
- `MediaPicker`, `MediaUploader` y `MediaThumbnail` usan tokens oficiales; el
  upload continúa aceptando exclusivamente JPEG, PNG y WebP, máximo 10 MB.
- El picker busca solo `type=IMAGE`; no ofrece Documents, SVG o video.
- Las imágenes visibles usan `thumbnail_url`, nunca master/original.
- Los controles muestran processing y errores en la galería correspondiente.

### Variant pricing

- Contexto real de lista default y moneda.
- Un formulario independiente por Variant, con precio actual, input técnico,
  Save y Remove según `pricing.can_update`.
- `formatPrice` conserva visualización scale-4 y el servidor continúa validando
  hasta cuatro decimales.
- No se presenta precio global de Product.

## Estado frontend y respuestas Inertia

Se identificaron y corrigieron dos riesgos de la pantalla anterior:

1. Cada Variant montaba un `MediaPicker` aunque su `<details>` estuviera cerrado,
   provocando una solicitud `/admin/media` por Variant al abrir ADM-015. Ahora
   no se monta ningún picker inicialmente y solo existe uno por selector abierto.
2. Product ALT y Variant galleries podían quedar desfasados o perder edición
   local después de una respuesta de otra sección. Ahora ProductMedia,
   Variant edits y Price inputs tienen estado local; cada éxito resincroniza
   únicamente el recurso afectado y preserva los demás borradores. Cuando una
   Variant pasa a ser default, se resincroniza además ese flag en todas las
   Variants porque la operación real desmarca la default anterior.

Todas las mutaciones Inertia usan `preserveScroll` y `preserveState`. Product,
Add Option y Create Variant conservan `useForm`; Variant updates, Media y Price
mantienen processing/error scoped por entidad u operación.

No se añadió watcher. Los únicos cálculos reactivos son permisos, default
Variant y resolución de errores de formulario.

## Desktop

- Contenido limitado a `max-w-7xl`; cards border-first y superficies blancas.
- Product information usa dos columnas en pantallas grandes.
- Variant fields escalan hasta cuatro columnas; Pricing usa una fila compacta
  por Variant.
- Galerías Product usan tres columnas como máximo, con thumbnails de 300 px
  servidos por el endpoint privado existente.
- Inter se usa en contenido y JetBrains Mono en slug, SKU, IDs técnicos,
  contadores y precios.

## Mobile 390

- Todas las secciones, formularios y cards colapsan a una columna.
- Botones principales usan ancho completo cuando mejora el target táctil.
- Options y Option Values usan chips con wrap; las galerías usan flex/grid sin
  anchos mínimos ni scroll horizontal.
- Acciones permanecen junto a su Product, Variant, imagen o precio.
- No hay barra inferior, acciones globales sticky ni tablas comprimidas.

## Componentes

### Reutilizados

- `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `StatusNotice`.
- `FieldError`, `ProductCategorySelector`.
- `MediaPicker`, `MediaUploader`, `MediaThumbnail`.

### Creados

Ninguno. No se extrajo `FormSection`: las cinco secciones comparten lenguaje
visual, pero sus contratos de formulario, permisos y acciones son distintos; un
wrapper genérico aportaría poca reutilización y ocultaría esas fronteras.

## Rendimiento

- Backend, query count y payload: sin cambios.
- MediaPicker pasa de N+1 montajes iniciales (Product + cada Variant) a cero
  solicitudes iniciales; carga únicamente al abrir Product Library o una
  Variant Library. Esta es una comparación de comportamiento de requests, no
  una afirmación porcentual de tiempo.
- Bundle previo de la ruta Product Edit: 15.60 kB, 4.56 kB gzip.
- Bundle final de la ruta Product Edit: 36.70 kB, 8.39 kB gzip.
- Chunk compartido MediaPicker final: 3.68 kB, 1.57 kB gzip.
- Chunk compartido MediaUploader final: 53.60 kB, 20.18 kB gzip; ya incluía la
  tubería Axios/upload y continúa cargándose como chunk compartido.
- El aumento de Edit corresponde a estado scoped, errores y toda la estructura
  responsive; no se añadieron dependencias.

## Pruebas

- Guard de conexión: 1 test, 4 assertions; confirma
  `pgsql.testing/commerce_testing`.
- Catalog: 40 tests, 216 assertions.
- Media: 12 tests, 92 assertions.
- Pricing: 27 tests, 142 assertions.
- `npm run typecheck`: correcto.
- `npm run build`: correcto; permanece el aviso no bloqueante del paquete
  opcional `fontaine`, no instalado.
- `git diff --check`: correcto.

La cobertura incluye Product/Categories, Options, Variants y default Variant,
Product/Variant galleries, primary/order/ALT/reuse, JPEG/PNG/WebP, referencias
protegidas, precios scale-4, RBAC y aislamiento cross-tenant. El repositorio no
tiene tests de componentes Vue configurados, por lo que responsive, foco y
processing visual requieren revisión manual.

## Diferencias frente a Stitch

- Se descartaron Save Details y Save Taxonomy de la referencia mobile: Laravel
  tiene un único update general y la fase exige una sola acción Save product.
- No se muestran URL slug editable, Entity IDs sintéticos, capabilities
  decorativas, dimensiones de imagen ausentes del prop ni publicación de
  storefront no respaldada.
- Existing Options permanecen read-only porque no hay endpoints update/delete.
- No se crean combinaciones ni asignaciones por Color.
- Pricing usa únicamente la lista default enviada por Laravel y precios por
  Variant.
- Product Gallery aparece después de Variants, siguiendo el workbench funcional
  canónico y manteniendo todas las acciones en su frontera real.

## Pendientes

No hay navegador autenticado disponible en el harness. Queda pendiente la
revisión visual manual a 1280, 1440 y 390 px, incluyendo:

- foco y navegación por teclado/anclas;
- Product sin Options, Media o Pricing visible;
- muchas Variants, Options y Categories;
- errores simultáneos y textos largos;
- upload/progress y búsquedas vacías;
- reordenamiento, primary, detach y ALT;
- permisos read-only;
- preservación visual de borradores entre mutaciones.
