# Admin performance baseline

Fecha: 2026-10-18.

## Entorno y metodología

- Laravel ejecutado dentro de `laravel.test`; PostgreSQL aislado `pgsql.testing/commerce_testing` confirmado por el guard test.
- Dataset sintético: 20 Products, 20 Categories, 24 Media assets, 1 Customer, 1 Store; listas operativas restantes vacías. No se usó Supabase compartido, datos reales, SUNAT ni correo.
- 7 requests secuenciales por pantalla: primera ejecución reportada por separado; mediana de las 6 navegaciones posteriores.
- Instrumentación: tiempo de request de Feature HTTP, query log de Laravel, suma de duración DB y bytes de response Inertia.
- Muestra insuficiente para un p95 fiable; **no se reporta p95**.
- El entorno de desarrollo configurado usa PostgreSQL mediante **Supabase pooler**, puerto 5432, `DB_URL` no configurado y sslmode efectivo por defecto `prefer`. No se hizo benchmark remoto.

## Antes de la corrección

| Pantalla | Primera visita ms | Mediana posterior ms | Queries | DB mediana ms | Response | Imágenes | Frontend |
|---|---:|---:|---:|---:|---:|---:|---|
| Dashboard | 1457.60 | 180.24 | 97 | 71.49 | 8.0 KB | 0 | No medido |
| Products Index | 194.29 | 143.77 | 62 | 43.08 | 5.9 KB | 0 | No medido |
| Products Edit | 377.15 | 190.77 | 84 | 63.49 | 5.0 KB | 0 en fixture | No medido |
| Categories Index | 153.25 | 142.66 | 60 | 44.83 | 6.4 KB | 0 | No medido |
| Media Index | 171.67 | 144.40 | 60 | 43.56 | 15.8 KB | 24 thumbnails después del HTML | No medido |
| Orders Index | 173.63 | 157.02 | 63 | 49.20 | 3.4 KB | 0 | No medido |
| Customers Index | 166.12 | 146.89 | 60 | 45.90 | 3.3 KB | 0 | No medido |
| Store Settings | 224.45 | 163.66 | 64 posterior | 52.43 | 3.2 KB | 0 en fixture | No medido |

La primera ejecución incluye bootstrap/carga fría del proceso de test y no equivale a browser cold load. La navegación posterior es la comparación útil dentro de este entorno.

## Después de la corrección request-scoped

| Pantalla | Primera visita ms | Mediana posterior ms | Queries | DB mediana ms | Response | Cambio de tiempo posterior |
|---|---:|---:|---:|---:|---:|---:|
| Dashboard | 1401.60 | 50.84 | 40 (43 primera) | 27.33 | 8.0 KB | -71.8% |
| Products Index | 92.41 | 20.86 | 8 | 4.50 | 5.9 KB | -85.5% |
| Products Edit | 94.03 | 28.31 | 15 | 7.78 | 5.0 KB | -85.2% |
| Categories Index | 26.48 | 19.63 | 6 | 3.70 | 6.4 KB | -86.2% |
| Media Index | 42.08 | 25.66 | 6 | 4.36 | 15.8 KB | -82.2% |
| Orders Index | 48.18 | 24.72 | 6 | 4.23 | 3.4 KB | -84.3% |
| Customers Index | 48.86 | 19.28 | 6 | 3.90 | 3.3 KB | -86.9% |
| Store Settings | 64.04 | 19.90 | 7 posterior | 3.81 | 3.3 KB | -87.8% |

## Evidencia de causa

`UnreadNotificationCount` evalúa los tipos visibles según permisos. Cada llamada a `PermissionChecker::userCan()` invocaba `currentMembership()`, que hacía tres consultas (membership, role, permissions). El loop añadía 54 consultas iguales en toda respuesta Inertia completa. `PermissionChecker` ya está registrado como servicio `scoped`; además, la memoización por `tenant:user` se reinicia cuando cambia la instancia HTTP de `Request`. Así elimina repetición dentro de la respuesta sin Redis, persistencia ni mezcla cross-request/cross-tenant, incluso si un cliente de pruebas reutiliza el contenedor entre requests.

## Métricas no obtenidas

- RTT/TLS/DNS hacia Supabase y latencia real del pooler.
- Browser Navigation Timing, Inertia request waterfall, scripting, Vue render/paint, LCP/INP.
- Tiempo/peso real de 24 thumbnails y cache-hit ratio del storage.
- EXPLAIN ANALYZE en Supabase y p95 representativo con dataset real.

No se inventan valores para estas dimensiones. La próxima medición debe usar DevTools en un entorno autorizado, con dataset y red documentados.
