# FASE 26.35 — Audit · ADM-069

## Alcance

- **ADM-069:** ledger de actividad (`GET /admin/activity`, `admin.activity.index`).
- Requiere sesión Laravel, Tenant y Store activos, y permiso dinámico `audit.view`.
- No se modificaron backend, dominio, contratos, rutas, instrumentación ni `AuditRecorder`; no se generó un diseño Stitch.

## Contrato funcional auditado

- El índice consulta primero por el Tenant resuelto y carga únicamente actor (`id`, `name`, `email`) y Store (`id`, `name`).
- Los registros se ordenan por `occurred_at DESC`, luego `id DESC`, y se paginan de 50 en 50 conservando el query string.
- Los filtros representables por las props Inertia son acción enum, tipo de recurso enum, actor del Tenant, Store del Tenant o Store actual, fecha desde y fecha hasta inclusivas.
- El FormRequest también acepta `subject_id`, pero el controller no lo devuelve dentro de `filters`; por ello no se añadió un control cuyo estado no puede representar el contrato Inertia actual.
- No existe búsqueda textual, orden configurable, exportación, estadísticas, mutación, detalle independiente ni URL autorizada hacia el recurso relacionado.
- Cada fila expone únicamente `id`, actor normalizado, tipo de actor, acción, tipo/ID/label de recurso, Store opcional, metadata sanitizada y fecha de ocurrencia.
- Aunque `AuditSummary` está inyectado actualmente en el controller y el README histórico describe contexto adicional, las props no incluyen summary, IP, user-agent, correlation ID ni idempotency key; la UI no los inventa ni expone.

## Integridad append-only

- El ledger conserva actor y contexto originales; usuarios o Stores eliminados pueden dejar referencias nulas sin eliminar el evento.
- `AuditEntry` no usa timestamps Eloquent editables y PostgreSQL rechaza `UPDATE` y `DELETE` mediante trigger.
- `AuditRecorder` continúa escribiendo dentro de la transacción propietaria, valida actor y Store contra el Tenant y admite idempotencia por Tenant.
- No se añadieron editar, eliminar, limpiar, reatribuir actores, alterar fechas ni reescribir eventos históricos.

## Seguridad y aislamiento

- La ruta conserva middleware `tenant`, `store` y `can:audit.view`; el FormRequest vuelve a verificar el permiso mediante `PermissionChecker`.
- La consulta siempre comienza con `tenant_id`; actores y Stores seleccionables se validan contra ese Tenant. Un selector cross-tenant produce error de validación y no permite inferir registros.
- `StoreContext` se usa solamente cuando el filtro es `current`; `all` conserva el alcance tenant-wide autorizado.
- La metadata fue limitada, validada como JSON y sanitizada recursivamente por Laravel antes de persistirse. Claves sensibles se sustituyen por `[REDACTED]`; Vue la representa como texto, nunca como HTML.
- La interfaz no expone IP, user-agent, correlation ID, idempotency key, modelos relacionados completos ni payloads diferentes de la metadata ya autorizada por el controller.

## UI, responsive e internacionalización

- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `Pagination` y `EmptyState`. `StatusBadge` no se usa porque las props no contienen un estado de evento.
- Desktop usa una tabla compacta y desplegado inline de metadata; mobile 390 px usa cards sin tabla ni navegación inferior.
- Los IDs y metadata largos permiten quiebre de línea para evitar overflow horizontal. Los detalles usan `aria-expanded` y `aria-controls`.
- es-PE continúa como locale predeterminado y `en` como alternativo. Se tradujeron todos los valores actuales de `AuditAction` y `AuditSubjectType`, además de filtros, etiquetas, actores especiales, vacíos y accesibilidad.
- `subject_label`, nombres, correos, IDs y claves/valores de metadata se conservan como datos históricos; no se traducen automáticamente.

## Rendimiento

- Se conservan eager loading de actor/Store y paginación server-side de 50; no se añadieron consultas, N+1, polling, prefetch ni dependencias.
- Los detalles usan exclusivamente la metadata ya incluida en la página y no realizan requests adicionales.

## Validación

Los resultados del guard, pruebas Audit/RBAC/Tenancy, suite Laravel, i18n, typecheck, build y `git diff --check` se registran en el informe final de FASE 26.35.
