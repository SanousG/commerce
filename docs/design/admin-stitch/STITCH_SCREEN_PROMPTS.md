# Prompts de pantallas y plantillas para Stitch

Aplicar siempre `STITCH_MASTER_PROMPT.md` y `ADMIN_DESIGN_TOKENS.md`. Reutilizar explícitamente `Executive Precision B2B` (`assets/a08e56da2173415785f1ffb340ddd446`): primary `#0F172A`, secondary `#2563EB`, tertiary `#0284C7`, neutral `#64748B`, Inter + JetBrains Mono. Verificar los valores aplicados en HTML/captura; el nombre del sistema en el prompt no basta. No usar una paleta principal distinta por módulo ni verdes como marca; Success/Active conserva su verde semántico oficial. Los prompts se agrupan por plantilla visual; `STITCH_DESIGN_COVERAGE.md` asigna cada una de las 69 pantallas. No marcar una pantalla como generada por usar estos textos.

## REF-01 — Design System

Diseña una página de catálogo de componentes a 1440 px y variantes 768/390 usando los tokens oficiales de `ADMIN_DESIGN_TOKENS.md`: superficies y bordes del asset, primary slate, interacción azul y estados semánticos separados. Incluye tipografía, iconos, botones, inputs/select/checkbox/radio/date, badges de estados comerciales, tabla densa, responsive cards, paginación, tabs, drawers, confirmation dialog, toast/save state, skeleton, empty/error/403/409/session-expired, KPI y chart container. Incluye estados hover/focus/disabled/processing/error. Usa datos sintéticos.

## REF-02 — Admin shell

Diseña el shell persistente con sidebar agrupado y colapsable, header, breadcrumbs, TenantSwitcher, StoreSwitcher, inbox y perfil. Muestra estado expanded/collapsed, drawer tablet/mobile y menú filtrado por RBAC. No dibujes contenido ornamental.

## REF-03 — Dashboard

Diseña Dashboard operativo con selector de rango y custom dates, KPI con comparación, chart de ventas, recent orders, top products, payment/refund, fulfillment, tenant-wide stock/procurement, returns y fiscal. Las secciones desaparecen por permiso; no dejes huecos. Diseña loading y sin datos. Prioriza scanability y evita un mosaico decorativo.

## REF-04 — Index table

Diseña un listado administrativo reutilizable: PageHeader, primary action, FilterBar, tabla compacta, status badges, row actions, empty/loading/error y Pagination. Desktop/tablet/mobile. Mobile usa cards. Parametrizable para Products, Customers, Orders, Payments, Fulfillment, Returns, Refunds, Exchanges, Suppliers, PO, Fiscal y Audit; cada cobertura mantiene columnas/filtros de su inventario.

## REF-05 — Products Index

Instancia REF-04 para Products: search name/SKU; columnas Product/slug, status, default SKU, variants count, categories, actions; create/edit/archive según permiso; 15 por página. No inventes bulk actions ni thumbnail porque la pantalla actual no lo entrega.

## REF-06 — Entity create/edit form

Diseña plantilla compacta para Create/Edit simple con breadcrumbs, título, descripción, FormSections, validation inline, save/cancel, pending/success y readonly. Instancias: Tenant, Store, Category, Location, Customer, Supplier, Price List. Conserva los campos específicos de inventario y no inventes autosave.

## REF-07 — Product workbench

Diseña Product Edit con tabs o anchors: Details, Categories & Tax, Options, Variants, Product Gallery, Variant Images, Pricing. Incluye MediaPicker drawer, ordered gallery, primary image, variant forms y prices; capability-driven readonly. Product Create es versión reducida Details/Categories. No agregar rich text, SEO ni storefront preview.

## REF-08 — Categories

Diseña Categories Index como lista jerárquica compacta, no un árbol ficticio drag-and-drop. Datos: name, parent/root, status, position, product count, edit/archive. Create/Edit usa name, parent, position y status solo en edit. Incluye empty/loading y 20/página.

## REF-09 — Media library

Diseña biblioteca privada de imágenes: uploader colapsable, search, grid de 24 thumbnails con ratio reservado, title/filename, dimensions, bytes, MIME, usage count y delete disabled si está en uso. Imágenes lazy/progressive; masters nunca públicos. Paginación Inertia visual. Incluye loading de thumbnails, error individual, vacío y permisos upload/delete.

