# ADM-016 — Categories Index implementation

Fecha: 2026-10-25. Fase: 26.12.

## Referencias

- Proyecto Stitch: `projects/13874730587997489426`.
- Desktop: `045b5a63c318431c9daee046a4924631`.
- Mobile: `9afecad50e4a4138ab4d1022ad3d4dab`.
- Design System: Executive Precision B2B.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

Las referencias se usaron solo para identidad visual. Su representación como
árbol no se implementó porque el índice Laravel entrega una página plana de
categorías y el alcance de esta fase prohíbe cargar o construir el árbol.

## Contrato funcional verificado

- Categories pertenece al Tenant, no a una Store. El índice usa
  `TenantContext` y `CatalogAccess::canViewCategories`.
- La consulta parte de la relación `Tenant::categories()`, por lo que queda
  limitada al Tenant activo autorizado.
- Orden real: `position`, seguido de `name`.
- Paginación server-side: 20 categorías por página.
- La única relación cargada es `parent:id,name` y el conteo se obtiene con
  `withCount('products')`; no existe N+1 por fila.
- Props por fila: `id`, `name`, `slug`, `status`, `position`, nombre de
  `parent` nullable y `products_count`.
- Estados reales: `ACTIVE`, `INACTIVE` y `ARCHIVED`.
- No existen búsqueda, filtros, bulk actions, reorder inline, drag-and-drop ni
  endpoint de eliminación física en este índice.

## Acciones y permisos

| Acción | Permiso | Ruta real |
|---|---|---|
| Ver índice | `categories.view` | `GET /admin/categories` |
| Crear | `categories.create` | `GET /admin/categories/create` |
| Editar | `categories.update` | `GET /admin/categories/{category}/edit` |
| Archivar | `categories.archive` | `PATCH /admin/categories/{category}/archive` |

`Create category`, `Edit` y `Archive` solo aparecen con su permiso. Archive no
se muestra para categorías ya archivadas, solicita confirmación y comunica su
estado processing por fila. El backend continúa aplicando autorización y
resolución Tenant-scoped; ocultar acciones no sustituye esos controles.

No se añadió Delete: el comportamiento real es archivado lógico mediante el
estado `ARCHIVED`.

## Diseño desktop

- Breadcrumb `Catalog / Categories`.
- `PageHeader` con título, contexto `All company`, descripción Tenant-wide y
  CTA autorizado.
- Tabla compacta border-first con columnas Category, Parent category,
  Position, Status, Products y Actions.
- El slug real se muestra como metadata secundaria en JetBrains Mono.
- Una categoría sin padre se identifica como `Root category`; una categoría
  hija muestra únicamente el nombre del padre, sin indentación ni controles de
  árbol.
- Position y Products usan cifras tabulares; Status reutiliza `StatusBadge`.
- El flash de create/update/archive reutiliza `StatusNotice`.

## Diseño mobile 390

- La tabla se reemplaza por cards desde anchos menores a `md`.
- Cada card mantiene nombre, slug, status, parent, position, product count y
  acciones autorizadas.
- Los datos se distribuyen en dos columnas internas sin anchos mínimos ni
  overflow horizontal.
- Se conserva el drawer de `AdminLayout`; no se agregó navegación inferior.

## Estados vacíos y paginación

- Sin categorías se usa `EmptyState` con explicación y `Create category` solo
  para usuarios autorizados.
- Se reutiliza `Pagination` con los links, `from`, `to` y `total` reales del
  paginator Laravel.
- El componente no renderiza controles cuando no existe otra página navegable.
- La navegación preserva el scroll mediante el comportamiento compartido de
  `Pagination` y no genera requests adicionales.

## Componentes reutilizados

- `AdminLayout`.
- `Breadcrumbs`.
- `PageHeader`.
- `StatusBadge`.
- `Pagination`.
- `EmptyState`.
- `StatusNotice`.

No se creó ningún componente nuevo: la composición coincide con el patrón ya
establecido por ADM-013 y no existe una segunda necesidad que justifique otra
abstracción.

## Diferencias frente a Stitch

- Se eliminó toda indentación, iconografía de ramas y apariencia de árbol.
- No existen expand/collapse, drag-and-drop ni ordenamiento inline.
- No se muestra un total hard-coded en el encabezado; el total solo aparece en
  Pagination cuando lo entrega Laravel.
- No se incorporaron búsqueda, filtros o bulk actions.
- Los ejemplos, slugs y conteos de Stitch no se trasladaron a la aplicación.
- Se conservaron las columnas funcionales solicitadas, aunque algunas no sean
  visibles en el recorte de la captura desktop.

## Rendimiento

- Consulta y payload backend: sin cambios.
- Paginación: 20 registros server-side, sin carga del árbol completo.
- Requests adicionales, watchers y estado duplicado: ninguno.
- Bundle anterior de Categories Index: 2.40 kB, 1.16 kB gzip.
- Bundle final de Categories Index: 6.27 kB, 2.29 kB gzip.
- El aumento corresponde a los componentes compartidos de encabezado, estados,
  badges, paginación y a las dos presentaciones responsive. No se añadieron
  dependencias.

## Validación

- Guard de conexión: 1 test, 4 assertions; confirma
  `pgsql.testing/commerce_testing`.
- `CategoryTest`: 9 tests, 66 assertions.
- Suite Catalog: 41 tests, 250 assertions.
- `npm run typecheck`: correcto.
- `npm run build`: correcto; solo permanece el aviso no bloqueante del paquete
  opcional `fontaine`, no instalado.
- `git diff --check`: correcto.

La cobertura ejecutada incluye categoría root, categoría con padre, ACTIVE e
INACTIVE, position, product count, página de 20 registros, RBAC, navegación a
Create/Edit, parent cross-tenant, pivot cross-tenant y edit cross-tenant. El
empty state y el responsive visual no tienen framework de pruebas Vue en el
repositorio y requieren revisión manual.

## Archivos de FASE 26.12

- `resources/js/pages/admin/categories/Index.vue`.
- `tests/Feature/Catalog/CategoryTest.php`.
- `docs/design/admin-stitch/ADM-016_IMPLEMENTATION.md`.
- `docs/design/admin-stitch/STITCH_DESIGN_COVERAGE.md`.

## Pendientes

No existe un navegador autenticado disponible en el harness. Queda pendiente
la revisión visual manual a 1280, 1440 y 390 px, incluyendo:

- nombres, slugs y padres extensos;
- ACTIVE, INACTIVE y ARCHIVED;
- estado vacío con y sin permiso create;
- usuarios sin update/archive;
- confirmación y processing de Archive;
- página 1, página intermedia y última página;
- foco y navegación por teclado.

ADM-017 Create Category y ADM-018 Edit Category quedaron deliberadamente fuera
de alcance y no fueron rediseñadas.
