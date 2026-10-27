# FASE 26.39 — Auditoría técnica final del administrador

Fecha: 2026-10-27
Rama inspeccionada: `development`
Alcance: ADM-001–ADM-069, sin rediseño, migraciones ni cambios de reglas comerciales.

## A. Resumen ejecutivo

Se verificaron **69 IDs únicos**, **69 componentes Inertia administrativos existentes** y **69 destinos `Inertia::render('admin/...')` únicos**, sin componente faltante. El registro efectivo contiene 226 rutas, 208 bajo `admin/*` y 74 rutas administrativas GET. Las 208 rutas administrativas usan `auth`, `auth.session` y `verified`; 203 usan contexto de Tenant y 82 requieren además contexto de Store. Las cinco excepciones al middleware `tenant` son el cambio de idioma y las operaciones de selección/creación de Tenant, que deliberadamente funcionan sin Tenant activo.

La suite Laravel completa pasó: **636 tests y 4429 assertions**. También pasaron i18n, TypeScript, los dos audits de npm y `git diff --check`. No se detectó una regresión funcional o de aislamiento que justifique modificar backend, RBAC o reglas de negocio.

El hallazgo operativo principal es el bundle inicial de **517.75 kB / 159.53 kB gzip**, que conserva la advertencia de Vite. El segundo riesgo es Git: el worktree agrupa cambios acumulados de varias fases y no debe integrarse como un único commit indiscriminado.

**Dictamen: APTO CON OBSERVACIONES.** Las observaciones no bloquean integración, pero la separación de commits y el plan de code splitting de catálogos deben tratarse explícitamente.

## B. Cobertura final ADM-001–069

- IDs encontrados en la documentación: **69 únicos**, rango completo ADM-001–ADM-069, sin faltantes ni IDs fuera de rango.
- Destinos administrativos únicos en controladores: **69**.
- Componentes Vue correspondientes existentes: **69/69**.
- Inventario canónico: `ADMIN_SCREENS_INVENTORY.md` mantiene la matriz ruta/componente/alcance/permisos completa.
- Documentos de implementación: existen documentos específicos o agrupados para todos los bloques salvo **ADM-020**, cuya implementación está descrita en `STITCH_DESIGN_COVERAGE.md`, pero no tiene un archivo `ADM-020_IMPLEMENTATION.md` dedicado. Es una brecha documental menor, no funcional.
- El inventario indica 225 rutas como corte histórico; el registro actual tiene 226 por la ruta posterior de preferencia de idioma. Las cifras actuales verificadas son 226 totales, 208 administrativas y 74 GET administrativas.
- Los enlaces administrativos estáticos detectados en Vue corresponden a rutas registradas. Los enlaces dinámicos y acciones están cubiertos por la suite HTTP de sus módulos.

## C. Seguridad y aislamiento

### Resultado

No se identificó una fuga cross-tenant/cross-store ni una autorización basada en `tenant_id` enviado por el frontend.

- Las consultas tenant-owned parten de `TenantContext` o de relaciones del Tenant. La búsqueda global encontró un único uso de `store_id` validado como filtro en Activity; la consulta permanece limitada por `tenant_id` antes de aplicar el filtro (`ActivityController.php:25-47`).
- Las operaciones Store-owned se encuentran tras `tenant` + `store` y resuelven el Store activo mediante `StoreContext`.
- La suite contiene 154 archivos de prueba y al menos 40 referencias explícitas a escenarios cross-tenant/foreign-tenant; todos pasaron.
- Todas las rutas administrativas están bajo sesión Laravel y middleware `web`, por lo que conservan autenticación cookie y CSRF. No se observó JWT ni autoridad de frontend.
- Los permisos efectivos se aplican en middleware `can:*`, Form Requests o servicios `*Access`; la ocultación de UI no constituye la única barrera.
- Las acciones monetarias críticas usan claves de idempotencia y bloqueo transaccional: checkout, creación/captura de pagos, refunds y finalización manual. Los botones Vue usan `processing`/guards para evitar doble envío accidental.
- Media se guarda con visibilidad privada y se entrega por controladores que resuelven el asset dentro del Tenant y aplican `Cache-Control: private` y `nosniff` (`MediaController.php:92-128`). El serving del disco local exige URL firmada; los artefactos SUNAT usan un disco no servible y rutas autorizadas.
- El ledger de auditoría es append-only tanto por modelo de escritura como por trigger PostgreSQL (`2026_09_08_000051_create_audit_entries.php:49-59`). La metadata pasa por sanitización, y el actor/Store se valida contra el Tenant (`AuditRecorder.php:54-64,77-92`).
- No se contactó SUNAT ni un servicio externo real. Los estados fiscales inciertos continúan bloqueando reenvíos según las reglas existentes.

