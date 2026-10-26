# FASE 26.15 — Internacionalización del administrador

## Arquitectura

- `vue-i18n` centraliza los mensajes del shell y de ADM-001/ADM-013–018.
- `es-PE` es el idioma predeterminado y fallback; `en` es la alternativa.
- `users.admin_locale` conserva la preferencia global de cada usuario. No depende
  de Tenant, Store ni sesión.
- `SetUserLocale` establece el locale de Laravel antes de controladores y
  validación. Inertia comparte el locale y el catálogo soportado.
- `PATCH /admin/locale` actualiza exclusivamente al usuario autenticado; no
  acepta IDs de usuario, Tenant o Store.

Los nombres comerciales, nombres de productos/categorías/opciones, SKU, slugs,
IDs, monedas y valores internos continúan sin traducción.

## Migración pendiente en desarrollo

La migración `2026_10_25_000001_add_admin_locale_to_users_table.php` añade la
columna `users.admin_locale`, default `es-PE`, y una restricción PostgreSQL para
`es-PE`/`en`.

## Infraestructura vigente

- Desarrollo usa PostgreSQL local en Docker: servicio `pgsql`, base `commerce`,
  accesible por Laravel dentro de la red Compose en el puerto 5432. El puerto
  publicado al host se configura por separado.
- Testing usa exclusivamente el PostgreSQL local aislado del servicio
  `pgsql.testing`, base y usuario `commerce_testing`, sin puerto publicado al
  host. `phpunit.xml` fuerza ese destino y `tests/TestCase.php` falla de forma
  cerrada ante otra conexión.
- Supabase no se utiliza actualmente para desarrollo ni testing.
- `RefreshDatabase` solo puede usarse después de que pase
  `TestingDatabaseConnectionTest` contra `pgsql.testing/commerce_testing`.

## Diagnóstico 2026-10-25

- Los servicios `laravel.test`, `pgsql`, `pgsql.testing`, `redis` y `mailpit`
  estaban activos; ambos PostgreSQL estaban saludables.
- La conexión efectiva de desarrollo fue `pgsql:5432/commerce`, con servidor
  dentro de la red Docker. No se encontró conexión efectiva a Supabase.
- La migración de `admin_locale` ya figuraba ejecutada en batch 1 y no había
  otras migraciones pendientes. `migrate --pretend --path=...` respondió
  `Nothing to migrate`.
- `users.admin_locale` existe como `varchar(5) NOT NULL DEFAULT 'es-PE'` y la
  constraint `users_admin_locale_check` admite únicamente `es-PE` y `en`.
- No debe ejecutarse ningún comando de migración para esta fase en desarrollo.
  Si la migración estuviera pendiente en otro entorno local previamente
  verificado, el comando acotado sería:

  ```bash
  docker compose exec -T laravel.test php artisan migrate --path=database/migrations/2026_10_25_000001_add_admin_locale_to_users_table.php
  ```

  Requiere autorización explícita antes de ejecutarse.

## Validación de internacionalización

- Implementado: locales soportados `es-PE` y `en`, fallback de Vue `es-PE`,
  selector en el header, persistencia global en el usuario autenticado,
  middleware Laravel, catálogos Laravel/Vue, validación y mensajes de Catalog.
- La preferencia no recibe IDs de Tenant/Store y las pruebas cubren persistencia
  al cerrar sesión y cambiar de Tenant. El locale comercial de Store permanece
  independiente.
- Nombres comerciales, nombres de productos/categorías/opciones, SKU, slugs,
  IDs, monedas y valores internos no se traducen automáticamente.
- Hallazgo de 26.15.1, resuelto en 26.15.2: el `.env` local tenía
  `APP_LOCALE=en` y `APP_FALLBACK_LOCALE=en`; el locale global efectivo de
  Laravel no era `es-PE` aunque la configuración de administración sí lo usaba.
- Hallazgo de 26.15.1, resuelto en 26.15.2: `resources/js/i18n/messages.ts`
  contenía secuencias letra-`?`-letra en cadenas españolas donde se perdieron
  tildes, además de separadores convertidos a `?`. Se repararon y se añadió una
  comprobación de integridad textual.

## Auditoría de dependencias

- `npm audit`: 5 vulnerabilidades (3 high, 2 critical). Afectan
  `follow-redirects`, `source-map-js`, `vite`, `shell-quote` y, por tránsito,
  la dependencia directa de desarrollo `concurrently`.
- `npm audit --omit=dev`: 2 vulnerabilidades high en dependencias transitivas
  de producción (`follow-redirects` y `source-map-js`).
- npm informa que existe corrección mediante `npm audit fix`, pero no se aplicó
  automáticamente. Las actualizaciones deben revisarse mediante cambios
  explícitos del lockfile y repetir typecheck, build y pruebas.

## Verificaciones ejecutadas

- Guard de testing: 1 test, 4 assertions, correcto.
- Localization + Auth + Catalog, ejecutados secuencialmente en un único proceso:
  79 tests, 524 assertions, todos correctos.
