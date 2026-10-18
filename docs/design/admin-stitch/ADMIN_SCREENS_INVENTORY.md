# Inventario real de pantallas administrativas

Fecha de corte: 2026-10-18. Fuente: `routes/web.php`, `php artisan route:list --json`, los 69 `Inertia::render()` administrativos, controladores, Form Requests y `resources/js/pages/admin/**`.

## Resultado del descubrimiento

- 225 rutas registradas; 207 usan prefijo `admin/`.
- 74 rutas administrativas GET, pero solo **69 pantallas Inertia reales**.
- `admin.media.show`, `admin.media.thumbnail` y `admin.media.file` son JSON/archivos privados, no pantallas.
- `admin.fiscal-documents.artifacts.download` descarga un artefacto privado, no es pantalla.
- `admin.purchase-receipts.show` redirige a la pantalla de la orden de compra; no tiene vista propia.
- Hay además 6 pantallas de acceso que soportan el recorrido administrativo: Login, Register, Forgot Password, Reset Password, Verify Email e Invitation. Total de vistas Inertia inspeccionadas: 75.
- Product Variants y Product Options no tienen páginas independientes: viven dentro de Product Edit. Shipments viven dentro de Fulfillment. StoreSettings, SUNAT, Tax, Cart y Checkout sí tienen UI administrativa. No existe UI administrativa separada para un storefront.

## Leyenda de estados comunes

Todas las pantallas heredan hoy la barra de progreso de Inertia (`#17211b`). No existe un `LoadingSkeleton`, `ErrorState`, `EmptyState`, diálogo o toast global. Los estados se codifican así:

- **L**: progreso Inertia; sin skeleton específico.
- **E0**: vacío mediante texto/fila inline.
- **ER**: errores HTTP globales por Laravel/Inertia; errores de formulario mediante `FieldError` cuando se indica.
- **OK**: flash `status` solo donde la vista lo presenta; no hay feedback global consistente.
- **RO**: controles ocultos o deshabilitados según permisos/capabilities/estado de dominio.
- **409**: Store Settings puede devolver conflicto de concurrencia; no hay patrón visual global especializado.

## Matriz ruta ↔ componente