### Riesgos residuales

- La garantía final depende de conservar los middleware y Form Requests al integrar los cambios; no debe reemplazarse autorización backend por condiciones visuales.
- Los filtros por Store enviados en Activity no son autoridad de contexto: solo restringen una consulta ya tenant-scoped. Esta distinción debe preservarse.

## D. Integridad funcional entre módulos

Se contrastaron rutas, componentes, props, permisos y contratos cubiertos por pruebas para los bloques solicitados:

- **Tenancy / Stores / Access:** selección de Tenant y Store separada; cambiar Store no altera membresía. Team conserva protecciones del OWNER y permisos de gestión.
- **Products / Categories / Media:** IDs se resuelven desde relaciones tenant-scoped; Media privada y relaciones del catálogo conservan validación de pertenencia.
- **Listings / Pricing / Promotions:** Listings es Store-owned; pricing/promociones son tenant-wide; importes y previews se calculan en servidor y no se adopta un total del navegador.
- **Inventory / Procurement:** ubicaciones, variantes, proveedores, órdenes y recepciones quedan tenant-scoped. Cantidades, transiciones y costes se validan en backend; costes permanecen bajo permisos específicos.
- **Customers / Cart / Checkout / Orders:** Customers es tenant-wide; Cart, Checkout y Orders son Store-owned. La conversión de checkout usa snapshots y cálculo servidor; los enlaces a órdenes respetan Store activa y permiso.
- **Payments / Refunds:** ledger e idempotencia se conservan; moneda e importes se validan contra Order/Payment y no contra cálculos del frontend.
- **Fulfillment / Returns / Exchanges:** state machines y permisos de completar/inspeccionar/restock permanecen backend-authoritative.
- **Tax / Fiscal:** snapshots monetarios/fiscales, series Store-owned e issuer tenant-wide conservan límites. La suite fiscal y UBL pasó sin transporte real.
- **Notifications / Audit:** notificaciones son personales y filtradas por permisos; Audit es tenant-scoped y append-only.

No se encontró una operación Vue estática dirigida a una ruta inexistente. La suite completa cubrió los flujos integrados y no arrojó fallos. No se implementó funcionalidad nueva.

## E. Internacionalización

### Resultado

- Locales auditados: `es-PE` y `en`.
- `npm run test:i18n`: **2919 mensajes Vue**, 45 labels de navegación y 43 mensajes Laravel por locale; paridad de claves y placeholders correcta.
- Escaneo adicional de 102 archivos Vue/TypeScript: 2226 claves literales `t('...')`, **0 faltantes** y **0 valores iguales a su propia clave**.
- Las familias dinámicas revisadas incluyen estados, roles, checkout, payments, returns, fiscal, inventory, promotions, notifications y navegación.
- La preferencia se persiste por usuario en `admin_locale`, está limitada a locales soportados y tiene fallback `es-PE` (`SetUserLocale.php:11-21`, `UpdateUserLocaleRequest.php:10-19`). La cobertura HTTP de localización pasó.
- Los datos persistidos, nombres propios, códigos, referencias y snapshots permanecen sin traducción artificial.

### Corrección menor aplicada

Se añadió `status.POSTED` en ambos catálogos. El estado de una recepción de compra registrada se mostraba como el código crudo `POSTED` en ADM-063 porque `StatusBadge` recurre al valor original cuando falta la clave. Resultado: `Posted` / `Registrado`.

## F. Rendimiento y bundles

### Medición final