- `npm run typecheck`: correcto.
- `npm run build`: correcto desde el host. Dentro de `laravel.test` falló por el
  binding opcional Linux de Rolldown ausente en el `node_modules` compartido;
  no se reinstalaron dependencias ni se modificó el lockfile para ocultarlo.
- `git diff --check`: correcto.
- Una ejecución inicial incorrectamente paralela de las tres suites produjo
  colisiones de `RefreshDatabase` sobre el mismo esquema de testing. Se descartó
  ese resultado y se confirmó el resultado válido mediante ejecución
  secuencial. La base de desarrollo no fue utilizada por ninguna prueba.

No se modifica el locale comercial de Store.

## FASE 26.15.2 — Corrección de locale y textos dañados

### Configuración efectiva de idioma

- Solo se modificó el `.env` local de desarrollo (archivo gitignored):
  `APP_LOCALE=es-PE` y `APP_FALLBACK_LOCALE=es-PE`. No se modificaron otros
  valores, credenciales ni `APP_TIMEZONE=America/Lima`.
- Se ejecutó únicamente `php artisan config:clear` dentro de `laravel.test`.
  No se ejecutaron migraciones ni se alteró PostgreSQL de desarrollo.
- Verificación con Laravel: `config('app.locale')`, `config('app.fallback_locale')`,
  `app()->getLocale()`, `config('localization.default')` y
  `config('localization.fallback')` devuelven `es-PE`; `localization.supported`
  sigue siendo `['es-PE', 'en']`.
- Los catálogos Laravel `lang/es-PE` y `lang/en` resuelven correctamente con
  `es-PE`; el fallback ya no cae a inglés.

### Reparaciones realizadas

- 41 cadenas dañadas reparadas. El catálogo contenía 43 cadenas con `?`; dos
  eran interrogaciones legítimas (`¿Archivar {name}?` en productos y
  categorías) y se conservaron intactas.
- 45 secuencias letra-`?`-letra corregidas dentro de palabras (tildes y `ñ`),
  más separadores y símbolos tipográficos convertidos a `?`.
- Separadores reconstruidos como `·`; comparación de stock como `≤`; elipsis
  como `…`; `Único` con tilde y mayúscula.
- Se conservaron todas las claves, la estructura del catálogo, los placeholders,
  la interpolación, las reglas de pluralización y los textos accesibles.
- Quedan exactamente 5 interrogaciones legítimas con `¿` y `?`:
  `products.archiveConfirm`, `categories.archiveConfirm`,
  `auth.login.forgot`, `auth.login.newHere` y `auth.register.existing`.
- No se tradujeron nombres comerciales, SKU, slugs, IDs, monedas ni valores
  internos de enums.

### Autenticación antes del middleware de usuario

- Las pantallas de autenticación recibían `es-PE`, pero su texto estaba
  hardcodeado en inglés. Se añadió el bloque `auth.*` a `vue-i18n` y se
  tradujeron `Login`, `Register`, `ForgotPassword`, `ResetPassword`,
  `VerifyEmail` y `AuthLayout`.
- Se añadieron catálogos Laravel `lang/es-PE/auth.php`, `lang/en/auth.php`,
  `lang/es-PE/passwords.php` y `lang/en/passwords.php`, además de las claves
  `boolean`, `confirmed`, `email` y atributos de autenticación en
  `validation.php`.
- Se añadieron al JSON de Laravel los textos de las notificaciones de
  verificación y restablecimiento de contraseña, en ambos idiomas.

### Cobertura español/inglés

- `Dashboard` → `Panel de control`; `Products` → `Productos`;
  `Categories` → `Categorías`; `Create product` → `Crear producto`;
  `Save changes` → `Guardar cambios`; `All company` → `Toda la empresa`;
  `Search` → `Buscar`; `Archive` → `Archivar`.
- Revisión de cobertura con llamadas `t()`: AdminHeader 10, AdminSidebar 11,
  MobileDrawer 5, Dashboard 122, Products Index/Create/Edit 37/33/116,
  Categories Index/Create/Edit 29/30/40. El único texto literal restante es el
  nombre comercial `Commerce`, que no se traduce.

### Pruebas de persistencia y locale

- Invitado: `/login` responde `es-PE` y el error de credenciales se muestra en
  español antes de que exista usuario autenticado.
- Preferencia ausente o no soportada: `SetUserLocale` cae a `es-PE`.
- Cambio a inglés y de vuelta a español: persiste en `users.admin_locale`,
  sobrevive al cierre de sesión y no altera `StoreContext::SESSION_KEY` ni el
  Tenant activo.
- `PATCH /admin/locale` sigue autenticado, validado y aislado entre usuarios.

### Validación automatizada de traducciones

- Nuevo `scripts/validate-i18n.mjs` y comando `npm run test:i18n`.
- Valida paridad de claves y placeholders entre `es-PE`/`en` (436 mensajes Vue
  y 24 mensajes JSON de Laravel por idioma), mojibake, secuencias
  letra-`?`-letra, separadores ` ? `, apertura `¿`/`¡`, fallback `es-PE` y
  cadenas representativas (`Categorías`, `Descripción`, `Contraseña`, `¿`, `¡`).
