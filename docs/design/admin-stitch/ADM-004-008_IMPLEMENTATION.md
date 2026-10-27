# FASE 26.37 — Access / Settings · ADM-004–008

## Alcance e identificación

| ID | Pantalla | Ruta | Contrato real |
|---|---|---|---|
| ADM-004 | Access matrix | `GET /admin/access` | Matriz readonly de roles del sistema y permisos efectivos de la membership actual. |
| ADM-005 | Settings hub | `GET /admin/settings` | Hub derivado de setup, visible con `settings.view`, con tarjetas según permisos efectivos. |
| ADM-006 | Company settings | `GET/PATCH /admin/settings/general` | Lectura con `settings.view`; actualización exclusiva del nombre con `settings.manage`. |
| ADM-007 | Team & Access | `GET /admin/settings/team` y endpoints de members/invitations | Miembros, roles, estados e invitaciones; lectura con `members.view` y mutaciones con `members.manage`. |
| ADM-008 | Notification preferences | `GET/PATCH /admin/settings/notifications` | Preferencias personales por Tenant/usuario; la ruta exige contextos Tenant y Store activos. |

Todas las rutas administrativas preservan sesión/cookie Laravel, `auth.session`, correo verificado, CSRF y resolución del Tenant. No se modificaron backend, dominio, modelos, rutas, Gates, matriz RBAC ni contratos HTTP. No se generó Stitch.

## ADM-004 — Access matrix

- Muestra el Tenant actual, rol actual, permisos efectivos y los tres roles globales `OWNER`, `ADMIN` y `MEMBER` en el orden del backend.
- Conserva el eager loading de permisos ordenados y no agrega consultas, filtros ni paginación.
- Presenta una matriz compacta en desktop y cards por rol en mobile. Las etiquetas humanas se traducen, mientras las claves técnicas persistidas permanecen visibles como código.
- Es totalmente readonly. No existen roles personalizados, CRUD de roles, edición individual ni overrides por usuario.
- Al cambiar Tenant, `PermissionChecker::currentMembership()` y las props compartidas vuelven a derivar rol y grants; no se reutilizan permisos del contexto anterior.

## ADM-005 — Settings hub

- Reutiliza exclusivamente `setup`, `store` y permisos compartidos. El estado se deriva mediante `AdminSetupStatus`; no se persisten flags de avance.
- Expone identidad de empresa y, según permisos, Stores/localización, inventario, precios, pagos, fiscal, impuestos, SUNAT y Team.
- Las áreas propias de Store explican la ausencia de una Store actual. El hub no muta datos ni bloquea operaciones por advertencias de setup.
- Los estados `Configured`, `Needs attention`, `Beta configured` y `Not configured`, y todos los detalles, se presentan en es-PE/en sin revelar credenciales SUNAT.

## ADM-006 — Company settings

- Props autoritativas: nombre, slug y status del Tenant activo.
- El formulario acepta únicamente `name`; Laravel elimina espacios externos y exige valor, string y máximo 120 caracteres.
- OWNER (`settings.manage`) puede guardar; ADMIN (`settings.view`) recibe un formulario readonly. MEMBER no puede abrir la ruta.
- Slug y status se muestran sin controles porque no existe operación HTTP para cambiarlos.
- La UI localiza confirmación y validación, evita doble envío y no acepta `tenant_id`.

## ADM-007 — Team & Access

### Lectura

- Lista completa no paginada de memberships del Tenant activo con usuario, correo, rol, status, fecha de ingreso y permisos efectivos.
- Lista únicamente invitaciones `PENDING`; una pendiente vencida se marca como vencida y todavía puede reenviarse o revocarse según el servicio existente.
- Incluye los tres roles globales con UUID backend. No existen búsqueda, filtros, paginación, custom roles ni eliminación de usuarios.

### Mutaciones existentes

- Invitar por correo normalizado y rol de sistema; máximo 255 y rate limit por Tenant/actor.
- Cambiar el rol de otra membership.
- Suspender una membership mediante status `SUSPENDED` y reactivarla sin crear otra fila.
- Reenviar una invitación, rotando token y expiración, o revocarla.
- La UI confirma rol, suspensión, reactivación, reenvío y revocación; bloquea envíos duplicados y muestra errores accesibles.

### Restricciones