| ID | Módulo / nombre visible | Ruta · nombre Laravel | Componente | Tipo | Alcance | Permiso efectivo |
|---|---|---|---|---|---|---|
| ADM-001 | Analytics · Dashboard | `/admin/dashboard` · `admin.dashboard` | `admin/Dashboard.vue` | Dashboard | Tenant + Store; inventario/compras señalados tenant-wide | Secciones por `*.view`; sin `dashboard.view` global |
| ADM-002 | Tenancy · Companies | `/admin/tenants` · `admin.tenants.index` | `admin/tenants/Index.vue` | Selector/lista | Usuario, sin Tenant activo obligatorio | Usuario verificado; solo memberships activas |
| ADM-003 | Tenancy · Create company | `/admin/tenants/create` · `admin.tenants.create` | `admin/tenants/Create.vue` | Formulario | Usuario | Usuario verificado |
| ADM-004 | Access · Access matrix | `/admin/access` · `admin.access.index` | `admin/access/Index.vue` | Matriz readonly | Tenant | `tenant.view` efectivo; roles de sistema |
| ADM-005 | Settings · Settings hub | `/admin/settings` · `admin.settings.index` | `admin/settings/Index.vue` | Hub | Tenant | `settings.view`; tarjetas por permiso |
| ADM-006 | Tenancy · Company settings | `/admin/settings/general` · `admin.settings.general.show` | `admin/settings/General.vue` | Formulario | Tenant | `settings.view`; mutación `settings.manage` |
| ADM-007 | Tenancy · Team & Access | `/admin/settings/team` · `admin.settings.team.index` | `admin/settings/Team.vue` | Tabla + formulario | Tenant | `members.view`; mutación `members.manage` |
| ADM-008 | Notifications · Preferences | `/admin/settings/notifications` · `admin.notifications.preferences.show` | `admin/settings/Notifications.vue` | Formulario | Tenant + usuario; Store contextual | Membresía activa; tipos obligatorios no muteables |
| ADM-009 | Stores · Stores | `/admin/stores` · `admin.stores.index` | `admin/stores/Index.vue` | Lista | Tenant | `stores.view` |
| ADM-010 | Stores · Create store | `/admin/stores/create` · `admin.stores.create` | `admin/stores/Create.vue` | Formulario | Tenant | `stores.create` |
| ADM-011 | Stores · Edit store | `/admin/stores/{store}/edit` · `admin.stores.edit` | `admin/stores/Edit.vue` | Formulario | Tenant | `stores.view`; mutación `stores.update` |
| ADM-012 | StoreSettings · Store settings | `/admin/stores/{store}/settings` · `admin.stores.settings.show` | `admin/stores/Settings.vue` | Formulario seccionado | Tenant + Store objetivo | `stores.view`; mutación `stores.update`; media según `media.*` |
| ADM-013 | Catalog · Products | `/admin/products` · `admin.products.index` | `admin/products/Index.vue` | Tabla | Tenant | `products.view` |
| ADM-014 | Catalog · Create product | `/admin/products/create` · `admin.products.create` | `admin/products/Create.vue` | Formulario | Tenant | `products.create` |
| ADM-015 | Catalog · Edit product | `/admin/products/{product}/edit` · `admin.products.edit` | `admin/products/Edit.vue` | Formulario compuesto | Tenant | `products.view`; mutaciones `products.update`; pricing/media/tax adicionales |
| ADM-016 | Catalog · Categories | `/admin/categories` · `admin.categories.index` | `admin/categories/Index.vue` | Lista | Tenant | `categories.view` |
| ADM-017 | Catalog · Create category | `/admin/categories/create` · `admin.categories.create` | `admin/categories/Create.vue` | Formulario | Tenant | `categories.create` |
| ADM-018 | Catalog · Edit category | `/admin/categories/{category}/edit` · `admin.categories.edit` | `admin/categories/Edit.vue` | Formulario | Tenant | `categories.view`; mutación `categories.update` |
| ADM-019 | Media · Media library | `/admin/media` · `admin.media.index` | `admin/media/Index.vue` | Grid + uploader | Tenant | `media.view`; upload/delete por `media.upload`/`media.delete` |
| ADM-020 | Listings · Listings | `/admin/listings` · `admin.listings.index` | `admin/listings/Index.vue` | Tabla + settings | Tenant + Store | `listings.view`; acciones específicas |
| ADM-021 | Listings · Create listing | `/admin/listings/create` · `admin.listings.create` | `admin/listings/Create.vue` | Formulario + selector | Tenant + Store | `listings.create` |
| ADM-022 | Listings · Edit listing | `/admin/listings/{listing}/edit` · `admin.listings.edit` | `admin/listings/Edit.vue` | Formulario + tabla | Tenant + Store | `listings.view`; mutación/publicación por permisos |
| ADM-023 | Pricing · Price lists | `/admin/pricing` · `admin.pricing.index` | `admin/pricing/Index.vue` | Lista | Tenant | `pricing.view` |
| ADM-024 | Pricing · Create price list | `/admin/pricing/price-lists/create` · `admin.pricing.price-lists.create` | `admin/pricing/price-lists/Create.vue` | Formulario | Tenant | `pricing.create` |
| ADM-025 | Pricing · Edit price list | `/admin/pricing/price-lists/{priceList}/edit` · `admin.pricing.price-lists.edit` | `admin/pricing/price-lists/Edit.vue` | Formulario + precios paginados | Tenant | `pricing.view`; mutación `pricing.update` |
| ADM-026 | Promotions · Promotions | `/admin/promotions` · `admin.promotions.index` | `admin/promotions/Index.vue` | Tabla | Tenant | `promotions.view` |
| ADM-027 | Promotions · Create promotion | `/admin/promotions/create` · `admin.promotions.create` | `admin/promotions/Create.vue` | Formulario compuesto | Tenant | `promotions.create` |
| ADM-028 | Promotions · Edit promotion | `/admin/promotions/{promotion}/edit` · `admin.promotions.edit` | `admin/promotions/Edit.vue` | Formulario + preview | Tenant | `promotions.view`; mutación `promotions.update` |
| ADM-029 | Inventory · Stock | `/admin/inventory` · `admin.inventory.index` | `admin/inventory/Index.vue` | Tabla | Tenant | `inventory.view` |
| ADM-030 | Inventory · Variant stock | `/admin/inventory/variants/{variant}` · `admin.inventory.variants.show` | `admin/inventory/Variant.vue` | Detalle + movimientos | Tenant | `inventory.view`; ajuste `inventory.adjust`; coste separado |
| ADM-031 | Inventory · Transfer stock | `/admin/inventory/transfers/create` · `admin.inventory.transfers.create` | `admin/inventory/transfers/Create.vue` | Formulario dinámico | Tenant | `inventory.adjust` |
| ADM-032 | Inventory · Locations | `/admin/inventory/locations` · `admin.inventory.locations.index` | `admin/inventory/locations/Index.vue` | Lista | Tenant | `inventory.view` |
| ADM-033 | Inventory · Create location | `/admin/inventory/locations/create` · `admin.inventory.locations.create` | `admin/inventory/locations/Create.vue` | Formulario | Tenant | `inventory.adjust` efectivo |
| ADM-034 | Inventory · Edit location | `/admin/inventory/locations/{location}/edit` · `admin.inventory.locations.edit` | `admin/inventory/locations/Edit.vue` | Formulario | Tenant | `inventory.view`; mutación `inventory.adjust` |
| ADM-035 | Costing · Inventory valuation | `/admin/inventory/valuation` · `admin.inventory.cost.index` | `admin/inventory/Valuation.vue` | Tabla + KPI | Tenant | `inventory.cost.view` |
| ADM-036 | Customers · Customers | `/admin/customers` · `admin.customers.index` | `admin/customers/Index.vue` | Tabla | Tenant | `customers.view` |
| ADM-037 | Customers · Create customer | `/admin/customers/create` · `admin.customers.create` | `admin/customers/Create.vue` | Formulario | Tenant | `customers.create` |
| ADM-038 | Customers · Customer detail | `/admin/customers/{customer}` · `admin.customers.show` | `admin/customers/Show.vue` | Detalle + historial | Tenant; órdenes de todas las Stores | `customers.view`; links de órdenes por `orders.view` y Store activa |
| ADM-039 | Customers · Edit customer | `/admin/customers/{customer}/edit` · `admin.customers.edit` | `admin/customers/Edit.vue` | Formularios compuestos | Tenant | `customers.view`; mutación `customers.update` |
| ADM-040 | Cart · Carts | `/admin/carts` · `admin.carts.index` | `admin/carts/Index.vue` | Tabla + alta | Tenant + Store | `carts.view`; alta `carts.manage` |
| ADM-041 | Cart · Cart detail | `/admin/carts/{cart}` · `admin.carts.show` | `admin/carts/Show.vue` | Detalle editable | Tenant + Store | `carts.view`; mutaciones `carts.manage` |
| ADM-042 | Checkout · Checkouts | `/admin/checkouts` · `admin.checkouts.index` | `admin/checkouts/Index.vue` | Tabla | Tenant + Store | `checkouts.view` |
| ADM-043 | Checkout · Start checkout | `/admin/checkouts/create` · `admin.checkouts.create` | `admin/checkouts/Prepare.vue` | Formulario | Tenant + Store | `checkouts.manage` |
| ADM-044 | Checkout · Checkout detail | `/admin/checkouts/{checkout}` · `admin.checkouts.show` | `admin/checkouts/Show.vue` | Detalle/formulario | Tenant + Store | `checkouts.view`; mutaciones `checkouts.manage` |
| ADM-045 | Orders · Orders | `/admin/orders` · `admin.orders.index` | `admin/orders/Index.vue` | Tabla | Tenant + Store | `orders.view` |
| ADM-046 | Orders · Order detail | `/admin/orders/{order}` · `admin.orders.show` | `admin/orders/Show.vue` | Workbench compuesto | Tenant + Store | `orders.view`; bloques/acciones por payments, fulfillment, returns, fiscal, costing |
| ADM-047 | Payments · Payments | `/admin/payments` · `admin.payments.index` | `admin/payments/Index.vue` | Tabla | Tenant + Store | `payments.view` |
| ADM-048 | Payments · Payment detail | `/admin/payments/{payment}` · `admin.payments.show` | `admin/payments/Show.vue` | Detalle + ledger/refund | Tenant + Store | `payments.view`; refund `refunds.manage` |
| ADM-049 | Payments · Payment settings | `/admin/settings/payments` · `admin.payments.settings.show` | `admin/payments/Settings.vue` | Formulario | Tenant + Store | `payments.settings` |
| ADM-050 | Fulfillment · Fulfillments | `/admin/fulfillments` · `admin.fulfillments.index` | `admin/fulfillments/Index.vue` | Tabla | Tenant + Store | `fulfillments.view` + `shipments.view` |
| ADM-051 | Fulfillment · Fulfillment detail | `/admin/fulfillments/{fulfillment}` · `admin.fulfillments.show` | `admin/fulfillments/Show.vue` | Workbench | Tenant + Store | views anteriores; acciones `fulfillments.*`/`shipments.manage` |
| ADM-052 | Returns · Returns | `/admin/returns` · `admin.returns.index` | `admin/returns/Index.vue` | Tabla/cards responsive | Tenant + Store | `returns.view` |
| ADM-053 | Returns · Return detail | `/admin/returns/{return}` · `admin.returns.show` | `admin/returns/Show.vue` | Workbench | Tenant + Store | `returns.view`; manage/inspect/restock/exchange específicos |
| ADM-054 | Exchanges · Exchanges | `/admin/exchanges` · `admin.exchanges.index` | `admin/exchanges/Index.vue` | Tabla/cards responsive | Tenant + Store | `exchanges.view` |
| ADM-055 | Exchanges · Exchange detail | `/admin/exchanges/{exchange}` · `admin.exchanges.show` | `admin/exchanges/Show.vue` | Detalle/acciones | Tenant + Store | `exchanges.view`; mutación `exchanges.manage` |
| ADM-056 | Refunds · Refunds | `/admin/refunds` · `admin.refunds.index` | `admin/refunds/Index.vue` | Tabla/cards responsive | Tenant + Store | `refunds.view` |
| ADM-057 | Refunds · Refund detail | `/admin/refunds/{refund}` · `admin.refunds.show` | `admin/refunds/Show.vue` | Detalle/acción | Tenant + Store | `refunds.view`; completar `refunds.manage` |
| ADM-058 | Procurement · Suppliers | `/admin/suppliers` · `admin.suppliers.index` | `admin/suppliers/Index.vue` | Tabla | Tenant | `suppliers.view` |
| ADM-059 | Procurement · Create supplier | `/admin/suppliers/create` · `admin.suppliers.create` | `admin/suppliers/Create.vue` | Formulario | Tenant | `suppliers.create` |
| ADM-060 | Procurement · Edit supplier | `/admin/suppliers/{supplier}/edit` · `admin.suppliers.edit` | `admin/suppliers/Edit.vue` | Formularios compuestos | Tenant | `suppliers.view`; update/archive específicos |
| ADM-061 | Procurement · Purchase orders | `/admin/purchase-orders` · `admin.purchase-orders.index` | `admin/purchase-orders/Index.vue` | Tabla | Tenant | `procurement.view` |
| ADM-062 | Procurement · Create purchase order | `/admin/purchase-orders/create` · `admin.purchase-orders.create` | `admin/purchase-orders/Create.vue` | Formulario dinámico | Tenant | `procurement.manage` |
| ADM-063 | Procurement · Purchase order detail | `/admin/purchase-orders/{purchaseOrder}` · `admin.purchase-orders.show` | `admin/purchase-orders/Show.vue` | Workbench + receipts | Tenant | `procurement.view`; manage/approve/receive específicos |
| ADM-064 | Tax · Taxes | `/admin/taxes` · `admin.taxes.index` | `admin/taxes/Index.vue` | Formularios inline | Tenant | `taxes.view`; mutación `taxes.manage` |
| ADM-065 | Fiscal · Fiscal settings | `/admin/settings/fiscal` · `admin.fiscal.settings.show` | `admin/fiscal/Settings.vue` | Formularios inline | Issuer Tenant + series Store | `fiscal_settings.view`; mutación `fiscal_settings.manage` |
| ADM-066 | Fiscal/SUNAT · Fiscal documents | `/admin/fiscal-documents` · `admin.fiscal-documents.index` | `admin/fiscal-documents/Index.vue` | Tabla | Tenant + Store | `fiscal_documents.view` |
| ADM-067 | Fiscal/SUNAT · Fiscal document detail | `/admin/fiscal-documents/{fiscalDocument}` · `admin.fiscal-documents.show` | `admin/fiscal-documents/Show.vue` | Detalle + intentos | Tenant + Store | view; envío/artefactos `fiscal_documents.manage` |
| ADM-068 | Notifications · Notification center | `/admin/notifications` · `admin.notifications.index` | `admin/notifications/Index.vue` | Lista paginada | Tenant + Store/tenant-wide + usuario | Membresía; filtrado adicional por permiso del tipo |
| ADM-069 | Audit · Activity | `/admin/activity` · `admin.activity.index` | `admin/activity/Index.vue` | Tabla/ledger | Tenant; Store seleccionable | `audit.view` |

