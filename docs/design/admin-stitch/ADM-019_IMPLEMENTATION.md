# ADM-019 — Media Library: implementación FASE 26.16

## Alcance

Rediseño visual e internacionalización de `/admin/media` sin reconstruir el módulo Media. Se preservaron el backend Laravel, las rutas, los Form Requests, los permisos RBAC, `TenantContext`, el almacenamiento privado, los derivados y la integración con Product Edit/Variant Media.

Referencias Stitch usadas solo como guía visual:

- Desktop: `610e6ea35fde48f5a5e354b41f35c62d`.
- Mobile: `3d9e23a8003c458087575f372e44697c`.

La fuente de verdad funcional es el código Laravel/Vue existente.

## Auditoría funcional

| Punto | Contrato verificado |
|---|---|
| Formatos | JPEG, PNG y WebP reales, detectados con `fileinfo`, `getimagesize` y GD. SVG, GIF, BMP, TIFF, documentos, archivos corruptos y disfrazados se rechazan. |
| Límites | `config/media.php`: 10 MB, 6000×6000, 20 MP, página de 24. Todos configurables por entorno. |
| Almacenamiento | Disco privado `local`; master normalizado en `media/tenants/{tenant}/{media}/master.{ext}`; derivados `THUMBNAIL` (300 px) y `MEDIUM` (1200 px) en WebP. |
| Exposición | El índice solo lista `status = READY` y `type = IMAGE`. Serializa `id`, `type`, `title`, `alt_text`, `original_filename`, `mime_type`, `extension`, `size_bytes`, `width`, `height`, `checksum`, `created_at`, `usage_count`, `thumbnail_url`, `file_url`. |
| Campos no disponibles | No se serializan autor/uploader, estado, referencias individuales ni desglose de uso por marca. No se muestran en la UI. |
| Permisos | `media.view` para todos los roles; `media.upload`, `media.manage` y `media.delete` para OWNER/ADMIN. Rutas protegidas con `can:*`. |
| Búsqueda y paginación | `title`, `alt_text` y `original_filename` con `ilike`; paginación server-side de 24 con `withQueryString()`. |
| Edición | `PATCH /admin/media/{asset}` limitado a `title` (160) y `alt_text` (1000). No reemplaza el archivo físico. |
| Eliminación | `DELETE /admin/media/{asset}` bloqueado con HTTP 409 si existen referencias de producto, variante, logo o favicon. |
| Tenancy | `MediaAsset` pertenece al Tenant, no a la Store. Todas las consultas se resuelven con `TenantContext`; no se introdujo `StoreContext`. |

No existe documentación de FASE 21 en `docs/`; el contrato autoritativo es `app/Modules/Media/README.md`.

## Contrato funcional preservado

- Formatos y límites del backend sin cambios.
- Un solo archivo por subida; normalización, eliminación de EXIF/GPS y generación de derivados existentes.
- Búsqueda real y paginación Laravel.
- Edición exclusiva de title/ALT.
- Protección de referencias y respuesta 409.
- Entrega privada de masters y derivados mediante rutas autorizadas.
- Aislamiento cross-tenant.
- Asociaciones con productos y variantes.
- Carga diferida de `MediaPicker` en Product Edit.

## Implementación

### Desktop

- `AdminLayout`, `Breadcrumbs` (Catálogo → Biblioteca multimedia) y `PageHeader` con contexto "Toda la empresa".
- Acción "Subir imagen" visible solo con `media.upload`; abre un panel colapsable que monta `MediaUploader` únicamente cuando se necesita.
- Búsqueda por título, ALT o nombre de archivo con botón de búsqueda y limpieza.
- Grid responsive de 2/3/4 columnas con derivados `THUMBNAIL`, título o filename, filename mono, dimensiones, tamaño e indicador de uso de catálogo.
- Paginación server-side mediante `Pagination`.
- Detalle en `<dialog>` nativo con:
  - Edición de title/ALT bajo `media.manage`.
  - Errores de validación con `FieldError`.
  - Metadata técnica real.
  - Uso agregado de producto/variante.
  - Política de eliminación y acción de borrado bajo `media.delete`.
- `EmptyState` diferenciado para biblioteca vacía y búsqueda sin resultados.
- `StatusNotice` para confirmaciones y alertas locales de error.

### Mobile 390 px

- Grid de dos columnas con thumbnails proporcionales (`aspect-[4/3]`), metadatos truncados y acciones accesibles.
- Búsqueda y uploader apilados; botones con altura mínima de 36 px.
- Detalle en diálogo de ancho `calc(100vw - 1.5rem)`, altura máxima 90vh y scroll interno.
- Paginación compacta existente.
- Sin scroll horizontal, sin navegación inferior y con el drawer compartido de `AdminLayout`.

## Componentes

Reutilizados:

- `AdminLayout`
- `Breadcrumbs`
- `PageHeader`
- `StatusNotice`
- `Pagination`
- `EmptyState`
- `FieldError`
- `MediaThumbnail`
- `MediaUploader`
- `MediaPicker` (solo se añadió un `aria-label` traducido de selección; sigue montándose solo al abrir el selector en Product Edit)

`StatusBadge` no se usa porque el índice no serializa `status`; todos los assets listados son `READY` por contrato.

Modificados:

- `MediaThumbnail.vue`: nueva prop opcional `fluid` para thumbnails de grid; el comportamiento por defecto no cambia.
- `MediaUploader.vue`: prop opcional `maxImageMb` (default 10), validación cliente de tipo/tamaño, reseteo del input tras éxito, textos traducidos y ayuda con `{max}`. `MediaPicker` sigue funcionando sin pasar la prop.
- `MediaPicker.vue`: `aria-label` traducido para cada imagen seleccionable; sin cambios en fetch, paginación, upload ni carga diferida.
- `Index.vue`: rediseño completo.
- `messages.ts`: catálogo `media.*` completo en es-PE/en.
- `validate-i18n.mjs`: aserciones exactas de title, upload, deleteConfirm, fileHelp y usageCount.
- `lang/es-PE/validation.php` y `lang/en/validation.php`: atributos `title`, `alt_text` y `file`.

