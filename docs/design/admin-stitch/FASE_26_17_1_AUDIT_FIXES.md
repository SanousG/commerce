# FASE 26.17.1 — Corrección de hallazgos administrativos

Fecha: **2026-10-26**

Rama: `development`

Alcance: correcciones sobre `ADM-001`, `ADM-013`–`ADM-019` y el shell
administrativo. No se crearon pantallas, no se alteraron reglas comerciales,
RBAC, `TenantContext`, `StoreContext`, modelos ni datos almacenados.

## 1. Resultado

Los hallazgos funcionales de FASE 26.17 quedaron resueltos:

- Laravel entrega mensajes legibles en `es-PE` y `en` para las reglas usadas por
  Dashboard, Catalog y Media; las pruebas HTTP verifican que no se filtren claves
  `validation.*`.
- Los errores de aplicación visibles de Catalog, Media y Dashboard usan el
  locale efectivo del usuario.
- Los estados reales que ADM-001 recibe están traducidos; un valor no reconocido
  se presenta como “Estado desconocido” / “Unknown status”, sin mostrar el enum
  crudo.
- El rango personalizado muestra errores por campo, conserva los valores y
  bloquea solicitudes duplicadas mientras una visita está activa.
- Media Library, Product Edit y Store Settings reciben el límite de subida desde
  `config('media.max_image_mb')`.
- El drawer móvil incorpora focus trap, foco inicial, Escape, restauración al
  disparador y fondo `inert`; el layout incorpora skip link, destino estable y
  foco al contenido al cambiar de ruta.

No se realizó verificación visual ni con tecnología asistiva real. Ese único
bloque de aprobación continúa pendiente.

## 2. Validaciones Laravel

Se completaron en `lang/en/validation.php` y
`lang/es-PE/validation.php` las estructuras que Laravel 13 consulta realmente:

- `file`
- `max.file`
- `min.file`
- `min.array`
- `enum`
- `not_in`
- `required_if`
- `date_format`
- `after_or_equal`
- `uploaded`

También se añadieron nombres legibles para rango, fechas y campos Catalog/Media.
No se cambió ninguna regla de FormRequest.

Cobertura HTTP añadida:

- archivo que supera el máximo en español e inglés;
- archivo con contenido no reconocido como imagen;
- enum de rango inválido;
- fecha con formato inválido;
- rango invertido;
- rango superior a 366 días;
- rango válido en sus límites reales.

## 3. Mensajes de aplicación localizados

Se localizaron exclusivamente mensajes destinados al usuario:

- Media: upload/update/delete, archivo ilegible, MIME auténtico, imagen inválida,
  dimensiones, corrupción y conflicto de borrado por referencias.
- Catalog: ownership de categorías, ciclos, opción duplicada, variante
  predeterminada, selección de valores y orden/primary de galerías.
- Dashboard: máximo permitido del rango personalizado.

Los mensajes técnicos de fallos de almacenamiento, GD/runtime y codificación,
destinados a manejo interno/logs, permanecen técnicos. Los códigos HTTP y los
contratos JSON no cambiaron.

Los JSON Laravel pasaron de 24 a **43 claves por locale**, con paridad exacta de
claves y placeholders.

## 4. Estados Dashboard

Se verificaron contra los enums usados por las proyecciones Analytics. Se
añadieron, sin crear valores de dominio:

- `REFUNDED`, `PARTIALLY_REFUNDED`
- `REQUESTED`, `APPROVED`, `RECEIVED`, `INSPECTING`
- `ORDERED`, `PARTIALLY_RECEIVED`
- `READY`, `SHIPPED`, `DELIVERED`

El validador i18n exige ahora las claves de los estados de Order, pagos,
fulfillment, refunds, returns, procurement y fiscal que ADM-001 serializa.
`StatusBadge` y los listados compactos del Dashboard usan un fallback semántico
controlado para cualquier estado desconocido.

## 5. Rango personalizado

`resources/js/pages/admin/Dashboard.vue` ahora:

- consume los errores Inertia `range`, `from` y `to`;
- presenta `FieldError` junto al control correspondiente;
- usa IDs estables, labels explícitos, `aria-invalid` y `aria-describedby`;
- conserva `from` y `to` mediante estado preservado de Inertia;
- deshabilita select y submit mientras la visita está en curso;
- ignora intentos duplicados mientras `rangeSubmitting` está activo.

No se cambió `DashboardDateRange`, la zona horaria, la duración máxima ni los
cálculos Analytics.

## 6. MediaPicker y límites

La fuente de verdad sigue siendo `config/media.php` y la validación autoritativa
sigue en `UploadMediaRequest`/`ImageProcessor`.

Laravel entrega `mediaLimits.max_image_mb` a:

- Media Library;
- Product Edit, incluidos sus dos MediaPicker;
- Store Settings, consumidor existente del picker.

`MediaPicker` requiere el límite y lo pasa a `MediaUploader`; se eliminó el
default embebido de 10 MB. Los MIME cliente continúan limitados a JPEG, PNG y
WebP, exactamente como el backend. No se modificó ningún archivo almacenado.

### Carga diferida

No se añadió import dinámico manual. El build ya produce chunks separados para
`MediaPicker` (3.91 kB / 1.62 kB gzip) y `MediaUploader` (54.10 kB / 20.32 kB
gzip), mientras la carga de datos e imágenes continúa diferida. Una capa
adicional no justificaba complejidad ni riesgo para cerrar los errores.

## 7. Accesibilidad

