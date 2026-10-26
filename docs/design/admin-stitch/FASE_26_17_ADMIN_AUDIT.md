# FASE 26.17 — Auditoría del primer bloque administrativo

Fecha de cierre técnico: **2026-10-26**

Rama auditada: `development`

HEAD auditado: `1f7e320` (`feat: expand admin operations and performance`)

Alcance: `ADM-001`, `ADM-013`–`ADM-019`, shell administrativo, RBAC,
i18n, rendimiento, seguridad y preparación de PR.

> **Actualización FASE 26.17.1 (2026-10-26):** los hallazgos funcionales P1/P2
> de i18n Laravel, estados Dashboard, errores del rango personalizado,
> configuración de MediaPicker y accesibilidad estructural del drawer fueron
> corregidos. La evidencia y el inventario final están en
> `FASE_26_17_1_AUDIT_FIXES.md`. La comprobación visual, de teclado y con
> tecnología asistiva en navegador continúa pendiente; las descripciones de
> brechas que siguen debajo representan el estado original de esta auditoría.

## 1. Resumen ejecutivo

El primer bloque rediseñado está funcionalmente integrado en el monolito
Laravel/Inertia/Vue y conserva los límites Tenant/Store, permisos y contratos de
backend revisados. La suite completa, el guard de base aislada, Pint, las
validaciones frontend, el build y las auditorías npm pasan.

El resultado **no puede considerarse todavía aprobado visualmente**: no se hizo
una sesión autenticada en navegador ni una comparación real a 1280, 1440 y
390 px. La revisión responsive y de accesibilidad fue estática sobre el código.

Tampoco debe presentarse aún como “i18n completa”. El catálogo Vue tiene paridad
exacta, pero varios mensajes Laravel usados por estas pantallas siguen ausentes
o hardcodeados en inglés. En `es-PE`, reglas como `validation.file` y
`validation.enum` llegan a resolverse como la clave literal. Este es el principal
bloqueo de calidad previo a un PR que prometa internacionalización completa.

No se encontraron vulnerabilidades npm, fuga cross-tenant, acceso público a
Media ni regresiones automatizadas. No se ejecutaron migraciones y no se tocó la
base PostgreSQL de desarrollo.

### Dictamen

- **Código y pruebas automatizadas:** aprobados.
- **Seguridad y aislamiento revisados:** sin hallazgos críticos o altos.
- **Aprobación visual/accesibilidad en navegador:** pendiente.
- **i18n integral de las ocho pantallas:** pendiente de corregir mensajes
  Laravel y estados faltantes.
- **PR:** preparar con allowlist; no abrir ni fusionar hasta resolver o aceptar
  explícitamente los pendientes P1 indicados abajo.

## 2. Inventario de cambios auditado

El worktree contiene el trabajo acumulado de FASE 26.7–26.16, sin commit:

- 52 archivos tracked modificados.
- Diff tracked al cierre previo al informe: aproximadamente 3.5k inserciones y
  0.7k eliminaciones.
- Archivos nuevos de i18n, shell, documentación, migración y pruebas.
- Una configuración local no versionada en `.opencode/plugins/stitch-env.js`.

Áreas principales:

1. **Sistema visual:** tokens en `resources/css/app.css`, Inter y JetBrains Mono,
   shell, header, sidebar, drawer y componentes administrativos compartidos.
2. **Pantallas:** Dashboard; Products Index/Create/Edit; Categories
   Index/Create/Edit; Media Library.
3. **i18n:** `vue-i18n`, catálogos `es-PE`/`en`, middleware Laravel, preferencia
   global `users.admin_locale`, validación de paridad y páginas auth.
4. **Backend Catalog/Media:** flashes traducibles de Catalog, serialización,
   pruebas de UI contracts, Media usage count y query-count constante.
5. **Entorno:** PostgreSQL local `pgsql`, PostgreSQL aislado
   `pgsql.testing/commerce_testing`, documentación de rendimiento y seguridad.

### Correcciones menores aplicadas durante esta auditoría

No cambian contratos, datos, permisos ni migraciones:

1. `resources/js/pages/admin/Dashboard.vue`: el enlace de productos destacados
   ahora apunta a la ruta existente `/admin/products/{id}/edit`, cuya pantalla
   ya soporta modo lectura por RBAC.
