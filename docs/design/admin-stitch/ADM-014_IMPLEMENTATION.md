# ADM-014 — Create Product implementation

Fecha: 2026-10-25. Fases: 26.9 y 26.10.

## Referencias visuales

- Proyecto Stitch: `projects/13874730587997489426`.
- Desktop canónico: `007ac28b970744b894215b10aaf0dc08`.
- Mobile canónico: `ee284fbb10ce419aa2bc1546ad9f823d`.
- Design System: Executive Precision B2B mediante
  `ADMIN_DESIGN_TOKENS.md`.

## Contrato real

- Product pertenece al Tenant y admite cero o muchas Category mediante
  `category_product`; no existe categoría primaria.
- Category y TaxCategory son conceptos distintos. Create no modifica
  TaxCategory.
- `ProductController::create` entrega las Category no archivadas del Tenant con
  `id`, `name` y `parent_id`, ordenadas por posición y nombre.
- `CreateProductRequest` acepta `categories[]`, máximo 50 IDs UUID distintos, y
  exige que cada ID exista dentro del Tenant activo.
- `CreateProduct` valida otra vez el Tenant y sincroniza el pivot con
  `tenant_id` dentro de la misma transacción que crea Product y default Variant.
- Un array vacío es válido.

## Implementación funcional de Categories — FASE 26.9

- Create usa `ProductCategorySelector`, compartido con ADM-015.
- La sección Categories siempre es visible. Ya no desaparece cuando el Tenant
  no tiene categorías.
- Cada opción muestra nombre y contexto padre cuando el padre está disponible.
- Permite selección múltiple con checkboxes nativos, contador, foco visible y
  distribución responsive.
- `useForm.categories` conserva la selección ante errores de validación.
- Se muestra el error general o el primer error `categories.*`.
- El empty state explica que la asignación es opcional y enlaza a
  `/admin/categories/create` únicamente con `categories.create`.
- Breadcrumbs, PageHeader, controles y acciones usan los tokens Executive
  Precision B2B. No se añadieron librerías.

## Rediseño visual final — FASE 26.10

### Estructura

- Ancho máximo de 5xl para mantener líneas legibles a 1280 y 1440 px.
- Breadcrumb `Catalog / Products / Create` y PageHeader con contexto
  `All company`.
- Aviso informativo compacto que explica la redirección real al workbench Edit
  después de crear el Product.
- Un único formulario y un único submit, sin wizard ni pasos artificiales.
- Card border-first sin sombra, dividido en tres secciones numeradas:
  `General information`, `Identifiers` y `Catalog organization`.
- Cada sección usa una columna contextual de 13rem desde `md` y el contenido
  flexible restante; en mobile colapsa naturalmente a una sola columna.

### Campos y acciones

- Labels explícitos para requeridos y opcionales, ayuda contextual y contadores
  reales basados en los límites del FormRequest.
- Product name y Description usan Inter; SKU y Barcode usan JetBrains Mono.
- Inputs con `admin-control`, estados de error Danger, `aria-invalid` y
  asociación `aria-describedby` con ayuda y `FieldError`.
- Footer no fijo con Cancel secundario y Create product primario. Ambos usan
  ancho completo a 390 px y ancho automático desde `sm`.
- `form.processing` deshabilita el submit y muestra `Creating…`, conservando la
  prevención de doble envío de Inertia.

### Categories

- Se conserva el mismo `ProductCategorySelector` utilizado por Edit; no existe
  una variante específica de ADM-014.
- Se corrigió la semántica del fieldset con un `legend` accesible y se añadió
  `aria-invalid` cuando existe error, sin cambiar su contrato ni flujo.
- Continúan selección múltiple, jerarquía padre, contador, empty state, enlace
  RBAC y persistencia de selección.

### Componentes reutilizados

- `AdminLayout` y su drawer mobile.
- `Breadcrumbs`.
- `PageHeader`.
- `FieldError`.
- `ProductCategorySelector`.

No se extrajeron `FormSection` ni `FormActions`: ADM-014 es por ahora el único
formulario con esta composición visual estabilizada y ADM-015 todavía no debe
rediseñarse. Extraerlos ahora crearía una abstracción sin un segundo contrato
verificado.

## Desktop y mobile

### Desktop

- Las secciones separan Product, default Variant y organización sin aumentar el
  número de operaciones.
- SKU y Barcode comparten fila; el resto conserva el ancho disponible.
- La jerarquía visual usa bordes, escala 4/8/12/16/24/32 y los aliases oficiales
  slate/azul, sin beige ni verde como identidad.

### Mobile 390

- Secciones, campos y categorías se apilan en una columna.
- Inputs y acciones ocupan todo el ancho disponible.
- Los checkboxes mantienen toda la tarjeta como área de interacción y una
  altura mínima de 56 px.
- No hay ancho mínimo de contenido, tabla, barra inferior ni acción sticky; el
  drawer continúa siendo el compartido por `AdminLayout`.

## Validación

- Creación con dos categorías y `tenant_id` correcto en el pivot.
- Creación sin categorías.
- Rechazo de Category de otro Tenant.
- RBAC de creación existente conservado.
- Guard de base aislada: 1 test, 4 assertions, correcto contra
  `pgsql.testing/commerce_testing`.
- Suite Catalog: 40 tests, 216 assertions, correcta.
- `npm run typecheck`: correcto.
- `npm run build`: correcto; permanece el aviso no bloqueante sobre el paquete
  opcional `fontaine`, que no se instaló.
- `git diff --check`: correcto.

## Rendimiento

- No cambió el controller, FormRequest, payload, consulta ni operación
  transaccional.
- No se añadieron requests, imágenes, dependencias o componentes con estado
  remoto.
- Chunk de ruta ADM-014 después del rediseño: 8.48 kB, 2.74 kB gzip.
- `ProductCategorySelector` permanece como chunk compartido: 2.97 kB, 1.37 kB
  gzip.
- No existe baseline previo aislado de ADM-014 para afirmar una mejora o
  regresión porcentual de carga.

## Diferencias frente a Stitch

- Solo aparecen los campos reales `name`, `description`, `sku`, `barcode` y
  `categories`; no se añadió Category primaria, TaxCategory ni datos de ejemplo.
- El nombre del Tenant permanece en el header compartido; el contenido usa
  `All company` en lugar de duplicar un nombre estático dentro del formulario.
- Categories se asigna durante Create porque el backend real ya lo soporta; el
  aviso de flujo no repite la afirmación de Stitch de asignarlas únicamente en
  Edit.
- La agrupación pone Description junto a información general y SKU/Barcode en
  Initial variant, en vez de copiar literalmente el orden visual de Stitch.
- Las acciones mobile no son sticky y no existe barra inferior.

## Pendientes

No hay navegador autenticado disponible en el harness. Queda pendiente la
revisión visual manual a 1280, 1440 y 390 px, incluyendo foco por teclado,
errores largos, nombres y descripciones al límite, muchas Categories, empty
state y estado processing.
