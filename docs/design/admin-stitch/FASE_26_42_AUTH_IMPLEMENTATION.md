# Fase 26.42 — Rediseño completo del módulo de autenticación

## Alcance auditado

Rama `development`, HEAD `201e062ee3e5bc319466c100ef23ed5c4eebc2b9`, conservando los cuatro commits locales y todos los cambios sin commit de FASE 26.41.

Laravel expone cinco pantallas reales de autenticación. No existe endpoint de confirmación de contraseña ni otro flujo Auth adicional.

| Pantalla | Vista Inertia | GET | Acción |
|---|---|---|---|
| Inicio de sesión | `auth/Login` | `/login` | `POST /login` |
| Registro | `auth/Register` | `/register` | `POST /register` |
| Solicitud de recuperación | `auth/ForgotPassword` | `/forgot-password` | `POST /forgot-password` |
| Restablecimiento | `auth/ResetPassword` | `/reset-password/{token}` | `POST /reset-password` |
| Verificación de correo | `auth/VerifyEmail` | `/verify-email` | `POST /email/verification-notification` |

El enlace firmado `GET /verify-email/{id}/{hash}` no renderiza una pantalla propia: verifica y redirige. Logout sigue siendo `POST /logout`.

## Contratos preservados

### Login

- Campos: `email`, `password`, `remember`.
- Normalización de email en Laravel.
- Autenticación con sesión/cookie, regeneración del identificador y remember cookie.
- Redirección al destino previsto, al dashboard o a verificación según corresponda.
- Rate limit `login` y protección CSRF sin cambios.

### Registro

- Campos: `name`, `email`, `password`, `password_confirmation`.
- Trim de nombre, normalización de email, unicidad y `Password::defaults()`.
- Emisión de `Registered`, login con sesión regenerada y redirección a verificación.
- No se crean empresas ni tiendas desde Vue.

### Recuperación

- Respuesta equivalente para correos conocidos y desconocidos para evitar enumeración.
- Token nativo del broker de Laravel, hashing y evento `PasswordReset` intactos.
- Los tokens permanecen únicamente en el contrato existente del formulario de reset; no se muestran ni registran.
- Rate limits `password-email` y `password-reset` preservados.

### Verificación

- Middleware `auth`, `auth.session`, `signed` y rate limit `verification` sin cambios.
- Reenvío de notificación y redirección al dashboard preservados.
- Logout invalida la sesión y regenera el token CSRF como antes.

No se modificaron controladores, FormRequests, middleware, rutas, guards, hashing, RBAC, `TenantContext`, `StoreContext` ni contratos HTTP.

## Rediseño visual

Se reemplazó el diseño guest verde/beige por Executive Precision B2B:

- Primary `#0F172A`.
- Secondary `#2563EB`.
- Tertiary `#0284C7`.
- Canvas `#F8F9FA`, superficies blancas y bordes `#E2E8F0`.
- Inter y JetBrains Mono ya provistas por el proyecto.

`AuthLayout` presenta en desktop una composición dividida: panel institucional oscuro y formulario sobre una tarjeta enfocada. En mobile oculta el panel decorativo, muestra branding compacto y mantiene una sola columna. No utiliza `AdminLayout`, sidebar ni navegación inferior.

Las cinco pantallas incorporan jerarquía homogénea, controles redondeados, foco visible, estados de procesamiento y enlaces secundarios consistentes. Recuperación explica explícitamente la respuesta anti-enumeración y verificación explica la vigencia del enlace sin revelar datos sensibles.

## Accesibilidad y responsive

- Labels e IDs únicos por pantalla.
- `aria-invalid` y `aria-describedby` enlazados con errores Laravel.
- `FieldError` conserva `role="alert"`; `StatusNotice` conserva `role="status"`.
- Autocomplete correcto para email, nombre y contraseñas actuales/nuevas.
- Botones bloqueados durante procesamiento, con indicador visual de carga.
- Foco global visible y foco específico de controles.
- Ancho mínimo soportado por la aplicación: 320 px; la composición mobile usa una columna, padding adaptable y no introduce anchuras fijas ni overflow horizontal.
- El contenido extenso, como registro, usa scroll vertical normal en pantallas bajas.

No existe harness de navegador o captura visual automatizada en el repositorio; la validación responsive realizada es estructural, de clases y compilación.

## Internacionalización