- No rechaza interrogaciones legítimas: solo exige `¿` cuando la cadena termina
  en `?`.
- Requiere Node con type stripping nativo para importar `messages.ts`
  (Node 22.18+/24). No añade dependencias.

### Resultados de verificación

- Guard de base aislada: 1 test, 4 assertions, correcto.
- Localization + Auth + Catalog (`CategoryTest`, `ProductCreationTest`,
  `ProductManagementTest`): 70 tests, 505 assertions, todos correctos, sobre
  `pgsql.testing/commerce_testing`.
- `npm run test:i18n`: correcto.
- `npm run typecheck`: correcto en host y en `laravel.test`.
- `npm run build`: correcto en host y en `laravel.test` (`✓ built in 8.25s`,
  exit code 0).
- Pint: 7 archivos PHP, correcto.
- `git diff --check`: correcto.
- La base `commerce` de desarrollo no fue usada por ninguna prueba y no se
  ejecutaron migraciones.

### Aislamiento de dependencias en Docker

- Causa del fallo: `.:/var/www/html` comparte el `node_modules` del host Windows
  con Linux; Rolldown busca `@rolldown/binding-linux-x64-gnu`, ausente en esa
  instalación.
- Solución aplicada: volumen nombrado `sail-node-modules` montado en
  `/var/www/html/node_modules`, igual que el volumen ya existente de `vendor`.
- Se recreó solo `laravel.test` y se ejecutó `npm ci` únicamente dentro del
  volumen. El `node_modules` del host no se borró ni se reemplazó.
- El build dentro del contenedor ya funciona. Persiste el aviso no bloqueante
  de `fontaine` opcional para fallbacks de fuentes de Laravel.

### Riesgo pendiente de dependencias npm

- `npm audit`: 5 vulnerabilidades (3 high, 2 critical). `npm audit --omit=dev`:
  2 high en dependencias de producción.
- No se ejecutó `npm audit fix` ni actualizaciones masivas.

| Paquete afectado | Dependencia padre | Versión vulnerable | Versión corregida |
| --- | --- | --- | --- |
| `shell-quote` | `concurrently` (dev) | 1.8.4–1.10.0 | `concurrently@10.0.6` (fija `shell-quote@1.12.0`) |
| `vite` | directa (dev) | 8.2.0–8.2.2 | `vite@8.3.5` |
| `follow-redirects` | `axios` (prod) | 1.16.0 | `follow-redirects@1.16.2` vía lockfile; `axios@1.20.0` acepta `^1.16.0` |
| `source-map-js` | transitiva (prod/dev) | 1.0.0–1.2.1 | `source-map-js@1.2.2` vía lockfile |

Plan controlado sugerido, en una tarea aparte: actualizar `concurrently` y
`vite` de forma explícita, refrescar solo las transitivas `follow-redirects` y
`source-map-js` en el lockfile, y repetir `npm audit`, `npm run test:i18n`,
`npm run typecheck`, `npm run build` y la suite de pruebas. Verificar
compatibilidad de `@vitejs/plugin-vue`, `laravel-vite-plugin` y
`@tailwindcss/vite` antes de aplicar.

### Estado Git

- Rama `development`, todos los cambios sin commit ni push.
- `.env` es gitignored; su cambio de locale no aparece en `git status`.
- Archivos nuevos de esta fase: `scripts/validate-i18n.mjs`,
  `lang/es-PE/auth.php`, `lang/en/auth.php`, `lang/es-PE/passwords.php`,
  `lang/en/passwords.php`.
- Archivos modificados de esta fase: `resources/js/i18n/messages.ts`,
  `resources/js/layouts/AuthLayout.vue`, las cinco pantallas de
  `resources/js/pages/auth/`, `lang/es-PE/validation.php`,
  `lang/en/validation.php`, `lang/es-PE.json`, `lang/en.json`, `package.json`,
  `compose.yaml`, `tests/Feature/Localization/AdminLocalizationTest.php`,
  `AGENTS.md` y este documento.
- Se preservaron los cambios previos del worktree y `.opencode/plugins/`.

### Pendientes de revisión manual

- Revisar visualmente login, registro, recuperación, verificación, shell,
  dashboard, productos y categorías en `es-PE` y `en` para confirmar naturalidad
  peruana y ausencia de recortes de texto.
- Confirmar en el navegador la persistencia del selector tras recarga y cierre
  de sesión.
- Programar la actualización controlada de dependencias npm descrita arriba.
- Evaluar si se instala `fontaine` o se desactiva `optimizedFallbacks`.
- `resources/js/pages/invitations/Show.vue` sigue con texto hardcodeado en
  inglés. Es una pantalla previa a la autenticación y quedó fuera del alcance
  ADM-001/ADM-013–018 de esta fase; debe traducirse en una tarea posterior.
- Las pantallas administrativas fuera de ADM-001/ADM-013–018 aún no forman
  parte del catálogo `vue-i18n` y conservan su idioma original.