## Internacionalización

- Español (Perú) es el idioma predeterminado y fallback; inglés es alternativo.
- Se tradujeron títulos, descripciones, búsqueda, estados vacíos, uploader, metadata, detalles, política de borrado, confirmaciones, errores y textos accesibles.
- Confirmación exacta:
  - es-PE: `¿Deseas eliminar esta imagen?`
  - en: `Do you want to delete this image?`
- No se traducen nombres ni títulos introducidos por usuarios.
- Paridad de claves y placeholders validada por `npm run test:i18n` (471 mensajes Vue).
- Los avisos de subida, edición y borrado son locales y traducidos; los mensajes flash históricos en inglés del controlador no se muestran. No se modificó el backend.

## Seguridad y permisos

- `media.view`: acceso al listado.
- `media.upload`: botón y panel de subida.
- `media.manage`: edición de title/ALT.
- `media.delete`: borrado y zona de peligro.
- La UI oculta acciones no autorizadas; el backend sigue siendo autoritativo.
- El borrado usa axios con `Accept: application/json` para capturar 409. Un 409 muestra un mensaje localizado, conserva el asset y no permite forzar la eliminación.
- `usage_count` solo agrega producto/variante; el 409 también cubre logo/favicon, por lo que la UI nunca asume que `usage_count = 0` significa eliminable.
- Masters y derivados permanecen privados; no se generan URLs públicas permanentes.
- Se conservan `TenantContext`, aislamiento cross-tenant y rutas autorizadas.

## Integración con Product Edit

- No se modificó `resources/js/pages/admin/products/Edit.vue`.
- `MediaPicker` conserva la carga diferida de FASE 26.11:
  - Galería de producto: `v-if="productMediaPickerOpen && canUpdate"`.
  - Galería de variante: `v-if="variantMediaPickerId === variant.id"`.
- `MediaUploader` mantiene su contrato y su `maxImageMb` por defecto de 10 MB para los usos existentes.
- No se alteraron selección, imagen principal, orden, ALT contextual, detach ni asociaciones de producto/variante.

## Rendimiento

- Paginación server-side de 24; no se carga la biblioteca completa.
- Sin consultas N+1 nuevas: el índice mantiene `withCount` para producto/variante y los thumbnails se sirven por su ruta autorizada con `loading="lazy"`. Una prueba verifica que el conteo de queries no crece al aumentar el volumen de la página.
- No se añadieron endpoints ni cargas de biblioteca completa; el uploader no se monta hasta abrir el panel. No se instrumentó tráfico HTTP real de navegador.
- `MediaPicker` no se monta al cargar Product Edit.
- Bundle de referencia (build de producción):

| Chunk | Antes | Después |
|---|---:|---:|
| `Index` de Media | 2.836 kB | 12.92 kB (4.18 kB gzip) |
| `MediaUploader` | 53.68 kB | 54.11 kB |
| `MediaPicker` | 3.76 kB | 3.84 kB |

El incremento del chunk de página corresponde al rediseño, metadatos, diálogo y manejo de errores; los componentes compartidos (`PageHeader`, `FieldError`, `StatusNotice`, `Pagination`) se cargan como chunks reutilizables.

## Pruebas ejecutadas

- Guard de base aislada: `TestingDatabaseConnectionTest` — 1 passed.
- Suite Media: 15 passed, 122 assertions.
- Catalog, Localization, Access, Auth y Tenancy: 119 passed, 752 assertions.
- `npm run test:i18n`: 471 mensajes Vue y 24 mensajes Laravel JSON por locale.
- `npm run typecheck`: sin errores.
- `npm run build`: correcto.
- `npm audit`: 0 vulnerabilidades.
- `npm audit --omit=dev`: 0 vulnerabilidades.
- `git diff --check`: sin errores.

Pruebas nuevas:

- `test_owner_updates_title_and_alt_without_replacing_the_image`.
- `test_library_serializes_catalog_usage_and_private_delivery_urls`.
- `test_library_query_count_does_not_grow_with_page_volume`.

Cobertura existente preservada: listado, paginación, búsqueda, formatos permitidos/rechazados, permisos, aislamiento tenant, eliminación permitida/bloqueada, integración producto/variante y headers privados.

## Diferencias frente a Stitch

- No se implementó `Uploaded By` porque no está serializado.
- No se implementó copiar Markdown/embed ni enlaces individuales de referencias porque no existe endpoint/acción.
- No se añadieron filtros de estado/tipo ni cards PROCESSING/FAILED; el índice solo lista `READY`/`IMAGE`.
- No se añadieron métricas decorativas ni acciones bulk.
- El detalle usa un `<dialog>` nativo accesible en lugar del panel lateral de Stitch.
- La confirmación de borrado sigue usando `window.confirm` con texto traducido, no un modal custom.
- `usage_count` se presenta como uso de catálogo (producto/variante); el bloqueo por logo/favicon se explica en la política de eliminación.

## Pendientes

- Validación visual manual desktop y mobile 390 px en navegador; no se realizó en esta fase.
- Si se desea mostrar el límite real de `config/media.php` también dentro de `MediaPicker` cuando el entorno lo cambie, habría que pasar `maxImageMb` desde Product Edit; no se modificó ese flujo.
- No se implementaron descargas, reemplazo de archivo, filtros ni acciones no soportadas por el backend.