## Matriz funcional por pantalla

“Sin modal” significa que hoy se usa navegación, formulario inline o `window.confirm`; no se inventa un modal existente.

| ID | Propósito e información | Filtros / búsqueda / paginación | Acciones y formularios; validación relevante | Relaciones | Estados, readonly, reutilización y observación visual |
|---|---|---|---|---|---|
| ADM-001 | KPIs, comparación, tendencia, órdenes recientes, ventas, pagos, rentabilidad, inventario, compras, returns y fiscal. | Rango today/yesterday/7d/30d/month/custom; custom from/to; sin paginación. | Cambiar rango; fechas válidas, inclusivas y máx. 366 días. Readonly. | Enlaces a órdenes, productos, inventario, PO y fiscal. | L/ER/E0; secciones ausentes por RBAC. `DashboardTrendChart`, `MetricDelta`, `AdminLayout`. Muy extensa y sin skeleton.
| ADM-002 | Memberships/compañías disponibles y selección activa. | Sin filtros/paginación. | Seleccionar empresa; ir a crear. | Dashboard/Stores. | L/E0/ER; cards simples; `AdminLayout`.
| ADM-003 | Alta de Tenant. | — | Nombre requerido/máx. según FormRequest. | Vuelve a Companies. | L/ER/FieldError; formulario único.
| ADM-004 | Matriz OWNER/ADMIN/MEMBER y permisos efectivos del miembro. | — | Solo lectura; no custom roles ni edición individual. | Team. | RO/L/ER; tabla densa sin responsive especializado.
| ADM-005 | Hub derivado de configuración y resumen de setup. | — | Abrir General, Team, Stores, payments, tax, fiscal según RBAC. | Todas las settings. | RO por tarjeta; L/E0/ER; cards.
| ADM-006 | Nombre/estado contextual de compañía. | — | Guardar nombre; solo `settings.manage`. | Settings hub. | L/ER/OK/RO; `FieldError`.
| ADM-007 | Miembros, roles, estados e invitaciones pendientes. | Paginación no implementada. | Invitar email+rol; cambiar rol; suspender/reactivar; reenviar/revocar. No auto-cambio ni último OWNER. | Access matrix, invitations. | L/E0/ER/OK/RO; `window.confirm`, tabla ancha.
| ADM-008 | Preferencia personal de email y tipos silenciados. | — | Toggle email y muted types; alertas fiscales obligatorias no muteables. | Notification center. | L/ER/OK/RO; formulario agrupado.
| ADM-009 | Tiendas, estado, moneda, timezone y selección. | — | Seleccionar, crear, editar, abrir settings. | Dashboard/store settings. | L/E0/ER/RO; lista/cards.
| ADM-010 | Alta de Store. | — | Nombre, moneda, timezone, locale; required/formatos del request. | Stores. | L/ER/FieldError.
| ADM-011 | Identidad operativa de Store. | — | Nombre, ACTIVE/INACTIVE, currency, timezone, locale. Desactivar Store activa limpia contexto. | Stores/settings. | L/ER/OK/RO.
| ADM-012 | Identidad, branding, contacto, dirección PE, social, currency/locale/timezone. | MediaPicker tiene búsqueda/página propia. | Guardar con versiones `updated_at`; logo/favicon same-tenant; HTTPS/domino social; color/phone/locale. | Media library y Store. | L/ER/OK/409/RO; `MediaPicker`, `MediaThumbnail`, `FieldError`; página larga.
| ADM-013 | Producto, slug, status, SKU default, #variantes, categorías. | Search nombre/SKU; 15/página. | Crear, editar, archivar con confirmación. | Product Edit/Categories. | L/E0/ER/RO; tabla horizontal; no thumbnail.
| ADM-014 | Producto base y variante default. | — | Nombre/description/SKU/barcode/categorías; límites y unicidad tenant desde request. | Product Edit/Categories. | L/ER; `FieldError`.
| ADM-015 | Detalle de Product, categorías, tax, options/values, variants, galerías y default pricing. | MediaPicker busca/pagina; no paginación de variantes. | Editar producto; alta options/variants; editar variants; precios; adjuntar/reordenar/quitar media. Validación por FormRequests y permisos cruzados. | Media, Categories, Pricing, Inventory/Listings. | L/E0/ER/OK/RO; múltiples forms; `MediaPicker/Thumbnail`; sin tabs, página muy larga.
| ADM-016 | Jerarquía, status, posición, parent y product count. | 20/página; sin búsqueda. | Crear/editar/archivar. | Products. | L/E0/ER/RO; cards, no árbol visual.
| ADM-017 | Alta category. | — | name, parent, position; parent same-tenant, sin ciclos. | Categories. | L/ER; `FieldError`.
| ADM-018 | Edit category y status. | — | name/status/parent/position; evita self/descendant. | Categories/Products. | L/ER/RO.
| ADM-019 | Assets privados, metadata, dimensiones, peso, mime y usage count. | Search título/ALT/filename; tipo IMAGE fijo; 24/página. | Upload JPEG/PNG/WebP, title/ALT; delete solo usage=0. | Product Edit/Store Settings. | L/E0/ER; lazy `<img>`; uploader siempre visible; paginación usa `<a>` (recarga completa: defecto UX/performance confirmado).
| ADM-020 | Listings de Store, estado, producto, slug, precio y default price list. | Search producto/slug/SKU; paginada. | Settings de price list; crear/editar; publicar/despublicar/archivar según capability. | Products/Pricing. | L/E0/ER/RO; tabla ancha.
| ADM-021 | Vincular Product tenant-wide a Store. | Product search, máximo 50. | product radio, storefront slug, price-list override. | Listings/Products/Pricing. | L/E0/ER; selector scrollable.
| ADM-022 | Listing, price list efectiva y variantes vendibles/precios/promos. | Search variantes. | Editar slug/list; habilitar variantes; publish/unpublish/archive. | Product/Pricing/Promotions. | L/E0/ER/RO; tabla ancha, server preview.
| ADM-023 | Price lists, moneda, estado/default. | — | Crear/editar, make default, archivar. | Product Edit/Listings. | L/E0/ER/RO; cards/lista.
| ADM-024 | Alta price list. | — | name/code/currency; code estable y tenant-unique. | Pricing. | L/ER.
| ADM-025 | Configuración de list y precio por variante. | Search producto/SKU; variantes paginadas. | Edit list; set/remove amount decimal scale 4. | Products/Listings. | L/E0/ER/RO; forms por fila; sin bulk edit.
| ADM-026 | Promos, descuento, estado/ventana/prioridad/scope. | Search name/internal code; paginada. | Crear/editar/archivar. | Products/Categories/Variants/Pricing. | L/E0/ER/RO; tabla 7 columnas.
| ADM-027 | Regla promocional y targets. | Target search. | name/code/description/status/type/value/currency/dates/priority/scope y IDs; no stacking, fixed currency exacta, UTC half-open. | Catalog/Pricing. | L/E0/ER; tres selectores scrollables.
| ADM-028 | Edit + preview efectivo server-side. | Target search; selector variant/price list para preview. | Igual a alta; calcular preview saved config; archivar desde navegación previa. | Catalog/Pricing. | L/E0/ER/RO; preview no sustituye guardado.
| ADM-029 | Stock agregado por variante y localización. | Search producto/SKU; paginada. | Abrir variante; nueva transferencia si puede ajustar. | Variant/Locations/Valuation. | L/E0/ER/RO; tabla horizontal.
| ADM-030 | Levels on-hand/reserved/available, coste y movement history. | Movimientos paginados. | Ajustar quantity+reason; inicializar coste por location si UNINITIALIZED. | Inventory/Costing. | L/E0/ER/RO; múltiples forms capability-driven.
| ADM-031 | Traslado entre locations. | — | source/destination/notes y líneas variant+quantity; distintas locations, enteros positivos. | Inventory/Locations. | L/ER; líneas dinámicas.
| ADM-032 | Locations, code/status/default. | — | Crear/editar/make default. | Inventory. | L/E0/ER/RO.
| ADM-033 | Alta location. | — | name/code/status; code tenant-unique. | Locations. | L/ER.
| ADM-034 | Edit location. | — | name/code/status. | Locations. | L/ER/RO.
| ADM-035 | Valor por variant/location y totales por currency. | Search SKU/product/location; paginada. | Solo lectura; abrir variant. | Inventory/Costing. | L/E0/ER/RO; tabla 6 columnas y KPI totals.
| ADM-036 | Customer, email, phone, status, address count, created. | Search name/email/phone + status; 20/página. | Crear/ver/editar/archivar según permisos. | Detail/Orders. | L/E0/ER/RO; tabla ancha.
| ADM-037 | Alta customer. | — | first/last/email/phone/notes; email/phone únicos por tenant cuando presentes. | Customers. | L/ER.
| ADM-038 | Identidad, address book y order history tenant-wide. | Órdenes 10/página. | Edit/archive; abrir solo órdenes de Store activa. | Orders/Customer Edit. | L/E0/ER/RO; alerta de órdenes en otras Stores.
| ADM-039 | Customer y address book. | — | Edit customer; add/edit/delete/default addresses. Required country/city/address; confirm delete/archive. | Customer detail. | L/E0/ER/RO; muchos forms inline.
| ADM-040 | Carts de Store, token hint, customer, status/totals. | Search token/customer; paginada. | Crear cart, abrir. | Checkout/Customer. | L/E0/ER/RO.
| ADM-041 | Cart items, pricing/tax preview y customer. | — | Add listing/variant/qty; update/remove/clear; attach customer. Cantidades 1..9999. | Listings/Customers/Checkout. | L/E0/ER/RO; confirm clear.
| ADM-042 | Checkout snapshots, status, totals, expiry. | Search token/name/email + status; paginada. | Start/open. | Cart/Orders. | L/E0/ER/RO; tabla.
| ADM-043 | Iniciar checkout desde cart activo. | — | Cart + idempotency UUID. | Carts/Checkout detail. | L/E0/ER.
| ADM-044 | Contact, delivery/address snapshot, lines/tax/totals/reservations. | — | Save pending; prepare/reserve; cancel; create Order. Server recalcula; no client amounts. | Cart/Order. | L/E0/ER/RO por lifecycle; confirm cancel.
| ADM-045 | Orders y snapshots de customer/status/payment/fulfillment/total. | Search; 3 statuses; customer_id contextual; from/to; 20/página. | Abrir detalle; limpiar filtros. | Customers y módulos operativos. | L/E0/ER; tabla 7 columnas.
| ADM-046 | Workbench: comercial, items, history, customer, payment plan/ledger, fulfillment, fiscal, returns, profitability. | — | Cancel; payment/capture/terms; create fulfillment/return/fiscal doc. Validaciones y transiciones server-side. | Customers, Payments, Refunds, Returns, Fulfillment, Fiscal. | L/E0/ER/OK/RO; componente mayor (32 KB fuente), página muy larga sin tabs.
| ADM-047 | Payments por order, method/provider/status/amount/captured/ref. | Search order + status + order_id; paginada. | Abrir payment/order. | Orders/Refunds. | L/E0/ER; tabla.
| ADM-048 | Payment summary, transactions y refunds. | — | Crear refund amount/reason/idempotency/optional return; límites contra ledger. | Order/Refunds. | L/E0/ER/RO.
| ADM-049 | Métodos y COD deposit. | — | Toggles; deposit NONE/PERCENTAGE/FIXED + decimal. No credenciales provider. | Orders/Payments. | L/ER/RO.
| ADM-050 | Fulfillment work, location/status/items/shipments. | Search order + status + order_id; paginada. | Abrir detail/order. | Orders/Shipments. | L/E0/ER; tabla.
| ADM-051 | Pick list, history, shipment/tracking. | — | Start/complete/cancel fulfillment; create shipment; ready/ship/deliver/cancel. | Order. | L/E0/ER/RO por state machines; forms inline.
| ADM-052 | Returns con order/customer/status/items/date. | Search + status + order_id; paginada. | Abrir return/order. | Orders/Exchanges. | L/E0/ER; tabla desktop + cards mobile ya implementadas.
| ADM-053 | Return items/history/exchanges. | — | Approve/reject/receive/inspect/restock/complete/cancel/create exchange. Cantidades y estados server-side. | Order/Inventory/Exchange. | L/E0/ER/RO; workbench muy largo; forms por ítem.
| ADM-054 | Exchange/order/customer/status/items/date. | Search + status + order_id; paginada. | Abrir exchange/return/order. | Returns/Orders. | L/E0/ER; tabla/cards responsive.
| ADM-055 | Exchange items/status. | — | Approve/complete/cancel reason. | Return/Order. | L/ER/RO.
| ADM-056 | Refund/payment/order/amount/status/date. | Search order + status + order_id; paginada. | Abrir refund/payment/order. | Payments/Orders. | L/E0/ER; tabla/cards responsive.
| ADM-057 | Refund detail. | — | Completar manualmente con idempotency + external reference. | Payment/Order/Return. | L/ER/RO.
| ADM-058 | Suppliers, contact/status/product count. | Search name/code/email/phone + status; paginada. | Crear/editar. | Supplier products/PO. | L/E0/ER; tabla.
| ADM-059 | Alta supplier. | — | name/code/contact/email/phone/website/notes. | Suppliers. | L/ER.
| ADM-060 | Supplier + product mappings/cost/MOQ/multiple/lead/preferred. | — | Edit/archive supplier; add/edit/archive mapping. | Catalog variants/PO. | L/E0/ER/RO; forms repetidos, página larga.
| ADM-061 | PO, supplier/status/total/ordered/received/remaining/expected. | Search + supplier + status; paginada. | Crear/abrir. | Suppliers/Receipts. | L/E0/ER; tabla 8 columnas.
| ADM-062 | Draft PO y líneas. | — | supplier/currency/expected/notes; líneas supplier product/qty/cost; MOQ/multiple server-side. | Supplier/PO. | L/E0/ER; líneas dinámicas.
| ADM-063 | PO lifecycle, items, draft receipts y receiving. | — | Edit draft/items; approve/ordered/cancel; create/update/post/cancel receipts. | Supplier/Inventory/Costing. | L/E0/ER/RO; receipt GET redirige aquí; workbench largo.
| ADM-064 | Tax categories y rates efectivas. | — | Add/edit category/rate; dates, status, rate decimal, categorías exempt/unaffected a cero. | Products/Checkout/Fiscal. | L/E0/ER/RO; forms inline por fila.
| ADM-065 | Issuer tenant-wide y series por Store. | — | Edit issuer; create/edit series/type/padding/status. | Orders/Fiscal docs. | L/E0/ER/RO; no secretos SOL/certificados en UI.
| ADM-066 | Fiscal docs: number/type/status/order/customer/total/date. | Search + type + status + from/until; paginada. | Abrir documento. | Orders. | L/E0/ER; tabla.
| ADM-067 | Snapshots issuer/customer/items/totals, submissions y artifacts. | — | Submit SUNAT solo elegible; descargar signed XML/CDR. | Order/Fiscal settings. | L/E0/ER/RO; estados UNKNOWN/SUBMITTED bloquean resend; no producción.
| ADM-068 | Inbox personal: title generic, severity/type/state/date/link. | unread/active/resolved + severity/type; 25/página. | Read one/all, dismiss, abrir recurso. | Preferences y recursos autorizados. | L/E0/ER; permission revocation oculta filas; no datos sensibles.
| ADM-069 | Ledger append-only: actor/action/subject/store/time/metadata sanitizada. | action, subject, actor, Store, from/to; 50/página. | Solo lectura, abrir páginas relacionadas cuando exista link. | Team/operaciones. | L/E0/ER/RO; tabla ~980px; metadata sin secretos.