2. `resources/js/pages/admin/categories/Index.vue`: usuarios con
   `categories.view` conservan acceso a la pantalla de detalle/edición en modo
   lectura; editar y archivar siguen condicionados por permisos.
3. `resources/js/pages/admin/media/Index.vue`: usuarios con `media.view` pueden
   abrir el diálogo de detalles; guardar y eliminar siguen condicionados por
   `media.manage` y `media.delete`.

## 3. Estado por pantalla

| Diseño | Pantalla | Estado funcional | Responsive por código | Estado visual |
|---|---|---|---|---|
| ADM-001 | Dashboard | Integrada; secciones y enlaces condicionados por RBAC; enlace de top products corregido | Layouts desktop/mobile presentes | Pendiente en 1280/1440/390 |
| ADM-013 | Products Index | Búsqueda, paginación, estados, cards móviles, create/update/archive por permiso | Tabla `md+`, cards `<md` | Pendiente |
| ADM-014 | Product Create | Contrato name/description/SKU/barcode/categories preservado | Formulario apilado y columnas desde `md` | Pendiente |
| ADM-015 | Product Edit | Producto, variantes, opciones, pricing y Media preservados; read-only por RBAC | Secciones y galerías adaptables | Pendiente |
| ADM-016 | Categories Index | Lista plana paginada deliberada; acceso read-only corregido | Tabla `md+`, cards `<md` | Pendiente |
| ADM-017 | Category Create | Solo name, parent y position; slug/estado controlados por servidor | Formulario apilado/columnas | Pendiente |
| ADM-018 | Category Edit | Jerarquía acíclica, estado, read-only y Archive separado | Formulario apilado/columnas | Pendiente |
| ADM-019 | Media Library | Grid, búsqueda, paginación, upload, details/edit, 409 y RBAC; details read-only corregido | 2/3/4 columnas y dialog fluido | Pendiente |

### Componentes compartidos

- `AdminLayout`, `AdminHeader`, `AdminSidebar`, `MobileDrawer`, `Breadcrumbs`,
  `PageHeader`, `StatusBadge`, `Pagination` y `EmptyState` se reutilizan en el
  bloque.
- La navegación usa `Link` de Inertia y oculta entradas según permisos.
- El foco visible global usa el secondary token.
- Tablas se sustituyen por cards móviles donde la densidad no cabe en 390 px.
- No hay evidencia de browser para descartar clipping, saltos de tipografía o
  diferencias con Stitch; el análisis anterior es estructural, no visual.

## 4. Sistema visual y consistencia

### Cumple

- Primary `#0F172A`, secondary `#2563EB`, tertiary `#0284C7`, neutral
  `#64748B`, radio base de 8 px y bordes de 1 px.
- Inter para UI y JetBrains Mono para datos técnicos/tabulares.
- Jerarquía sobria, superficies blancas y canvas claro consistente con
  **Executive Precision B2B**.
- Controles, cards, tablas y links comparten clases administrativas.

### Deuda menor

- `FieldError.vue` usa `text-red-700` en vez de `text-admin-danger`.
- `StatusNotice.vue` conserva clases emerald directas, aunque las ocho
  pantallas suelen sobrescribirlas con tokens admin.
- Dashboard mantiene algunos `text-amber-*`, `text-sky-*` y tonos inversos
  directos para semántica. No contradicen la marca, pero reducen la centralidad
  del sistema de tokens.
- `AuthLayout.vue` conserva la identidad histórica verde/lima. Auth no forma
  parte de las ocho pantallas auditadas; no se modificó en esta fase.

## 5. Accesibilidad

### Evidencia positiva

- Un `h1` por pantalla mediante `PageHeader`; subsecciones con headings.
- Labels, fieldsets, `aria-invalid`, `aria-describedby`, `role="alert"` y
  `role="status"` en los flujos principales.
- Íconos decorativos marcados con `aria-hidden`.
- Navegación, breadcrumbs y paginación tienen nombres accesibles.
- El diálogo Media usa `<dialog>`, `showModal()`, heading y botón de cierre.
- Estados no dependen solo del color: mantienen texto visible.

### Pendientes

- **Media:** `MobileDrawer` mueve el foco al botón de cierre y responde a Escape,
  pero no implementa focus trap explícito ni restaura explícitamente el foco al
  disparador. Requiere prueba real de teclado.
