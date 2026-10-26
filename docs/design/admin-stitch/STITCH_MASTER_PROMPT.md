# Google Stitch — Master Prompt

## Estado de integración

Google Stitch está conectado mediante MCP. Proyecto obligatorio: `projects/13874730587997489426` (**Commerce SaaS Admin Dashboard**). Design System obligatorio: `assets/a08e56da2173415785f1ffb340ddd446` (**Executive Precision B2B**, versión `1`). No crear otro proyecto, no mezclar el asset histórico `Commerce Operational System` y no cambiar el Design System sin autorización explícita.

## Prompt maestro

> Diseña un sistema visual original para el administrador de **Commerce**, un SaaS e-commerce B2B multi-tenant. El producto es un monolito modular Laravel 13 con Vue 3, Inertia y Tailwind. No diseñes storefront. No copies literalmente Shopify, Linear, Stripe o Vercel; usa como referencia conceptual su claridad, densidad y jerarquía.
>
> **Modelo de contexto:** Tenant es la empresa; Store es un canal operativo. Membership, catálogo base, clientes, inventario, procurement, tax y media son tenant-wide. Currency, timezone, locale, listings, payments, series fiscales, órdenes y operaciones comerciales son Store-owned. Cambiar Store nunca cambia membership. Muestra siempre el contexto activo sin sugerir que la UI sustituye la autorización backend.
>
> **Usuarios y permisos:** roles de sistema OWNER, ADMIN y MEMBER con permisos granulares. Oculta acciones no permitidas, pero incluye estados readonly y “sin permiso”. No diseñes editor de custom roles: no existe. Cada workflow debe mostrar solo capacidades reales.
>
> **Navegación desktop (1440):** sidebar colapsable con logo Commerce, Overview, Catalog, Merchandising, Sales, Operations, Customers, Inventory, Procurement, Compliance, Workspace y Settings. Estado activo claro, grupos expandibles e iconografía lineal consistente. Header sticky con breadcrumbs, título contextual, selector de Tenant, selector de Store, inbox y perfil. Área de contenido máxima legible pero amplia para tablas.
>
> **Tablet (768):** sidebar como drawer, header compacto, filtros en grid de dos columnas, acciones en overflow menu cuando sean secundarias.
>
> **Mobile (390):** drawer accesible; contexto Tenant/Store visible; filtros en drawer/sheet; acciones primarias visibles; listados como cards/rows con 3–5 datos esenciales y acceso al detalle. Nunca comprimir tablas de 8–20 columnas.
>
> **Estilo:** usar exclusivamente los tokens reales de Executive Precision B2B documentados en `ADMIN_DESIGN_TOKENS.md`. Identidad slate/azul: primary `#0F172A`, secondary `#2563EB`, tertiary `#0284C7`, neutral `#64748B`. Canvas operativo `#F8F9FA`, cards `#FFFFFF`, texto secundario `#334155`, bordes `#E2E8F0/#CBD5E1`. No usar colores distintos por módulo. Los verdes se reservan exclusivamente a Success/Active mediante los tokens semánticos del asset. No usar gradientes decorativos ni glassmorphism. Radio 8 px, sombras mínimas, border-first.
>
> **Tipografía:** Inter para body, headings y controles; JetBrains Mono para etiquetas técnicas, IDs, SKU y montos. Base 14/20 px; display 30/38; headings 24/32, 18/24 y 15/20; labels 12/16 y 11/14. Contraste WCAG AA, foco azul visible de 2 px y targets mínimos 40 px.
>
> **Espaciado:** escala 4, 8, 12, 16, 24, 32, 48. Controles compactos 36–40 px. Tablas 44–48 px por fila. No desperdiciar vertical space en hero headings gigantes.
>
> **Componentes:** AdminSidebar, AdminHeader, Breadcrumbs, PageHeader, Primary/Secondary/Destructive Button, DataTable, ResponsiveDataList, SearchInput, FilterBar/Drawer, StatusBadge, Pagination, EmptyState, ErrorState, LoadingSkeleton, PermissionState, ConfirmationDialog, Drawer, FormSection/FormField/FormActions, Tabs, Select, DateRangePicker, KpiCard, ChartContainer, NotificationItem, MediaPicker/Grid/Uploader/Thumbnail, ProductGallery, TenantSwitcher, StoreSwitcher.
>
> **Tablas:** headers sticky cuando convenga, primera columna prioritaria, números alineados a la derecha, badges compactos, acciones por fila visibles o en menú, paginación Inertia. En mobile transformar a cards; conservar status, identificador, relación primaria y acción.
>
> **Formularios:** dividir por propósito; labels persistentes; ayuda breve; errores junto al campo; barra de acciones contextual para páginas largas. Respetar required, maxlength, enums, amounts exactos, idempotency y controles disabled/readonly. No inventar autosave.
>
> **Estados a especificar:** datos, vacío, loading, error, 403, validación, operación exitosa, guardado pendiente, HTTP 409 por concurrencia, sesión expirada y confirmación destructiva. Skeletons deben imitar estructura, no bloquear todo el shell. Sesión expirada ofrece volver a login sin perder intencionalmente secretos.
>
> **Rendimiento percibido:** shell estable entre navegaciones, feedback inmediato, imágenes lazy con ratio reservado y placeholder, sin animaciones pesadas. Diseña prefetch solo como comportamiento opcional y selectivo para listas no sensibles; no prefetch masivo de pagos, fiscal o catálogo.
>
> **Datos demo:** exclusivamente sintéticos (por ejemplo “Andes Retail SAC”, Store “Lima Online”, SKU `TSH-BLK-M`, order `ORD-2026-1042`). No mostrar PII real, credenciales, tokens, claves SOL, certificados, XML/CDR ni datos bancarios.
>
> **Pantallas de referencia a producir primero:** Design System, Admin Layout, Dashboard, Products Index, Product Create/Edit, Categories Index, Media Library, Store Settings. Luego extender las plantillas a las 69 pantallas inventariadas en la matriz de cobertura.

