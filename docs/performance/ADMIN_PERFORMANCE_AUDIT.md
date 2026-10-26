# Auditoría de rendimiento del administrador

## Dictamen

El cuello de botella dominante medido no era Vue ni el bundle: era una repetición de consultas de RBAC provocada por shared props de Inertia y el contador de notificaciones. La corrección pequeña redujo 54 consultas por navegación y entre 71.8% y 87.8% la mediana backend medida. Esto no demuestra todavía rendimiento optimizado en producción ni cubre red/browser/imágenes.

## Flujo auditado

1. **Inertia navigation:** los `Link` conservan SPA navigation salvo la paginación de Media, que usaba `<a>` y fue corregida.
2. **Middleware auth/session:** Laravel session auth; no JWT/Supabase Auth.
3. **ResolveTenant:** una consulta scoped de membership + tenant; limpia context en `finally`.
4. **ResolveStore:** autorización y resolución scoped; ahora reutiliza membership request-scoped.
5. **Controller:** consultas paginadas/eager loads según pantalla.
6. **Shared props:** user, Tenant, membership+permissions, Store, unread count y flash.
7. **Serialization:** props Inertia explícitas; no se serializan modelos completos en las pantallas prioritarias.
8. **Vue:** page chunks lazy por `import.meta.glob`; render no medido en browser.
9. **Media:** 24 thumbnails privados lazy; cada archivo se autoriza y se resuelve por Tenant.

## Laravel / Inertia

### Hallazgo corregido — P1

- `HandleInertiaRequests` comparte `currentTenantMembership` y `unreadNotificationsCount` en toda navegación.
- Las closures evitan trabajo cuando una partial reload no solicita la prop, pero en navegación normal ambas se evalúan.
- `UnreadNotificationCount` filtra tipos por permiso correctamente, pero repetía la misma carga de membership por tipo.
- Corrección: memoización in-memory en `PermissionChecker`, cuya lifetime es `scoped`; clave `tenant_id:user_id`, incluyendo resultado null, e invalidación al cambiar la instancia HTTP de `Request`.
- Seguridad preservada: no cache global/Redis, no se acepta tenant frontend, `can(TenantMember)` sigue revalidando con DB y una nueva request no reutiliza memberships memorizadas aunque el contenedor sobreviva entre requests.

### Pendiente

- Evaluar polling/refresh del unread count solo con métricas; hoy añade una consulta COUNT legítima por navegación.
- No convertir permisos/contexto a props opcionales o stale partial reloads sin una estrategia de invalidación.
- Definir skeleton global después de diseño; no oculta latencia backend.

## PostgreSQL / Supabase

- Infraestructura vigente desde 2026-10-25: Development apunta al PostgreSQL
  local Docker `pgsql/commerce`; tests y probes usan el servicio aislado
  `pgsql.testing/commerce_testing`. Supabase no se utiliza actualmente.
- La auditoría histórica no ejecutó EXPLAIN ni carga sobre Supabase compartido.
- No hay SQL individual demostrado como lento en el probe; el problema fue volumen repetido.
- Products usa paginación 15, eager load de default variant/categories y `withCount`, sin N+1 observado.
- Categories usa paginación 20, eager parent y `withCount`, sin N+1 observado.
- Orders/Customers usan paginación 20 y proyecciones acotadas.
- Búsquedas `ILIKE '%texto%'` no aprovechan B-tree normal en grandes volúmenes; no se propone índice sin cardinalidad, `pg_stat_statements` y plan real.
- Product Create/Edit carga todas las categorías activas; Product Edit carga todas sus options, variants y media. Es un riesgo de escala, no un defecto medido con el fixture pequeño.
- Dashboard conserva 40 consultas después de la corrección porque agrega muchos módulos autorizados. Son bounded aggregate queries documentadas, pero requieren medición representativa antes de cache/refactor.

## Products y Categories

- **Products Index:** resumen mínimo, 15/página; no carga imágenes ni precios/variants completos.
- **Products Edit:** eager loading correcto evita N+1, pero el payload crece con todas las variantes/options/media; candidato a tabs con deferred/partial props tras aprobación de contrato.
- **Categories Index:** 20/página; no búsqueda; parent y product count en SQL.
- **Category Create/Edit:** helper carga todas las categorías activas para evitar ciclos. Puede crecer; mantener corrección de grafo y medir antes de cambiar.

## Media

- Library: 24/página, type IMAGE, metadata explícita, usage counts en query, thumbnails de 300 px WebP.
- `MediaThumbnail` usa `loading="lazy"`.
- Masters y derivatives siguen privados; `Cache-Control: private, max-age=3600`, `nosniff`.
- No hay procesamiento durante GET; solo `readStream` de derivative/master existente.
- Coste potencial: hasta 24 requests de thumbnail, cada uno con auth/tenant/Gate y dos lookups de media/derivative, además del storage. No se midió waterfall real.
- `MediaPicker` hace un fetch JSON al montar; en Product Edit/Store Settings está presente solo cuando la UI lo renderiza según su condición actual. No hay watchers que dupliquen requests.
- Defecto corregido: la paginación de Media usaba anchor HTML y hacía full reload. Ahora usa Inertia `Link`.
- Pendiente: placeholder/aspect ratio, error por thumbnail, cache validators/versioned derivative URL y medición storage; nunca hacer masters públicos.

## Vue / Vite

- Page-level code splitting ya existe: `resolvePageComponent` + `import.meta.glob` sin eager.
- Build: main JS 189.93 KB raw / 64.11 KB gzip; CSS 66.02 KB / 13.66 KB gzip.
- Páginas mayores: Dashboard 30.20 KB, Order Show 28.24 KB raw; continúan chunks separados.
- `MediaUploader` chunk 52.53 KB / 19.87 KB gzip incluye su dependencia HTTP y se carga donde se importa; revisar solo si waterfall browser muestra impacto.
- No se encontraron fetches duplicados generalizados. `MediaPicker` es el único GET Axios onMounted relevante; watchers hallados actualizan estado local.
- Listados están paginados; no se necesita virtualización actualmente.
- No hay prefetch configurado. Añadirlo indiscriminadamente sería incorrecto para datos sensibles y Store/Tenant context.

## UX actual

- Headings sobredimensionados reducen densidad; workbenches largos carecen de tabs/anchors.
- Sidebar no tiene selector Tenant/Store ni modo colapsado real; en pantallas pequeñas es una barra horizontal extensa, no drawer.
- Feedback, empty/error/loading y confirmation están duplicados/inconsistentes.
- Muchas tablas dependen de overflow horizontal; Returns/Refunds/Exchanges ya tienen cards mobile y sirven como patrón parcial.
- Media full reload fue una causa de lentitud percibida específica y ya se eliminó.

## Consultas SQL lentas

Ninguna consulta individual puede calificarse como lenta con evidencia disponible. No hubo query con plan analizado ni acceso seguro a estadísticas remotas. El hallazgo SQL probado fue **54 consultas redundantes** de membership/role/permissions por navegación.