- **Baja:** `AdminLayout` no ofrece enlace “saltar al contenido”.
- La restauración de foco del `<dialog>` nativo y el orden de tabulación deben
  confirmarse en Chromium/Firefox/Safari.
- No se ejecutó auditoría con lector de pantalla, axe ni Lighthouse.

## 6. i18n

### Cumple

- `es-PE` es default y fallback; `en` es alternativa.
- La preferencia es global por usuario, no Tenant/Store.
- `PATCH /admin/locale` está autenticado, validado y protegido por CSRF.
- Catálogos Vue con paridad exacta de claves y placeholders.
- Las plantillas de las ocho pantallas usan `t()` para copy de interfaz. Se
  mantienen sin traducir únicamente marca, SKU, IDs, monedas, nombres de datos,
  valores técnicos y el guion de ausencia.
- Catálogos Laravel JSON tienen paridad entre ambos locales.

### P1 — i18n Laravel incompleta

Comprobación en runtime con locale `es-PE`:

- `validation.file` → `validation.file`
- `validation.max.file` → `validation.max.file`
- `validation.enum` → `validation.enum`
- También faltan `validation.not_in`, `validation.date_format`,
  `validation.after_or_equal` y `validation.required_if`.

Esto puede mostrar claves crudas en Media, Dashboard y formularios Catalog.
Además siguen hardcodeados en inglés errores de aplicación que sí pueden llegar
a estas pantallas, entre ellos:

- Media: autenticidad, lectura, dimensiones y corrupción de imagen en
  `ImageProcessor`; flashes de upload/update/delete en `MediaController`.
- Categories: ciclo de jerarquía en `SaveCategory`.
- Products: ownership de categorías, opción duplicada, reglas de variante y
  orden/primary de galerías en servicios Catalog.
- Dashboard: máximo de 366 días en `DashboardRequest`.

El Dashboard tampoco renderiza actualmente sus errores de validación de rango,
por lo que un rango custom inválido puede volver sin explicación visible.

### P2 — estados incompletos

`StatusBadge` hace fallback al enum crudo. El catálogo compartido carece de
estados como `REFUNDED`, `PARTIALLY_REFUNDED`, `REQUESTED`, `APPROVED`,
`RECEIVED`, `INSPECTING`, `ORDERED` y `PARTIALLY_RECEIVED`, que pueden aparecer
en widgets Dashboard. El catálogo global también omite `READY`, `SHIPPED`,
`DELIVERED`, `SENT` y `ERROR` para otros módulos.

### Recomendación i18n

Antes de declarar la fase “full i18n”:

1. Completar los mensajes Laravel realmente usados por ADM-001/013–019 en
   `lang/en/validation.php` y `lang/es-PE/validation.php`.
2. Envolver los errores de aplicación visibles con `__()` y añadir claves JSON
   en ambos idiomas.
3. Completar estados que el Dashboard puede serializar.
4. Añadir pruebas que aserten el texto `es-PE` para upload inválido, rango
   custom y errores de dominio Catalog, no solo la presencia del error.

## 7. Media Library — casos especiales

- Contrato conservado: JPEG/PNG/WebP, 10 MB por default, 6000×6000, 20 MP,
  página de 24, almacenamiento privado y estado READY/IMAGE.
- Thumbnail y archivo original requieren auth, `media.view`, TenantContext y
  devuelven `Cache-Control: private` más `nosniff`.
- `usage_count` representa asociaciones Product + Variant; logo/favicon se
  protegen en backend con 409 aunque no sumen en ese contador.
- Delete usa JSON DELETE, conserva el asset ante 409 y no ofrece force delete.
- Thumbnails usan `loading="lazy"`; la consulta de lista usa `withCount` y tiene
  prueba de query count constante.
- Upload/edit/delete muestran mensajes Vue locales en la pantalla actual.

### Límites y lazy loading

- La biblioteca principal pasa `limits.max_image_mb` al uploader y se mantiene
  coherente con `config/media.php`.
- `MediaPicker` conserva un default local de 10 MB y Product Edit no recibe ni
  pasa `limits`. Si cambia `MEDIA_MAX_IMAGE_MB`, el picker puede divergir del
  servidor y de la biblioteca principal.
- Product Edit monta `MediaPicker` solo al abrirlo; eso difiere la consulta JSON
  y las imágenes. Sin embargo el import es estático, por lo que no constituye
  por sí solo lazy code loading mediante `defineAsyncComponent`/`import()`.