## Tokens oficiales verificados

| Token | Valor |
|---|---|
| Canvas | `#F8F9FA` |
| Surface | `#FFFFFF` |
| Text | `#0F172A` |
| Secondary text | `#334155` |
| Muted / Neutral | `#64748B` |
| Border | `#E2E8F0`; strong `#CBD5E1` |
| Primary | `#0F172A` |
| Secondary | `#2563EB` |
| Tertiary | `#0284C7` |
| Success | `#16A34A` sobre `#F0FDF4` |
| Warning | `#D97706` sobre `#FFFBEB` |
| Danger | `#DC2626` sobre `#FEF2F2` |
| Radius | 8 px; 999 px solo badges/avatar |
| Content padding | 32 desktop, 24 tablet, 16 mobile |
| Sidebar | 248 expanded, 72 collapsed |
| Header | 64 desktop, 56 mobile |

## Criterios de aceptación del diseño

1. Cobertura explícita de las 69 pantallas, agrupadas sin perder particularidades.
2. Tres breakpoints de referencia: 1440, 768 y 390.
3. Acciones y estados coherentes con RBAC/capabilities reales.
4. Tenant-wide y Store-scoped claramente distinguibles.
5. Ninguna funcionalidad nueva presentada como existente.
6. Workbenches largos usan tabs/anchors sin ocultar validaciones ni estado.
7. Tablas tienen estrategia mobile.
8. Loading/error/empty/403/409/session-expired están definidos.
9. Datos 100% sintéticos.
10. Resultado listo para aprobación visual; no implica implementación.

## Operación MCP

1. Trabajar solo en `projects/13874730587997489426`.
2. Consultar `list_design_systems` y usar únicamente `a08e56da2173415785f1ffb340ddd446`.
3. Verificar HTML y captura; mencionar el nombre en el prompt no demuestra que los tokens se aplicaron.
4. Para `apply_design_system`, probar primero una sola instancia no canónica y comparar antes/después según `ADMIN_DESIGN_TOKENS.md`.
5. No aplicar cambios masivos ni tocar ADM-001 aprobado sin autorización.
6. Registrar IDs, previews, sourceScreen, visibilidad y diferencias en `STITCH_DESIGN_COVERAGE.md`.
