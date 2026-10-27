# FASE 26.36 — Tenancy · ADM-002–003

## Alcance

- **ADM-002 — Companies:** `GET /admin/tenants` (`admin.tenants.index`).
- **ADM-003 — Create company:** `GET /admin/tenants/create` (`admin.tenants.create`) y `POST /admin/tenants` (`admin.tenants.store`).
- Selección: `POST /admin/tenants/{tenant}/select` (`admin.tenants.select`) con UUID.

Las rutas requieren sesión Laravel y correo verificado, pero deliberadamente no requieren un Tenant activo. No se modificaron backend, dominio, rutas, contratos, middleware ni modelos; no se generó Stitch.

## Contrato funcional auditado

### ADM-002

- Lista exclusivamente memberships `ACTIVE` del usuario autenticado cuyos Tenants también están `ACTIVE`.
- Eager-load de Tenant y rol; orden natural case-insensitive por nombre después de obtener la colección.
- Props por empresa: `id`, `name`, `slug` y rol de sistema (`OWNER`, `ADMIN` o `MEMBER`). No se serializan estado, Stores, cantidad de miembros ni permisos.
- No existe búsqueda, filtro, paginación, edición, eliminación, abandono, transferencia de propiedad o administración de membresías en esta pantalla.
- Si no existe ninguna membership elegible, el controller redirige a ADM-003; el empty state de Vue es defensivo.
- La página no pasa por `ResolveTenant`, por lo que no recibe un `currentTenant` autoritativo y no marca visualmente una empresa como actual.

### ADM-003

- El único campo aceptado es `name`: requerido, string y máximo 120 caracteres.
- Cualquier usuario autenticado y verificado puede crear otra empresa; no existe un permiso RBAC adicional ni modo readonly para esta operación.
- La creación transaccional genera un slug único, crea el Tenant `ACTIVE`, asigna al creador una membership `OWNER`/`ACTIVE` con `joined_at`, y crea la lista de precios y ubicación de inventario predeterminadas.
- Al completar, Laravel selecciona el nuevo Tenant, limpia la Store previa y redirige al dashboard. Las Stores se crean mediante su flujo separado.
- La interfaz conserva validación Laravel, `FieldError`, CSRF Inertia y bloqueo de doble envío.

## Selección y contexto

- Seleccionar una empresa exige una membership activa del usuario y un Tenant activo. Un UUID válido pero ajeno, suspendido, removido o no activo devuelve 403; un identificador malformado no llega al controller y devuelve 404.
- Una selección válida escribe únicamente `current_tenant_id` desde la membership autorizada, elimina `current_store_id` y redirige al dashboard.
- `ResolveTenant` vuelve a validar Tenant y membership en cada request protegido. Sin selección, autoelige la única membership activa; con varias exige ADM-002; con ninguna dirige a ADM-003.
- Si la membership pierde acceso o el Tenant deja de estar activo, se limpian los contextos Tenant y Store. `TenantContext` también se limpia del contenedor al finalizar cada request.
- Cambiar Tenant nunca conserva una Store del contexto anterior; `StoreContext` resuelve luego una Store elegible dentro del nuevo Tenant.

## Seguridad y aislamiento

- La UI nunca envía `tenant_id` como dato de creación ni como autoridad de negocio. El UUID del endpoint de selección vuelve a validarse contra las memberships del usuario.
- Membership, rol y Tenant visible proceden exclusivamente de relaciones Laravel; Tenants ajenos, archivados o memberships suspendidas/removidas no se listan.
- No se cargan datos internos de otros Tenants, Stores, miembros, invitaciones ni permisos.
- No se añadieron invitaciones, Team, Access, settings, administración de roles ni operaciones de ciclo de vida.

## UI, i18n y responsive

- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `EmptyState` y `FieldError`.
- ADM-002 usa cards compactas con nombre persistido, slug y rol, además de una única acción de selección con prevención de doble envío.
- ADM-003 usa un formulario de un campo y un resumen exclusivamente de efectos respaldados por `CreateTenant`.
- es-PE sigue como locale predeterminado y `en` como alternativo. Nombres y slugs persistidos no se traducen.
- En mobile 390 px, cards, formulario y acciones se apilan sin tabla, navegación inferior ni scroll horizontal.

## Rendimiento

- Se conserva la consulta existente con eager loading de `tenant` y `role`; no se incorporan Stores, miembros adicionales, contadores, polling ni requests automáticos.
- La creación y la selección mantienen sus POST y redirecciones originales. No se añadieron dependencias.

## Validación

- Guard aislado `pgsql.testing/commerce_testing`: 1 test, 4 assertions.
- Tenancy: 19 tests, 116 assertions.
- Access + Stores + ciclo de memberships: 59 tests, 300 assertions.
- Suite Laravel: 636 tests, 4,429 assertions.
- i18n: 2,586 mensajes Vue, 45 sidebar labels y 43 mensajes Laravel por locale.
- `npm run typecheck`, `npm run build` y `git diff --check`: aprobados.
- Bundles: ADM-002 `Index--nTx484x.js`, 3.18 kB / 1.28 kB gzip; ADM-003 `Create-C4kcZEwU.js`, 3.94 kB / 1.47 kB gzip.

## Pendiente y omitido

- Pendiente: validación visual humana acumulada en desktop y mobile 390 px.
- Omitido deliberadamente: ADM-004–012, Access/Settings, Stores, Team/invitations, lifecycle de Tenant, backend, migraciones y generación Stitch.