## 8. Seguridad y aislamiento

### Resultado

No se identificaron hallazgos críticos o altos de seguridad dentro del bloque.

- Web usa sesión/cookie Laravel; no se introdujo JWT ni Supabase Auth.
- Rutas admin requieren `auth`, `auth.session` y `verified`.
- Mutaciones usan CSRF de Laravel/Inertia/axios.
- Tenant se resuelve con `TenantContext`; Store con `StoreContext` cuando
  corresponde. No se acepta `tenant_id` frontend como autoridad.
- Media, Products y Categories hacen lookups scoped; los tests cross-tenant
  pasan.
- La UI oculta acciones por RBAC, pero controladores, FormRequests y Gates siguen
  siendo la autoridad.
- No hubo llamadas a SUNAT durante las pruebas.
- `npm audit` completo y production reportan 0 vulnerabilidades.

### Riesgos de Git/configuración

- `.opencode/plugins/stitch-env.js` es configuración local no tracked. No
  contiene la key, pero lee `STITCH_API_KEY` desde `.env`; debe quedar fuera del
  PR y nunca debe imprimirse ni versionarse junto con credenciales.
- `.env` está ignorado.
- `.env.example` y `compose.yaml` contienen credenciales conocidas y locales
  para PostgreSQL Docker. No son secretos de producción; deben revisarse y
  viajar juntos si se incluyen para no romper el bootstrap local.
- No usar `git add .`; aplicar allowlist por grupo.

## 9. Rendimiento

### Evidencia actual

- Build de producción exitoso: 824 módulos, aproximadamente 1.65 s en la
  repetición posterior a las correcciones.
- Chunks relevantes, tamaño sin comprimir / gzip:
  - app: 290.52 / 97.45 kB
  - AdminLayout: 16.13 / 5.17 kB
  - Dashboard: 34.43 / 8.18 kB
  - Product Edit: 37.97 / 8.15 kB
  - Media Index: 12.86 / 4.16 kB
  - MediaPicker: 3.84 / 1.59 kB
  - MediaUploader: 54.11 / 20.33 kB
  - CSS app: 78.72 / 15.95 kB
- Media pagina 24 elementos, usa thumbnails y prueba de query count constante.
- Products/Categories usan paginación y eager loading explícito.
- Las props Dashboard se construyen por permiso y no serializan secciones no
  autorizadas.

### Límites de la evidencia

- No se repitió el benchmark backend de
  `docs/performance/ADMIN_PERFORMANCE_BASELINE.md`.
- No hay medición nueva de TTFB, p95, waterfall, scripting, render o paint.
- Por tanto no se afirma ningún porcentaje nuevo de mejora ni aprobación de
  rendimiento browser.
- El warning del build sobre `fontaine` es opcional y no bloqueante; no se añadió
  la dependencia por restricción de esta fase.

## 10. Resultados de validación

| Comando | Resultado |
|---|---|
| Guard `TestingDatabaseConnectionTest.php` | PASS — 1 test, 4 assertions, 0.89 s |
| Suite Laravel completa | PASS — 634 tests, 4391 assertions, 175.74 s |
| `npm run test:i18n` | PASS — 471 mensajes Vue y 24 JSON Laravel por locale |
| `npm run typecheck` | PASS |
| `npm run build` | PASS — warning opcional `fontaine` |
| `npm audit` | PASS — 0 vulnerabilidades |
| `npm audit --omit=dev` | PASS — 0 vulnerabilidades |
| Pint `--test` sobre PHP modificado/nuevo | PASS — 27 archivos |
| `git diff --check` | PASS |

La suite se ejecutó únicamente después de confirmar por código y guard que la
conexión efectiva era `pgsql.testing/commerce_testing`. `RefreshDatabase` no se
ejecutó contra `pgsql/commerce`.

Después de las tres correcciones Vue se repitieron `test:i18n`, typecheck,
build, route-list del contrato Product y `git diff --check`; todos pasaron. La
suite PHP completa no se repitió porque las correcciones fueron exclusivamente
de enlaces/visibilidad Vue y no alteraron backend.

## 11. Deuda y riesgos priorizados

### P0 / crítico

- Ninguno identificado.

### P1 / alto de calidad de entrega

