# FASE 26.15.3 — Corrección controlada de vulnerabilidades npm

Fecha: 2026-10-25

## Alcance

Se corrigieron exclusivamente las vulnerabilidades reportadas por `npm audit` en
el frontend. No se modificaron servicios de dominio, modelos, migraciones,
RBAC, `TenantContext`, `StoreContext`, Stitch ni la base de datos de desarrollo.
No se hizo commit ni push.

## Entorno

| Entorno | Node | npm |
| --- | --- | --- |
| Host Windows | v24.14.0 | 11.9.0 |
| `laravel.test` (Docker) | v24.21.0 | 12.2.0 |

El frontend usa Vite 8, `@vitejs/plugin-vue`, `laravel-vite-plugin`,
`@tailwindcss/vite`, Vue 3, Inertia y `vue-i18n`. Docker monta
`node_modules` en el volumen nombrado `sail-node-modules`; el
`node_modules` del host Windows no se eliminó.

## Auditoría inicial

`npm audit` (host y Docker, mismo lockfile):

- 5 vulnerabilidades: **2 critical, 3 high**.
- Paquetes reportados: `shell-quote` (critical), `concurrently` (critical por
  dependencia vulnerable), `vite` (high), `follow-redirects` (high),
  `source-map-js` (high).
- `npm audit --omit=dev`: **2 high** (`follow-redirects` y `source-map-js`).

Rutas exactas de dependencia:

```text
concurrently@10.0.5 -> shell-quote@1.9.0
axios@1.20.0 -> follow-redirects@1.16.0
vite@8.2.2 -> postcss@8.5.28 -> source-map-js@1.2.1
@tailwindcss/node@4.3.3 -> source-map-js@1.2.1
@intlify/message-compiler@11.1.12 -> source-map-js@1.2.1
@vue/compiler-core@3.5.42 -> source-map-js@1.2.1
```

Advisories incluidos:

- `GHSA-pqg4-j6r4-53mv` — `shell-quote` inyección de comandos en `quote()`.
- `GHSA-q3jh-wrc6-jgq8` y `GHSA-8r9p-f939-6h3c` — `follow-redirects`
  encabezados y credenciales.
- `GHSA-68fv-2mgg-jv7q` — `source-map-js` denegación de servicio.
- `GHSA-698v-4r3x-3vcv`, `GHSA-r825-w8mm-5pxw`, `GHSA-587h-7wmj-h5pm`,
  `GHSA-9jrq-w75r-8gcw`, `GHSA-rq7h-c2jc-7f22`, `GHSA-vfpm-58rq-9qcg` — Vite
  y `launch-editor`.

## Cambios aplicados

Se actualizaron solo dependencias directas afectadas y se dejó que el lockfile
resolviera transitivas dentro de rangos compatibles. **No se usaron
`overrides`** y no se ejecutó `npm audit fix` ni `npm audit fix --force`.

| Paquete | Tipo | Antes | Después | Justificación |
| --- | --- | --- | --- | --- |
| `concurrently` | dev directa | 10.0.5 | 10.0.6 | Única versión que fija `shell-quote@1.12.0`; corrige la vulnerabilidad critical. |
| `shell-quote` | transitiva de `concurrently` | 1.9.0 | 1.12.0 | Versión corregida de la inyección de comandos. |
| `vite` | dev directa | 8.2.2 | 8.3.5 | Corrige todos los advisories de Vite; misma rama 8.x. |
| `rolldown` | transitiva de `vite` | 1.2.7 | 1.2.15 | Requerida por Vite 8.3.5. |
| `@rolldown/binding-*` | opcionales de `rolldown` | 1.2.7 | 1.2.15 | Bindings por plataforma requeridos por el nuevo `rolldown`. |
| `@oxc-project/types` | transitiva de `rolldown` | 0.148.0 | 0.155.0 | Requerida por `rolldown@1.2.15`. |
| `follow-redirects` | producción, vía `axios` | 1.16.0 | 1.16.2 | Corrección compatible; `axios@1.20.0` declara `^1.16.0`. |
| `source-map-js` | producción, transitiva | 1.2.1 | 1.2.2 | Corrección compatible; todos los padres aceptan `^1.2.1`/`^1.0.2`. |
| `postcss` | transitiva de `vite` | 8.5.28 | 8.5.31 | Actualización patch dentro del rango de Vite 8.3.5. |
| `nanoid` | transitiva de `postcss` | 3.3.18 | 3.3.20 | Actualización patch dentro del rango de `postcss`. |

