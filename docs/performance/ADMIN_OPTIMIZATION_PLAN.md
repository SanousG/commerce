# Plan priorizado de optimización administrativa

## Completado en esta fase

| Prioridad | Cambio | Evidencia | Riesgo |
|---|---|---|---|
| P0 | Memoizar membership/role/permissions dentro de `PermissionChecker` scoped | -54 queries por navegación; tabla antes/después | Bajo; request-local, tenant+user keyed, tests RBAC/isolation pasan |
| P1 | Paginación Media con Inertia `Link` en lugar de `<a>` | Código: elimina document reload al cambiar página | Bajo; misma URL/resultado |

## Próximas mediciones, sin cambio funcional

1. Browser DevTools: primera visita vs Inertia posterior, TTFB, response transfer, scripting/render/paint y thumbnail waterfall.
2. Repetir 20–30 veces por pantalla para mediana/p95; documentar hardware, network throttling y dataset.
3. Capturar `Server-Timing` en entorno local/staging autorizado para middleware/controller/DB/serialization.
4. Obtener `pg_stat_statements` y EXPLAIN (sin ANALYZE en compartido salvo autorización) para searches y aggregates con cardinalidad real.
5. Medir Supabase direct/pooler DNS/TLS/connection reuse sin carga agresiva.

## P1 propuesto — seguro tras medición

- Reservar aspect ratio/placeholder y error fallback para thumbnails.
- Añadir abort/cancelación a búsquedas JSON de MediaPicker si se vuelven reactivas.
- Unificar `Pagination` Inertia para impedir futuros anchors full-page.
- Separar Product Edit visualmente en tabs; cargar props pesadas de media/pricing de forma deferred solo con tests de permisos y staleness.
- Revisar Dashboard por sección con query timing, no por conteo únicamente.

## P2 — requiere aprobación/refactor

- URL versionada o ETag para derivatives privados y estrategia de cache revalidable; conservar auth y aislamiento.
- Búsqueda PostgreSQL con trigram/FTS solo si plans/cardinalidad demuestran necesidad; migración de índice separada y no ejecutada en Supabase durante implementación.
- Prefetch Inertia selectivo para Products/Categories/Media list/create bajo interacción (`hover`/visible), nunca masivo ni para financial/fiscal.
- Deferred props para Dashboard y workbenches; definir qué props pueden llegar después sin parpadeos ni permisos obsoletos.
- Revisar pooler/connection mode con operación antes de cambiar configuración.

## Caché

No se propone Redis actualmente. El problema dominante se resolvió con memoización request-scoped. Si una medición futura justifica cache persistente:

- Key: versión + tenant + store nullable + user + permission fingerprint + filtros + página.
- TTL corto y invalidación por eventos del dominio.
- Nunca cache global cross-tenant; no cachear páginas financieras/fiscales completas.
- Mantener autorización en cada request aun ante cache hit.

## Objetivo de aceptación

- Navegación posterior claramente inferior a baseline en backend y sin aumento de queries con volumen.
- Ninguna regresión de RBAC, tenant/store, filtros, pagination o props Inertia.
- Imágenes progresivas sin hacer masters públicos.
- p95 solo después de muestra suficiente en entorno representativo.
- No declarar “optimizado” hasta completar red/browser/storage.
