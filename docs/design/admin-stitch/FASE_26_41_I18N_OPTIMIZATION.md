# Fase 26.41 — Optimización de internacionalización y carga inicial

## Estado y alcance

- Rama auditada: `development`.
- HEAD inicial: `201e062ee3e5bc319466c100ef23ed5c4eebc2b9`.
- Estado inicial: cuatro commits por delante de `origin/development`, sin cambios locales visibles.
- Alcance: carga de los catálogos Vue. No se modificaron backend, rutas, RBAC, contextos, persistencia, reglas comerciales ni configuración de Vite.

## Arquitectura anterior

`resources/js/i18n/messages.ts` declaraba `en` y `esPE` en el mismo módulo. `esPE` extendía el objeto inglés y reemplazaba sus traducciones. `resources/js/i18n/index.ts` importaba estáticamente `messages` y entregaba ambos catálogos a `createI18n`. Como `app.ts` importaba ese bootstrap, Vite incluía ambos idiomas en el entry `app` para todos los usuarios.

La preferencia seguía este flujo, que no se cambió:

1. Laravel lee `users.admin_locale` mediante `SetUserLocale`.
2. `HandleInertiaRequests` comparte `localization.locale`, `fallback` y `supported`.
3. `app.ts` usa `localization.locale` al crear vue-i18n.
4. El selector envía `PATCH /admin/locale`; Laravel persiste la preferencia.

## Arquitectura nueva

- `resources/js/i18n/locales/es-PE.ts` y `resources/js/i18n/locales/en.ts` son catálogos completos e independientes. No existe una dependencia estática entre ellos.
- `resources/js/i18n/index.ts` contiene dos loaders `import()` explícitos.
- Un `Map` conserva cada promesa de importación. El mismo catálogo no genera solicitudes repetidas durante la vida de la aplicación; una importación fallida se elimina para permitir reintento.
- La aplicación espera los catálogos requeridos antes de montar Vue. Por ello no se renderizan claves ni contenido parcialmente traducido durante el arranque.
- El selector espera la carga antes de cambiar `locale`. Mantiene el idioma anterior durante la espera y si la importación falla. Un número de secuencia, junto con el selector deshabilitado, evita que un resultado antiguo gane una carrera.
- La navegación Inertia reutiliza la instancia de vue-i18n y las promesas ya resueltas; no vuelve a descargar catálogos.

## Fallback y comportamiento por locale

- Inicio `es-PE`: carga solamente `es-PE`.
- Inicio `en`: carga `en` y `es-PE` en paralelo. El segundo catálogo es necesario para que `fallbackLocale: 'es-PE'` sea funcional, no solo declarativo.
- Cambio `es-PE` → `en`: carga `en` una vez; `es-PE` ya está disponible como fallback.
- Cambio `en` → `es-PE`: no carga otro chunk, porque el fallback se precargó al inicio inglés.
- Cambios posteriores: reutilizan el caché de módulos y el caché de promesas.
- Recarga: Inertia vuelve a proporcionar la preferencia persistida en `users.admin_locale` y el bootstrap carga el conjunto correspondiente.

No se cambiaron claves, placeholders, pluralización, formatos basados en `locale`, mensajes, estados ni valores de dominio.

## Validador i18n

`scripts/validate-i18n.mjs` ahora importa ambos archivos físicos por separado. Conserva las verificaciones existentes de:

- 2919 claves por locale y paridad completa;
- placeholders;
- caracteres dañados;
- páginas administrativas cubiertas;
- navegación/sidebar;
- estados, enums y traducciones específicas;
- 43 mensajes JSON de Laravel por locale.

Además comprueba que ambos loaders sean dinámicos y que el bootstrap no tenga imports estáticos de catálogos.

## Medición de bundles

Mediciones obtenidas con `npm run build` en el mismo entorno, Vite 8.3.5. Los tamaños son minificados y los valores gzip son los reportados por Vite.