`package.json` cambió únicamente en:

```diff
-        "concurrently": "^10.0.3",
+        "concurrently": "^10.0.6",
-        "vite": "^8.0.0",
+        "vite": "^8.3.5",
```

`axios` permanece en `1.20.0` (ya era la última versión); la corrección se
resolvió en su transitiva `follow-redirects`.

## Compatibilidad

- Motores Node: `vite@8.3.5` exige `^20.19.0 || >=22.12.0` y
  `concurrently@10.0.6` exige `>=22`. Host (24.14.0) y Docker (24.21.0)
  cumplen.
- `@vitejs/plugin-vue@6.0.8` acepta `vite ^8.0.0`.
- `laravel-vite-plugin@3.2.0` declara peer `vite ^8.0.0`.
- `@tailwindcss/vite@4.3.3` acepta `vite ^8`.
- `vue-i18n@11.1.12` y Vue 3.5.42 no cambiaron.
- El lockfile actualizado se instaló con `npm ci` dentro del volumen Linux
  `sail-node-modules`; el host se actualizó con `npm install` en el mismo
  `node_modules` (sin borrarlo).

## Resultados finales de auditoría

- `npm audit` (host): **found 0 vulnerabilities**.
- `npm audit --omit=dev` (host): **found 0 vulnerabilities**.
- `npm audit` (`laravel.test`): **found 0 vulnerabilities**.
- `npm audit --omit=dev` (`laravel.test`): **found 0 vulnerabilities**.

## Pruebas ejecutadas

- Guard de base aislada `TestingDatabaseConnectionTest`: 1 test, 4 assertions.
- `tests/Feature/Auth`, `Localization`, `Catalog`, `Media` y `Analytics`:
  **122 tests, 940 assertions**, todos correctos, exclusivamente sobre
  `pgsql.testing/commerce_testing`.
- `npm run test:i18n`: 436 mensajes Vue y 24 mensajes JSON de Laravel por
  idioma, correcto.
- `npm run typecheck`: correcto en host y en Docker.
- `npm run build`: correcto en host (exit 0, `✓ built in 3.66s`) y en Docker
  (exit 0, `✓ built in 7.9s`).
- `git diff --check`: correcto.
- No se ejecutaron migraciones ni se usó la base `commerce` de desarrollo.

## Riesgos restantes

- `npm audit` en cero no equivale a seguridad total: solo refleja los
  advisories publicados y las versiones resueltas en este lockfile. Nuevas
  vulnerabilidades pueden aparecer después.
- La mayor parte del toolchain afectado es de desarrollo (Vite,
  `concurrently`); el riesgo en producción se reduce, pero el servidor de
  desarrollo no debe exponerse a redes no confiables.
- Persiste el aviso no bloqueante de `fontaine` opcional para fallbacks de
  fuentes de Laravel; no es una vulnerabilidad.
- El lockfile incluye bindings opcionales de Rolldown para varias plataformas.
  Cualquier instalación futura debe repetir `npm ci` en el entorno correcto
  (Windows host o volumen Linux) y no compartir `node_modules` entre ambos.
- Diferencia de versiones de npm entre host (11.9.0) y Docker (12.2.0): el
  lockfile es compatible, pero conviene mantener npm actualizado.
- Se debe repetir `npm audit`, `test:i18n`, `typecheck`, `build` y la suite de
  pruebas tras cualquier actualización futura de dependencias.

## Estado Git

- Rama `development`; cambios sin commit ni push.
- Modificados en esta fase: `package.json`, `package-lock.json`.
- Creado: `docs/security/FASE_26_15_3_NPM_SECURITY.md`.
- Se preservaron todos los cambios previos del worktree y `.opencode/plugins/`.
- `.env` no se modificó en esta fase.