- `members.view`: OWNER y ADMIN ven la página. `members.manage`: solo OWNER muta en la matriz actual.
- No se permite cambiar el propio rol ni suspender la propia membership.
- El Tenant debe conservar al menos un OWNER activo. La UI deshabilita preventivamente al último owner visible; Laravel vuelve a comprobar bajo locks transaccionales.
- Todas las búsquedas de member/invitation parten de relaciones del Tenant bloqueado. IDs de otro Tenant devuelven 404.
- Invitaciones almacenan únicamente SHA-256 del token; ningún token o enlace seguro se serializa a Vue.
- ADMIN recibe representación readonly completa. Ocultar controles no sustituye Gates ni FormRequests.

## ADM-008 — Notification preferences

- El registro es personal por `(tenant_id, user_id)`. La ausencia de fila crea defaults: `email_enabled=false` y `muted_types=[]`.
- El selector de correo es opt-in. Silenciar un tipo opcional suprime entrega in-app y por correo para ese tipo.
- `FISCAL_REJECTED` y `FISCAL_UNKNOWN` son obligatorios y no pueden silenciarse; FormRequest y routing vuelven a validarlo. El correo de tipos obligatorios sigue requiriendo que email esté habilitado.
- `TEAM_INVITATION` se excluye porque el invitado todavía no es member del Tenant.
- Las preferencias no alteran el ledger de auditoría. Cambiar Tenant carga otro registro; Store es requisito contextual de ruta pero no forma parte de la clave de preferencia.
- Todo miembro activo puede actualizar sus propias preferencias; no existe edición de preferencias ajenas ni modo readonly.

## Seguridad y aislamiento

- `TenantContext` es la única autoridad de empresa; ninguna pantalla acepta `tenant_id` del frontend.
- Las operaciones Team se limitan al Tenant activo y requieren permiso backend. IDs manipulados no permiten mutar memberships, invitations o roles ajenos.
- Roles aceptados deben ser globales y `is_system=true`; no se modificó la matriz.
- Suspender una membership invalida su Tenant/Store context en el siguiente request protegido. Team es tenant-wide y no cambia al seleccionar otra Store.
- Preferencias se resuelven para el usuario autenticado y Tenant actual; cambiar empresa no hereda datos de la anterior.
- Se preservan Auth, correo verificado, sesiones, CSRF, rate limits, locking del último OWNER y auditoría existente.

## i18n y responsive

- Se añadieron catálogos `access.*`, `settings.*` y `notifications.preferencePage.*` para en/es-PE.
- Roles, statuses, setup, acciones, ayudas, confirmaciones, errores conocidos, permisos humanos y accesibilidad están localizados. Nombres, correos, slugs y claves técnicas no se traducen.
- ADM-004 usa matriz desktop y cards mobile. ADM-007 usa tabla desktop y cards mobile, evitando el scroll horizontal anterior.
- Formularios y acciones se apilan a 390 px; no hay navegación inferior.

## Rendimiento y limitaciones

- Se preservan consultas, eager loading y colecciones existentes; no se añaden requests automáticos, polling ni dependencias.
- Team continúa sin paginación por contrato. Access y Settings no tienen filtros o búsqueda backend.
- Pendiente acumulado: revisión visual humana desktop/mobile 390 px.
- Omitido deliberadamente: Stores ADM-009–012, CRUD de usuarios/roles/permisos, recuperación de contraseña, perfil, backend, migraciones y servicios externos.

## Validación

- Guard aislado `pgsql.testing/commerce_testing`: 1 test, 4 assertions.
- Access + AdminSettings + preferencias/notificación por email: 50 tests, 235 assertions.
- Auth + Tenancy + RBAC focalizado: 55 tests, 292 assertions.
- Suite Laravel completa: 636 tests, 4,429 assertions.
- i18n: 2,802 mensajes Vue, 45 sidebar labels y 43 mensajes Laravel por locale.
- `npm run typecheck`, `npm run build` y `git diff --check`: aprobados.
- Bundles: ADM-004 5.15 kB / 1.85 kB gzip; ADM-005 3.70 kB / 1.51 kB; ADM-006 4.25 kB / 1.71 kB; ADM-007 14.47 kB / 4.03 kB; ADM-008 6.24 kB / 2.02 kB.
- Build: 828 módulos. Advertencias no bloqueantes existentes: fallback opcional `fontaine` y chunk principal mayor a 500 kB.