| Métrica | Referencia | Final | Diferencia |
|---|---:|---:|---:|
| Chunk principal | 517.71 kB | 517.75 kB | +0.04 kB |
| Gzip principal | 159.52 kB | 159.53 kB | +0.01 kB |
| Mayor chunk de página | — | MediaUploader 54.11 kB / 20.32 kB gzip | — |
| Build | advertencia >500 kB | advertencia >500 kB | sin cambio |

El incremento mínimo corresponde a la traducción `POSTED`; no se aplicó un cambio cosmético del límite ni `manualChunks` arbitrario.

### Contenido del chunk principal

La inspección del source map generada solo para auditoría atribuyó aproximadamente 443.9 kB de 515.7 kB minificados:

- catálogos `resources/js/i18n/messages.ts`: ~125.1 kB atribuidos; el módulo aislado con ambos locales mide 262.35 kB minificados / 73.26 kB gzip;
- `@inertiajs/core`: ~121.1 kB;
- runtimes Vue DOM/Core/Reactivity: ~107.5 kB combinados;
- compilador y runtime de vue-i18n / Intlify: ~64 kB combinados atribuidos;
- `@inertiajs/vue3`, bootstrap de aplicación y dependencias auxiliares: resto.

Las páginas administrativas ya se cargan por `import.meta.glob` dinámico en `resources/js/app.ts`; no son la causa directa del chunk inicial de 517 kB. El peso principal proviene de framework + Inertia + vue-i18n y de cargar **ambos catálogos completos** al iniciar.

### Optimización recomendada

1. Separar físicamente `en` y `es-PE` en módulos independientes.
2. Cargar dinámicamente solo el catálogo de `props.initialPage.props.localization.locale` antes de montar Vue.
3. Mantener `es-PE` como fallback cargándolo de forma adicional solo para usuarios `en` si se requiere fallback de runtime, o garantizar por test la paridad total y usar fallback explícito controlado.
4. Medir nuevamente chunk inicial, gzip, cambio de idioma y navegación Inertia antes de integrar.

No se aplicó esta separación en la auditoría porque mover 2919 mensajes por locale sería un cambio amplio sobre un archivo acumulado y necesita una prueba específica de cambio de idioma/fallback. Un import dinámico del archivo actual no reduciría bytes, porque ese archivo contiene ambos locales.

## G. Resultados de pruebas

Antes de pruebas de base se confirmó que no existían procesos `php`/`phpunit` antiguos y que Compose tenía servicios separados `pgsql` y `pgsql.testing`.

| Validación | Resultado |
|---|---|
| Guard `TestingDatabaseConnectionTest` | PASS — 1 test, 4 assertions; `pgsql.testing/commerce_testing` |
| Suite Laravel completa | PASS — 636 tests, 4429 assertions, 154.50 s |
| `npm run test:i18n` | PASS — 2919 mensajes Vue/locale, 45 navegación, 43 Laravel JSON |
| `npm run typecheck` | PASS |
| `npm run build` | PASS — 5.13 s; advertencia de chunk >500 kB |
| `npm audit` | PASS — 0 vulnerabilidades |
| `npm audit --omit=dev` | PASS — 0 vulnerabilidades |
| `git diff --check` | PASS |

El build se volvió a ejecutar después de la corrección i18n. `public/build/` permanece ignorado y no debe integrarse.

## H. Hallazgos por prioridad

### Alta

Ninguno.

### Media

1. **Bundle inicial >500 kB:** 517.75 kB / 159.53 kB gzip. Impacta carga y mantiene advertencia. La optimización segura es dividir catálogos por locale, no elevar el límite.
2. **Worktree acumulado:** contiene cambios de varias fases, 69 archivos tracked modificados tras esta auditoría y múltiples archivos nuevos. Un commit único dificultaría revisión, bisect y rollback.

### Baja

1. **ADM-020 sin documento de implementación dedicado:** está cubierto en inventario y `STITCH_DESIGN_COVERAGE.md`, pero no sigue el patrón documental de los demás bloques.
2. **Estado `POSTED` no localizado:** corregido durante esta auditoría.
3. **Inventario con conteo histórico de rutas:** documenta 225; el registro actual contiene 226 por la preferencia de idioma. No afecta cobertura 69/69.

