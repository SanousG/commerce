# Fase 26.43 — Consolidación final del frontend

## Estado final

El frontend administrativo queda consolidado con:

- 69 pantallas administrativas ADM-001–069 terminadas y preservadas.
- 5 pantallas reales de autenticación: Login, Register, Forgot Password, Reset Password y Verify Email.
- Catálogos Vue separados para `es-PE` y `en`, cargados dinámicamente.
- Persistencia de idioma autenticado mediante `users.admin_locale` y fallback `es-PE`.
- Validador de 2932 mensajes Vue, 45 etiquetas de sidebar y 43 mensajes Laravel por locale.

No se modificaron backend, rutas, RBAC, reglas comerciales, contextos tenant/store ni contratos HTTP durante la consolidación.

## Optimización i18n

Mediciones de producción reportadas por Vite 8.3.5:

| Activo / escenario | Antes | Final |
|---|---:|---:|
| Entry principal | 517.75 / 159.53 kB gzip | 255.84 / 85.83 kB gzip |
| Inicio `es-PE`, entry + locale | 517.75 / 159.53 kB gzip | 393.71 / 124.72 kB gzip |
| Chunk `es-PE` | incluido en entry | 137.87 / 38.89 kB gzip |
| Chunk `en` | incluido en entry | 125.79 / 36.20 kB gzip |

La reducción del entry final es de 261.91 kB minificados y 73.70 kB gzip. La descarga inicial relevante para el locale predeterminado disminuye 124.04 kB minificados y 34.81 kB gzip. El usuario inglés descarga además el fallback español, por lo que el beneficio principal corresponde a `es-PE`.

## Autenticación

Las cinco vistas Auth usan el diseño Executive Precision B2B, mantienen una sola columna en mobile y una composición dividida en desktop. Se preservan:

- sesión/cookie Laravel y CSRF;
- regeneración e invalidación de sesión;
- remember cookie;
- rate limits;
- normalización y validaciones de FormRequests;
- recuperación nativa, tokens y protección anti-enumeración;
- verificación mediante enlaces firmados;
- redirecciones posteriores a login, registro y verificación.

No se añadieron proveedores sociales, JWT, endpoints ni flujos artificiales.

## Validación final

- Guardia `pgsql.testing/commerce_testing`: 1 test, 4 aserciones, aprobado.
- Suite Laravel completa: 636 tests, 4429 aserciones, aprobada.
- `npm run test:i18n`: aprobado; 2932 mensajes Vue por locale.
- `npm run typecheck`: aprobado.
- `npm run build`: aprobado.
- `npm audit`: 0 vulnerabilidades.
- `npm audit --omit=dev`: 0 vulnerabilidades.
- `git diff --check`: aprobado.

PHPUnit se ejecutó secuencialmente contra `pgsql.testing/commerce_testing`. No se ejecutaron migraciones, no se modificó PostgreSQL de desarrollo y no se contactó SUNAT ni servicios externos reales.

## Bundles Auth finales

| Chunk | Tamaño minificado / gzip |
|---|---:|
| `AuthLayout` | 4.30 / 1.70 kB |
| `Login` | 3.38 / 1.42 kB |
| `Register` | 4.00 / 1.36 kB |
| `ForgotPassword` | 2.81 / 1.31 kB |
| `ResetPassword` | 3.20 / 1.22 kB |
| `VerifyEmail` | 2.26 / 1.10 kB |
| CSS global | 79.68 / 16.23 kB |

## Commits locales

1. `c4dc7a9` — `perf(i18n): lazy load locale catalogs`
2. `feat(auth): redesign authentication screens` — commit que incluye esta documentación; su hash final se reporta en el estado Git de entrega.

Los cuatro commits previos de FASE 26.40 permanecen en el historial sin alteraciones.

## Archivos excluidos

- `.env` y variantes.
- `public/build/`.
- `.opencode/plugins/stitch-env.js`.
- Credenciales, certificados y archivos locales.

No se instalaron dependencias ni se generaron migraciones.

## Pendientes no bloqueantes

- Revisión visual manual adicional en navegadores/dispositivos reales.
- Un selector de idioma para invitados requeriría un contrato backend explícito para sincronizar mensajes Vue y Laravel; no forma parte del alcance actual.

No quedan pendientes funcionales o de pruebas para cerrar FASES 26.41–26.43.