## Pantallas de acceso relacionadas (fuera de las 69 admin)

| ID | Ruta | Componente | Función |
|---|---|---|---|
| AUTH-001 | `/login` · `login` | `auth/Login.vue` | Sesión Laravel; email/password/remember; rate limit. |
| AUTH-002 | `/register` · `register` | `auth/Register.vue` | Registro; name/email/password/confirmation; rate limit. |
| AUTH-003 | `/forgot-password` · `password.request` | `auth/ForgotPassword.vue` | Solicitar enlace; respuesta no enumera cuentas. |
| AUTH-004 | `/reset-password/{token}` · `password.reset` | `auth/ResetPassword.vue` | Token/email/password/confirmation. |
| AUTH-005 | `/verify-email` · `verification.notice` | `auth/VerifyEmail.vue` | Reenvío verificación con throttle y logout. |
| AUTH-006 | `/invitations/{token}` · `invitations.show` | `invitations/Show.vue` | Revisar/aceptar invitación; usuario autenticado/verificado y email coincidente. |

## Funcionalidades investigadas sin pantalla independiente

- **Product Variants / Product Options:** subsecciones de ADM-015.
- **Shipments:** subsección de ADM-050/051; README confirma que no hay pantalla standalone.
- **Returns/Refunds/Exchanges:** sí tienen pantallas separadas.
- **SUNAT:** ADM-067; transporte production no disponible y no se contactó.
- **Notifications:** ADM-008/068.
- **Audit:** ADM-069.
- **Storefront:** deliberadamente ausente.
- **Purchase Receipt:** operación y bloque dentro de ADM-063; su GET es solo redirect.
