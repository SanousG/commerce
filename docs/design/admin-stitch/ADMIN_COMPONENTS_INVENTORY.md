# Inventario de componentes administrativos

## Existentes y reutilizables

| Componente | Ubicación | Uso actual | Decisión |
|---|---|---|---|
| `AdminLayout` | `resources/js/layouts/AdminLayout.vue` | Shell, navegación RBAC, tenant/store, inbox, logout | Reutilizar contrato; rediseñar después de aprobación. Extraer sidebar/header sin cambiar props. |
| `AuthLayout` | `resources/js/layouts/AuthLayout.vue` | Login/registro/reset/verificación/invitation | Mantener separado del admin. |
| `FieldError` | `resources/js/components/FieldError.vue` | Error accesible de campos | Reutilizar como primitive. |
| `StatusNotice` | `resources/js/components/StatusNotice.vue` | Flash/status en auth | Generalizar visualmente, no cambiar contrato aún. |
| `MediaUploader` | `resources/js/components/media/MediaUploader.vue` | Upload privado, preview, progreso, error | Reutilizar; encapsular dentro de panel/drawer futuro. |
| `MediaPicker` | `resources/js/components/media/MediaPicker.vue` | Búsqueda/paginación JSON y selección | Reutilizar; agregar cancelación/error visual en implementación posterior. |
| `MediaThumbnail` | `resources/js/components/media/MediaThumbnail.vue` | Thumbnail privado con `loading=lazy` | Reutilizar; añadir dimensiones/placeholder sin publicar masters. |
| `DashboardTrendChart` | `resources/js/components/DashboardTrendChart.vue` | SVG sin dependencia | Reutilizar dentro de `ChartContainer`. |
| `MetricDelta` | `resources/js/components/MetricDelta.vue` | Comparación KPI | Reutilizar dentro de KPI Card. |

No hay componentes globales de tabla, paginación, botones, badges, breadcrumbs, modal, drawer, skeleton, empty/error state, tabs, select o date-range. Esos patrones están duplicados en páginas con clases Tailwind.

## Nuevos componentes propuestos

Los nombres finales se validarán contra el código antes de implementar. Ninguno se crea en esta fase.

| Familia | Componentes propuestos | Responsabilidad |
|---|---|---|
| Shell | `AdminSidebar`, `AdminHeader`, `Breadcrumbs`, `TenantSwitcher`, `StoreSwitcher` | Navegación, contexto y responsive; links filtrados por RBAC, no autorización. |
| Page | `PageHeader`, `PageActions`, `ContentSection` | Título, descripción, acciones y jerarquía uniforme. |
| Actions | `PrimaryButton`, `SecondaryButton`, `DestructiveButton`, `IconButton` | Estados hover/focus/disabled/processing. |
| Data | `DataTable`, `ResponsiveDataList`, `Pagination`, `StatusBadge`, `KpiCard`, `ChartContainer` | Densidad, headers sticky, mobile cards y navegación Inertia. |
| Filters | `SearchInput`, `FilterBar`, `FilterDrawer`, `SelectField`, `DateRangePicker` | Apply/clear explícito; debounce/cancelación solo donde se apruebe. |
| Feedback | `LoadingSkeleton`, `EmptyState`, `ErrorState`, `PermissionState`, `SaveStatus` | Loading, vacío, error, 403, pending/success/409/session expired. |
| Overlay | `ConfirmationDialog`, `Drawer` | Sustituir `window.confirm`; foco y teclado accesibles. |
| Forms | `FormSection`, `FormField`, `FormActions`, `Tabs` | Agrupar workbenches extensos sin alterar FormRequests. |
| Media | `MediaGrid`, `ImagePreview`, `ProductGallery` | Construidos sobre Picker/Uploader/Thumbnail actuales. |
| Notifications | `NotificationItem` | Severidad, estado y acción con contenido no sensible. |

## Duplicaciones observadas

- Cada index implementa filtros, tablas, links de paginación y empty rows por separado.
- Payments, Refunds, Returns, Exchanges y Fulfillments comparten casi el mismo filtro `search/status/order_id`.
- Create/Edit de Products, Categories, Stores, Suppliers y Pricing repiten encabezados y acciones.
- `window.confirm` aparece en acciones destructivas; no hay focus trap ni copy uniforme.
- Paginación de Media usa `<a>`, provocando recarga completa; las demás suelen usar `Link`.
- Las páginas Order, Product, Return, Purchase Order y Store Settings necesitan tabs/anchors y guardado contextual; hoy son páginas verticales extensas.