- Los catálogos dinámicos de FASE 26.41 permanecen separados.
- Se añadieron 13 mensajes por locale: ahora se validan 2932 mensajes Vue por idioma.
- Se tradujeron nuevos textos institucionales, ayudas, placeholders, privacidad, verificación y accesibilidad en `es-PE` y `en`.
- El validador inspecciona las cinco vistas Auth y `AuthLayout`, además de las tres claves dinámicas del panel, y compila todos los mensajes con el parser de vue-i18n para detectar sintaxis inválida.
- El montaje sigue esperando el catálogo requerido, por lo que no se renderizan claves sin resolver.

Para invitados, el contrato real de Laravel establece `es-PE` porque aún no existe un usuario cuya preferencia pueda resolverse. No se añadió un selector guest que produjera UI inglesa con errores Laravel en español, ni un endpoint/cookie nuevo. Para usuarios autenticados —incluida la pantalla de verificación— Inertia comparte `users.admin_locale` y carga `en` con fallback `es-PE` cuando corresponde.

## Bundles

Medidos con Vite 8.3.5 mediante `npm run build`. Valores minificados / gzip.

| Chunk | Antes | Después |
|---|---:|---:|
| `AuthLayout` | 1.76 / 0.82 kB | 4.30 / 1.70 kB |
| `Login` | 3.09 / 1.27 kB | 3.38 / 1.43 kB |
| `Register` | 3.58 / 1.17 kB | 4.00 / 1.36 kB |
| `ForgotPassword` | 1.87 / 0.97 kB | 2.81 / 1.31 kB |
| `ResetPassword` | 2.79 / 1.03 kB | 3.20 / 1.22 kB |
| `VerifyEmail` | 1.28 / 0.70 kB | 2.26 / 1.11 kB |
| CSS global | 76.05 / 15.59 kB | 79.68 / 16.23 kB |
| Entry `app` | 255.77 / 85.80 kB | 255.84 / 85.85 kB |
| Catálogo `es-PE` | 137.04 / 38.61 kB | 137.86 / 38.88 kB |
| Catálogo `en` | 125.01 / 35.95 kB | 125.78 / 36.19 kB |

El crecimiento se concentra en el layout visual compartido y en 3.63 kB de CSS minificado. No se añadieron dependencias ni requests de aplicación; los catálogos conservan la estrategia dinámica.

## Pruebas

- Guardia `pgsql.testing/commerce_testing`: 1 test, 4 aserciones, aprobado.
- Suite Auth completa: 29 tests, 157 aserciones, aprobada.
- Suite Tenancy/redirecciones: 19 tests, 116 aserciones, aprobada.
- Suite Laravel completa: 636 tests, 4429 aserciones, aprobada.
- `npm run test:i18n`: aprobado, 2932 mensajes Vue por locale.
- `npm run typecheck`: aprobado.
- `npm run build`: aprobado.
- `npm audit`: 0 vulnerabilidades.
- `git diff --check`: aprobado.

Todas las pruebas Laravel se ejecutaron secuencialmente dentro de Compose contra la base aislada. No se ejecutaron migraciones manuales ni se accedió a PostgreSQL de desarrollo.

## Archivos de FASE 26.42

### Modificados

- `resources/css/app.css`
- `resources/js/layouts/AuthLayout.vue`
- `resources/js/pages/auth/Login.vue`
- `resources/js/pages/auth/Register.vue`
- `resources/js/pages/auth/ForgotPassword.vue`
- `resources/js/pages/auth/ResetPassword.vue`
- `resources/js/pages/auth/VerifyEmail.vue`
- `resources/js/i18n/locales/en.ts`
- `resources/js/i18n/locales/es-PE.ts`
- `scripts/validate-i18n.mjs`

### Creado

- `docs/design/admin-stitch/FASE_26_42_AUTH_IMPLEMENTATION.md`

No se modificó ninguna pantalla ADM-001–069. No se añadieron dependencias, migraciones, endpoints, proveedores sociales ni pantallas artificiales.

## Pendientes

- Revisión visual manual en navegadores reales a 390 px y desktop cuando exista un flujo de QA visual; no hay bloqueos funcionales o de compilación.
- Un selector de idioma para invitados requeriría un contrato backend explícito para que los mensajes Laravel y Vue utilicen el mismo locale. Se omitió deliberadamente para respetar la persistencia y los contratos existentes.