Cambios estructurales:

- focus trap circular con Tab y Shift+Tab dentro del drawer;
- foco inicial en el botón de cierre;
- cierre con Escape y backdrop;
- restauración del foco al botón que abrió el drawer;
- fondo con `inert` y `aria-hidden` durante el modal;
- `aria-controls`, `aria-expanded`, `role="dialog"` y `aria-modal`;
- skip link localizado;
- destino estable `#admin-main-content` con `tabindex="-1"`;
- foco programático en el contenido principal tras navegación Inertia.

No se añadió dependencia. Falta validar interactivamente con navegador
autenticado, teclado, NVDA/VoiceOver y axe/Lighthouse.

## 8. Migración `admin_locale`

Verificación read-only contra la conexión efectiva de desarrollo:

- host: `pgsql`;
- base: `commerce`;
- columna `users.admin_locale`: existe;
- registro `2026_10_25_000001_add_admin_locale_to_users_table`: existe;
- `php artisan migrate:status --database=pgsql`: todas las migraciones figuran
  como `Ran`; no hay pendientes.

Por tanto, el informe de FASE 26.15.1 coincide con la base actualmente conectada
y la afirmación de FASE 26.17 sobre una migración pendiente era incorrecta. No
se ejecutó ninguna migración ni escritura sobre desarrollo.

## 9. Pruebas y validaciones

| Validación | Resultado |
|---|---|
| Guard `pgsql.testing/commerce_testing` | PASS — 1 test, 4 assertions |
| Pruebas focalizadas | PASS — 62 tests, 598 assertions |
| Suite Laravel completa | PASS — 636 tests, 4429 assertions, 152.97 s |
| Pint sobre PHP afectado | PASS — 20 archivos |
| `npm run test:i18n` | PASS — 484 Vue y 43 JSON Laravel por locale |
| `npm run typecheck` | PASS |
| `npm run build` | PASS — 824 módulos, 1.91 s en la ejecución final |
| `npm audit` | PASS — 0 vulnerabilidades |
| `npm audit --omit=dev` | PASS — 0 vulnerabilidades |
| `git diff --check` | PASS al cierre |

Las pruebas de base se ejecutaron solo después del guard aislado. La suite no
contactó SUNAT. El warning opcional de `fontaine` permanece no bloqueante.

## 10. Rendimiento

No se introdujeron consultas ni cálculos comerciales nuevos. Los límites Media
son props escalares y la accesibilidad usa APIs nativas.

Artefactos relevantes del build final:

- app: 291.20 kB / 97.69 kB gzip;
- AdminLayout: 17.44 kB / 5.67 kB gzip;
- Dashboard: 35.51 kB / 8.46 kB gzip;
- Product Edit: 38.10 kB / 8.18 kB gzip;
- Media Index: 12.86 kB / 4.16 kB gzip;
- MediaPicker: 3.91 kB / 1.62 kB gzip;
- MediaUploader: 54.10 kB / 20.32 kB gzip;
- CSS: 79.01 kB / 16.05 kB gzip.

No se ejecutó benchmark browser ni se afirma una mejora porcentual.

## 11. Archivos de la corrección

### Backend y traducciones

- `app/Modules/Analytics/Http/Requests/DashboardRequest.php`
- `app/Modules/Catalog/Application/{CreateProduct,CreateProductOption,ManageProductMedia,SaveCategory,SaveVariant,UpdateProduct}.php`
- `app/Modules/Catalog/Http/Controllers/ProductController.php`
- `app/Modules/Media/Application/{DeleteMediaAsset,ImageProcessor}.php`
- `app/Modules/Media/Http/Controllers/MediaController.php`
- `app/Modules/Stores/Http/Controllers/StoreSettingsController.php`
- `lang/en/validation.php`, `lang/es-PE/validation.php`
- `lang/en.json`, `lang/es-PE.json`

### Frontend

- `resources/js/components/admin/{AdminHeader,MobileDrawer,StatusBadge}.vue`
- `resources/js/components/media/{MediaPicker,MediaUploader}.vue`
- `resources/js/layouts/AdminLayout.vue`
- `resources/js/pages/admin/Dashboard.vue`
- `resources/js/pages/admin/products/Edit.vue`
- `resources/js/pages/admin/stores/Settings.vue`
- `resources/js/i18n/messages.ts`
- `scripts/validate-i18n.mjs`

### Pruebas y documentación

- `tests/Feature/Analytics/AdminDashboardTest.php`
- `tests/Feature/Catalog/{CategoryTest,ProductManagementTest}.php`
- `tests/Feature/Media/{MediaUploadAndLibraryTest,ProductMediaTest}.php`
- `tests/Feature/Stores/StoreSettingsTest.php`
- `docs/design/admin-stitch/FASE_26_17_ADMIN_AUDIT.md`
- `docs/design/admin-stitch/FASE_26_17_1_AUDIT_FIXES.md`

## 12. Decisiones y pendientes

- Sin dependencias añadidas o eliminadas.
- Sin migraciones creadas o ejecutadas.
- Sin cambios de RBAC, tenancy, store context o reglas comerciales.
- Sin pantallas Stitch ni nuevas pantallas de producto.
- Import dinámico manual omitido porque Vite ya separa los componentes Media.
- Pendiente exclusivamente la comprobación interactiva visual y de accesibilidad
  real; no se declara aprobada.
- `.opencode/plugins/stitch-env.js` permanece local y debe excluirse del futuro
  PR.
- No se creó commit, push ni PR.