1. Mensajes Laravel faltantes o hardcodeados impiden afirmar i18n completa.
2. Validación de rango Dashboard no muestra feedback al usuario.

### P2 / medio

1. Estados Dashboard incompletos en ambos catálogos.
2. `MEDIA_MAX_IMAGE_MB` puede divergir en `MediaPicker`.
3. Mobile drawer sin focus trap/restauración explícita.
4. Falta aprobación visual y de teclado real en 1280/1440/390.
5. MediaPicker difiere datos, pero no usa import dinámico para diferir código.

### P3 / bajo

1. Skip link ausente.
2. Tokens semánticos directos en componentes puntuales.
3. Warning opcional de `fontaine`.
4. `HandleInertiaRequests` comparte directamente `admin_locale`; una preferencia
   inválida cae a español en Laravel/Vue por fallback, pero podría dejar el
   selector sin opción coincidente hasta guardar un valor válido.

## 12. Checklist manual pendiente

Ejecutar con usuario Owner, Admin y Member, en `es-PE` y `en`:

1. Comparar cada pantalla con Stitch a 1280 y 1440 px.
2. Probar a 390 px: header, drawer, cards, forms, grids y diálogo Media.
3. Recorrer todo con Tab/Shift+Tab; verificar Escape, foco inicial, focus trap y
   restauración en drawer/dialog.
4. Verificar zoom 200 %, reflow y textos largos.
5. Probar empty/loading/error/validation/success/read-only/forbidden.
6. Confirmar cambio de idioma sin perder Tenant/Store ni estado inesperado.
7. Probar Media con tipo falso, archivo corrupto, > límite, dimensiones/píxeles
   excesivos, 409 por Product/Variant y referencias logo/favicon.
8. Cambiar temporalmente `MEDIA_MAX_IMAGE_MB` en un entorno autorizado y
   comprobar biblioteca principal frente a Product Edit.
9. Medir DevTools: primera carga, navegación Inertia, waterfall de thumbnails y
   fetch del picker.
10. Ejecutar axe/Lighthouse y, como mínimo, NVDA o VoiceOver en los flujos
    críticos.

## 13. Plan de PR limpio

No se creó commit, push ni PR. Plan recomendado:

### Precondiciones

1. Resolver P1 de i18n o cambiar explícitamente el alcance para no prometer
   “full i18n”.
2. Completar el checklist visual mínimo y adjuntar evidencia.
3. Repetir checks afectados después de cualquier corrección.

### Allowlist y exclusiones

- Excluir `.opencode/plugins/stitch-env.js`.
- Excluir `public/build/` generado; ya está ignorado.
- No incluir `.env`.
- Revisar si `AGENTS.md` y `.opencode/skills/testing-rules/SKILL.md` pertenecen al
  PR de producto o a un PR separado de tooling/documentación.
- Incluir `.env.example` únicamente junto con `compose.yaml` y la documentación
  de aislamiento local.

### Secuencia sugerida de commits

1. `chore(dev): isolate local development and testing postgres`
2. `feat(admin): add executive precision shell and shared components`
3. `feat(i18n): add es-PE admin locale and persisted preference`
4. `feat(admin): redesign dashboard products and categories`
5. `feat(media): redesign media library with private tenant controls`
6. `test(admin): cover catalog media localization and isolation`
7. `docs(admin): record stitch implementation and final audit`

### Título sugerido

`feat(admin): redesign dashboard, catalog and media with es-PE localization`

### Contenido obligatorio del PR

- Capturas 1280/1440/390 de las ocho pantallas y drawer/dialog.
- Matriz RBAC Owner/Admin/Member.
- Nota de migración `users.admin_locale` y rollback.
- Evidencia de base de pruebas aislada.
- Resultados completos de checks.
- Bundles antes/después sin afirmar p95 no medido.
- Deuda aceptada, especialmente i18n Laravel y accesibilidad de foco.

## 14. Trabajo deliberadamente omitido

- No se implementaron módulos o pantallas posteriores.
- No se amplió la traducción general de módulos fuera del bloque.
- No se refactorizaron contratos de Media, Catalog, Tenant o Store.
- No se añadió focus-trap, skip link, `fontaine` ni dependencia npm.
- No se ejecutaron migraciones, benchmarks destructivos ni pruebas sobre
  PostgreSQL de desarrollo.
- No se hizo commit, push, cambio de rama ni apertura de PR.
