# FASE 26.34 — Notifications · ADM-068

## Alcance

- **ADM-068:** centro de notificaciones (`/admin/notifications`).
- **ADM-069** pertenece al módulo Audit y queda fuera de esta fase.

ADM-068 fue rediseñada e internacionalizada con Executive Precision B2B sin modificar backend, dominio, rutas, contratos HTTP, migraciones ni infraestructura de correo/colas. No se generó un diseño Stitch.

## Contrato funcional auditado

- El índice lista 25 notificaciones por página, ordenadas por creación descendente.
- Los filtros reales son `unread`, `active`, `resolved`, `severity` y `type`; no existe búsqueda textual, orden configurable ni exportación.
- Las acciones existentes son marcar una notificación como leída, marcar todas las visibles como leídas, descartar una notificación activa, abrir el recurso autorizado y abrir preferencias.
- No existe creación o edición manual, restauración de descartadas, eliminación, acciones masivas adicionales ni detalle propio de notificación.
- Los estados resuelto y descartado son históricos. Descartar no elimina la fila. Marcar como leída es idempotente y conserva el primer timestamp.
- Los títulos y mensajes son contenido genérico persistido por Laravel; Vue no deriva ni traduce datos operativos almacenados.

## Seguridad y aislamiento

- Sesión Laravel, CSRF, `TenantContext`, `StoreContext` y `PermissionChecker` permanecen como autoridad.
- Cada consulta se limita al Tenant activo, usuario autenticado y Store activa, incluyendo notificaciones tenant-wide.
- Laravel vuelve a filtrar por el permiso actual asociado al tipo. Revocar un permiso oculta contenido histórico y bloquea sus acciones.
- IDs de otro Tenant, Store o destinatario devuelven 404. Los recursos enlazados vuelven a autorizar su propio alcance.
- La UI nunca acepta `tenant_id`, `store_id`, destinatario, severidad o estado como autoridad de una mutación.
- Las preferencias continúan aisladas por `(tenant, user)`; las alertas fiscales obligatorias no pueden silenciarse.

## UI, responsive e internacionalización

- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `Pagination` y `EmptyState`.
- Los filtros reflejan exactamente el FormRequest. Limpiar filtros elimina parámetros falsos/vacíos y conserva navegación Inertia.
- Las acciones bloquean doble envío; descartar y marcar todas requieren confirmación localizada. El progreso se anuncia mediante `aria-live`.
- Desktop conserva una lista compacta con severidad, estado, tipo, fecha y acciones. Mobile 390 px apila contenido y acciones sin scroll horizontal ni navegación inferior.
- es-PE sigue siendo predeterminado y `en` alternativo. Se añadieron etiquetas para los 17 tipos enum, tres severidades, estados visuales, filtros, confirmaciones, vacíos y mensajes accesibles.
- Los títulos y mensajes persistidos se muestran sin alterar porque son datos suministrados por Laravel, no copy estático de Vue.

## Rendimiento e integridad

- Se conservan paginación server-side de 25, query original y conteo compartido; no se añadieron requests automáticos, polling, prefetch, queries ni dependencias.
- `mark all read` mantiene el alcance del backend completo para el usuario/contexto visible, no solo la página actual.
- Las notificaciones de eventos/condiciones, deduplicación, resolución, escalamiento, transacciones y dispatch `afterCommit` no se modificaron.
- No se contacta ningún proveedor externo en pruebas; correo usa fakes/driver array según el entorno de test.

## Funcionalidades no disponibles

- Búsqueda textual, ordenamiento configurable, exportación y acciones bulk diferentes de marcar todas como leídas.
- Creación/edición manual, restauración, eliminación y detalle independiente de notificación.
- SMS, push web, WhatsApp, preferencias por Store, ledger de intentos de entrega y política de retención.
- Alertas para eventos deliberadamente omitidos por el módulo (fallo de pago no fiable, invitación in-app, courier/storefront y otros indicados en su README).

## Validación

Los resultados de guard, pruebas Laravel, i18n, typecheck, build y `git diff --check` se registran en el informe final de FASE 26.34.