| Activo / escenario | Antes | Después | Diferencia |
|---|---:|---:|---:|
| Entry `app` | 517.75 kB / 159.53 kB gzip | 255.77 kB / 85.80 kB gzip | -261.98 kB / -73.73 kB gzip |
| Chunk `es-PE` | incluido en `app` | 137.04 kB / 38.61 kB gzip | separado |
| Chunk `en` | incluido en `app` | 125.01 kB / 35.95 kB gzip | separado |
| Arranque `es-PE`, entry + locales | 517.75 kB / 159.53 kB gzip | 392.81 kB / 124.41 kB gzip | -124.94 kB / -35.12 kB gzip |
| Arranque `en`, entry + fallback | 517.75 kB / 159.53 kB gzip | 517.82 kB / 160.36 kB gzip | +0.07 kB / +0.83 kB gzip |
| Entry + ambos catálogos | 517.75 kB / 159.53 kB gzip | 517.82 kB / 160.36 kB gzip | prácticamente igual |

El entry se reduce 50.60 % sin gzip y 46.22 % gzip. Para el locale predeterminado, el conjunto entry + catálogo se reduce 24.13 % sin gzip y 22.01 % gzip. El total de código de entry y ambos idiomas no disminuye: la optimización evita descargar inglés inicialmente para `es-PE`, no elimina traducciones.

### Solicitudes

Contando el entry y los chunks de locale, sin incluir CSS, fuentes ni el chunk de la pantalla Inertia:

- Antes: 1 solicitud JavaScript de bootstrap; no había solicitudes de locale.
- Después con `es-PE`: 2 solicitudes (entry + español), una solicitud adicional.
- Después con `en`: 3 solicitudes (entry + inglés + fallback español), dos adicionales y paralelas.
- Cambio `es-PE` → `en`: una solicitud de locale la primera vez.
- Cambio `en` → `es-PE`, cambios repetidos y navegación Inertia: cero solicitudes nuevas de locale durante la misma sesión de página.

El número total de solicitudes de una pantalla depende de sus imports dinámicos y del comportamiento de preload/caché del navegador; esos imports ya existían y no forman parte del cambio. No se realizaron mediciones de red o tiempo en navegador, por lo que no se afirma una mejora de tiempo de carga.

## Archivos

### Creados

- `resources/js/i18n/locales/en.ts`
- `resources/js/i18n/locales/es-PE.ts`
- `docs/design/admin-stitch/FASE_26_41_I18N_OPTIMIZATION.md`

### Modificados

- `resources/js/app.ts`
- `resources/js/components/admin/AdminHeader.vue`
- `resources/js/i18n/index.ts`
- `scripts/validate-i18n.mjs`

### Eliminado

- `resources/js/i18n/messages.ts` (reemplazado por los dos catálogos físicos).

No se añadieron dependencias, migraciones, `manualChunks` ni cambios de `chunkSizeWarningLimit`.

## Validaciones

- `npm run test:i18n`: aprobado; 2919 mensajes Vue, 45 etiquetas de sidebar y 43 mensajes Laravel por locale.
- `npm run typecheck`: aprobado.
- `npm run build`: aprobado.
- Guardia `TestingDatabaseConnectionTest`: aprobada contra `pgsql.testing/commerce_testing`.
- `AdminLocalizationTest` y `AuthenticationTest`: 14 pruebas, 97 aserciones, aprobadas.
- `npm audit`: 0 vulnerabilidades.
- `git diff --check`: aprobado.

La suite Laravel completa no se ejecutó porque no se modificó la integración backend; se ejecutaron las suites relevantes de localización y autenticación después de verificar la base aislada.

## Riesgos y recomendación

- El arranque inglés descarga ambos catálogos y tiene un overhead gzip pequeño por los wrappers de chunks. Esto es consecuencia directa de mantener disponible el fallback español completo.
- El arranque ahora depende de uno o dos imports dinámicos antes de montar Vue. Ante un error de red inicial, la aplicación no monta contenido con claves sin resolver; el error queda como fallo de carga del bootstrap.
- Separar catálogos completos aumenta la legibilidad de la frontera por locale, aunque los archivos generados son extensos.

**Dictamen:** aceptar la optimización. Produce una reducción verificable de 35.12 kB gzip para el locale predeterminado y reduce sustancialmente el entry, sin regresiones detectadas ni cambios funcionales. El beneficio se concentra en `es-PE`; para `en` la descarga total permanece esencialmente igual por el fallback obligatorio.