## REF-10 — Store settings

Diseña Store Settings extensa con tabs/anchors: Identity, Branding, Contact, Address, Social, Localization. Logo/favicon usan MediaPicker; color fields; save bar; readonly; 409 conflict con reload/review choice. Mantén currency/timezone/locale y versiones de concurrencia. No agregar shipping, checkout flags ni provider credentials.

## REF-11 — Settings hub / access

Diseña Settings Hub por tarjetas sobrias con progreso derivado; Company General; Team con invitation form y member table; Access matrix readonly; Notifications Preferences; Payment Settings; Fiscal Settings/series. Distingue permisos de lectura/gestión y tipos fiscales obligatorios.

## REF-12 — Inventory operations

Diseña Stock Index, Variant Stock detail, Transfer form, Locations y Valuation. Variant incluye levels, available/on-hand/reserved, optional cost, adjustment y movement history. Transfer usa líneas dinámicas. Valuation alinea dinero y muestra totals por currency. Marca “All company”.

## REF-13 — Merchandising

Diseña Price Lists Index/Edit y Promotions Index/Create/Edit. Price Edit tiene precios paginados por variant. Promotion usa basic rule, window, priority, ALL/SELECTED targets y server-side effective-price preview. Evita coupon/segmentation/stacking porque no existen.

## REF-14 — Listings

Diseña Listings Index/Create/Edit para Store activa: default price list, product selector limitado a 50, slug, override list, variant availability, base/promotion/effective price y publish/unpublish/archive. Mostrar por qué no es publicable sin alterar backend.

## REF-15 — Customer detail

Diseña Customer Detail/Edit: identity, status, contact, address book y tenant-wide order history. Señala órdenes de otras Stores como no abribles. Edit permite CRUD/default de addresses; usa confirmation dialog. No fusionar customer con user.

## REF-16 — Cart / Checkout

Diseña Carts Index/Detail y Checkouts Index/Start/Detail. Cart detail gestiona items/customer y preview. Checkout detail organiza contact/address, immutable calculated lines, tax, reservation/lifecycle actions. No permitir editar importes ni confiar en valores frontend.

## REF-17 — Order workbench

Diseña Order Detail con summary sticky y tabs: Overview, Items, Payment, Fulfillment, Returns, Fiscal, Profitability, History. Acciones visibles solo por capability; snapshots readonly; cancel es destructivo. Order Index usa REF-04 con todos los filtros reales. No combinar payment con fulfillment.

## REF-18 — Payment / reverse logistics

Diseña Payments/Refunds/Returns/Exchanges. Indexes comparten FilterBar pero preservan columnas. Details muestran estados y ledger/workflow. Return workbench incluye receive, inspect por item, restock, exchange y transitions. Confirmaciones destructivas, idempotency fields y readonly según state machine.

## REF-19 — Fulfillment / shipment

Diseña Fulfillment Index y Detail con pick list, location, history, shipment/tracking y state actions. Shipment no tiene página separada. Mantén separación entre fulfillment completion y shipment lifecycle; no inventes labels/carrier APIs.

## REF-20 — Procurement

Diseña Suppliers Index/Create/Edit y Purchase Orders Index/Create/Detail. Supplier Edit incluye product mappings/cost/MOQ/multiple/lead/preferred. PO Detail integra items y receipts draft/post/cancel, con inventory intake explícito. No crear pantalla separada de receipt.

## REF-21 — Tax / Fiscal / SUNAT

Diseña Tax inline configuration, Fiscal Settings y Fiscal Documents Index/Detail. Detalle muestra immutable snapshots, submissions y artifacts. `UNKNOWN`/`SUBMITTED` debe bloquear resend y pedir reconciliación manual. BETA claramente visible; jamás representar production habilitado ni mostrar XML/CDR/secretos.

## REF-22 — Notifications / Audit

Diseña Notification Center (personal, generic content, severity/type/state, read/dismiss/link) y Activity append-only ledger con filters. No mostrar PII o montos en notificaciones ni secretos en audit metadata. Activity es readonly y densa; mobile cards.

## REF-23 — Authentication

Diseña shell de acceso separado para Login, Register, Forgot/Reset Password, Verify Email e Invitation. Claro, sobrio, accessible, con validation/rate-limit feedback y sesión caducada. No JWT ni social login.
