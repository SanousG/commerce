# ADM-021 — Create Listing: implementación FASE 26.19

## Alcance

Rediseño e internacionalización de `/admin/listings/create` sobre el contrato Laravel existente. No se modificaron rutas, controlador, Form Request, servicios, modelos, permisos ni consultas. No existe una referencia Stitch verificable para ADM-021: el intento histórico devolvió HTTP 503 sin ID, por lo que la implementación usa el sistema aprobado Executive Precision B2B y el backend como fuente de verdad.

## Contrato verificado

| Punto | Contrato preservado |
|---|---|
| Scope | Tenant autorizado y Store activa mediante `TenantContext` y `StoreContext`; no se acepta `tenant_id` ni `store_id` del formulario. |
| Permiso | Página y alta protegidas por `listings.create`. |
| Productos | Solo Products `ACTIVE` del Tenant que no tienen Listing en la Store actual. Búsqueda por nombre o SKU y máximo 50 resultados. |
| Selección | Un único `product_id` UUID. |
| Slug | Nullable y máximo 180 al crear. Si queda vacío usa el slug del Product; `CreateListing` lo normaliza y hace único por Store. |
| Price list | Override opcional limitado a listas `ACTIVE` del Tenant. Vacío conserva la resolución Store → Tenant. |
| Estado inicial | `DRAFT`. Se crean snapshots de todas las variants y se habilitan únicamente las `ACTIVE`. |
| Resultado | Redirección al Edit de la nueva publicación con flash existente. |

## Implementación

- `AdminLayout`, `Breadcrumbs`, `PageHeader`, `EmptyState` y `FieldError` reutilizados.
- Contexto visible de la Store activa y aviso explícito del estado DRAFT.
- Búsqueda Inertia real con limpieza, estado de procesamiento y los props existentes.
- Selector radio de producto en cards compactas; SKU predeterminado nullable y estado vacío diferenciado.
- Formulario de slug opcional y override de price list, sin configurar variantes, precios ni promociones durante el alta.
- Errores conocidos del servidor traducidos; datos comerciales como nombres, SKU y price lists se muestran sin traducir.
- Footer responsive con Cancelar y Crear; el submit se bloquea sin producto o durante procesamiento.

## Responsive y accesibilidad

- Desktop: formulario dividido en Product y Store settings, selector en dos columnas y densidad administrativa compacta.
- Mobile 390 px: una columna, cards sin scroll horizontal, controles y acciones a ancho completo.
- Labels asociados, fieldsets, radio nativo, `aria-invalid`, ayudas y regiones de error.
- No se agregó navegación inferior ni se modificó el drawer compartido.

## Seguridad y rendimiento

- Laravel continúa autorizando y revalidando Product, Store y PriceList dentro de la transacción.
- No se añadieron endpoints, requests ni consultas. La búsqueda conserva el límite de 50 y eager loading de `defaultVariant`.
- Chunk de producción: `Create-fc-Qs5up.js`, **11.20 kB** (**3.61 kB gzip**), además de chunks administrativos compartidos.

## Validación

- Guard aislado: 1 test, 4 assertions, PASS.
- Suite Listings: 25 tests, 185 assertions, PASS.
- Cobertura relevante: roles OWNER/ADMIN, MEMBER denegado, Product externo/inactivo, PriceList externa, Store/Tenant autoritativos, slug por Store, estado DRAFT e inicialización de variants.
- `npm run test:i18n`, `npm run typecheck`, `npm run build` y `git diff --check`: PASS.

## Pendiente

- Revisión visual manual en navegador desktop y viewport 390 px.
- No se generó ni reintentó una pantalla Stitch; el estado histórico de ADM-021 permanece sin recurso verificable.