## I. Cambios menores aplicados

1. `resources/js/i18n/messages.ts`: traducción `status.POSTED` para `en` y `es-PE`.
2. `.gitignore`: exclusión explícita de `/.opencode/plugins/stitch-env.js` para impedir su incorporación accidental.
3. Este informe final.

No se modificó backend, RBAC, reglas comerciales, migraciones ni configuración de bundle.

## J. Estado Git

Estado observado antes del informe:

- 69 archivos tracked modificados: 68 preexistentes a esta auditoría más `.gitignore`.
- 24 entradas untracked finales: 21 documentos de implementación acumulados, dos helpers Vue y este informe. El plugin local ya no aparece por quedar ignorado.
- Diff tracked final: 4819 inserciones y 2260 eliminaciones en 69 archivos. Los untracked no forman parte de esa estadística.
- No hay eliminaciones tracked.
- Los cambios actuales son frontend, i18n, documentación y validador; no hay backend o migraciones sin commit en este worktree.

Excluir siempre:

- `.env` y variantes con secretos;
- `public/build/`;
- `.opencode/plugins/stitch-env.js`;
- certificados, credenciales, XML/CDR y artefactos privados;
- archivos temporales de source maps/análisis (se generaron fuera del repositorio).

`.env` y `public/build/` ya estaban ignorados. El plugin local quedó ignorado en esta fase.

## K. Plan de commits y push

No ejecutar `git add -A`. Usar paths explícitos y revisar `git diff --cached` antes de cada commit.

Orden recomendado:

1. **Admin shared shell + i18n:** `AdminSidebar.vue`, `Pagination.vue`, `StatusBadge.vue`, `navigation.ts`, `messages.ts`, `scripts/validate-i18n.mjs` y `.gitignore`.
2. **Tenancy, Access, Stores y Settings:** ADM-002–012 y sus documentos.
3. **Listings, Pricing y Promotions:** ADM-020–028; incluir `PromotionForm.vue` con Create/Edit de Promotions.
4. **Inventory, Customers, Cart y Checkout:** ADM-029–044; incluir `AddressFields.vue` con Customers.
5. **Orders, Payments y reverse logistics:** ADM-045–057.
6. **Procurement, Tax, Fiscal, Notifications y Audit:** ADM-058–069.
7. **Documentación consolidada:** `STITCH_DESIGN_COVERAGE.md`, documentos de implementación restantes y este informe. Si los documentos describen exactamente cada commit funcional, también pueden viajar junto a su bloque, pero no duplicar ni separar helpers de sus consumidores.

Antes de push:

1. confirmar `git status --short` y ausencia de `.env`, `public/build`, plugin y secretos;
2. revisar cada `git diff --cached --stat` y `git diff --cached`;
3. ejecutar nuevamente guard, suite Laravel, i18n, typecheck, build, ambos audits y `git diff --check` sobre el árbol final;
4. confirmar que no apareció una migración ni cambio backend accidental;
5. realizar revisión humana de la secuencia de commits y solo entonces publicar.

## L. Riesgos y pendientes

- Separar catálogos por locale en una fase de rendimiento acotada, con medición antes/después y test de cambio de idioma/fallback.
- Crear o consolidar la documentación dedicada de ADM-020 si se exige uniformidad documental estricta.
- Actualizar el conteo histórico del inventario de 225 a 226 rutas sin alterar la matriz de 69 pantallas.
- Revisar visualmente los cambios acumulados antes de staging; esta fase no reabrió aprobación estética.
- El volumen acumulado sigue siendo el mayor riesgo de integración. No asumir que todos los archivos pertenecen al mismo commit o fase.

## Dictamen final

**APTO CON OBSERVACIONES.**

La cobertura es 69/69, no faltan rutas/componentes, no se detectaron fallos de autorización o aislamiento, y todas las validaciones obligatorias pasaron. No se declara “APTO PARA INTEGRACIÓN” sin observaciones porque persiste la advertencia de bundle y porque el worktree mezcla un volumen importante de cambios acumulados que requiere staging y commits selectivos.
