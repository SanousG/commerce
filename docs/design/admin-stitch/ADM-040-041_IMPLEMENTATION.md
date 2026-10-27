# ADM-040–041 — Implementación Cart

Fecha: 2026-10-26
Fase: 26.24

## Alcance entregado

El inventario oficial separa Cart de Checkout. Se rediseñaron e internacionalizaron las dos pantallas consecutivas del módulo Cart, sin iniciar Checkout ADM-042–044, generar diseños Stitch, modificar Laravel ni agregar dependencias:

| ID | Ruta | Vista | Resultado |
|---|---|---|---|
| ADM-040 | `/admin/carts` | `admin/carts/Index.vue` | Tabla desktop, cards mobile, búsqueda real, alta de carrito invitado y paginación |
| ADM-041 | `/admin/carts/{cart}` | `admin/carts/Show.vue` | Detalle, cálculos vivos, cliente, líneas y mutaciones existentes |

Ambas pantallas usan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice` según Executive Precision B2B.

## Contrato funcional confirmado

No existe `app/Modules/Cart/README.md`; por ello el contrato se verificó directamente contra rutas, controller, Form Requests, acciones Application, modelos, enums y tests.

### Listado

- Requiere `carts.view` y opera únicamente sobre la Store activa y el Tenant autorizado.
- Busca por prefijo del token completo almacenado o por nombre, apellido o correo del Customer.
- Ordena por `updated_at` descendente y pagina 20 registros en servidor.
- Solo expone `token_hint` de ocho caracteres; el token completo nunca llega a Inertia.
- Cada fila conserva Customer nullable, estado, suma de cantidades, moneda nullable, subtotal calculado, validez, expiración y actualización.
- La única alta existente crea un carrito invitado. Aunque el backend acepta opcionalmente `customer_id`, el endpoint de índice existente no entrega un selector de Customer; no se inventó otro flujo.

### Detalle y cálculo

- Los estados reales son `ACTIVE`, `CONVERTED`, `EXPIRED` y `ABANDONED`.
- Solo un carrito `ACTIVE` y un usuario con `carts.manage` puede mutar. Todo otro detalle es readonly.
- Laravel calcula base antes de descuentos, descuento, subtotal, bases gravada/exonerada/inafecta, impuesto incluido y validez; la UI no calcula importes.
- Los importes conservan los strings exactos de escala 4 y no aplican conversión de moneda ni redondeo del navegador.
- Cada línea muestra precio unitario base/final, importes base/descuento/final, categoría/tasa/impuesto, disponibilidad, promociones aplicadas y problemas canónicos.
- Los códigos de problemas se traducen en UI: listing/store no disponible, Product/Variant inactivo, Variant inválida/deshabilitada, precio faltante, moneda distinta, stock insuficiente y configuración tributaria faltante/inválida.

## Operaciones y ciclo de vida

| Operación | Contrato conservado |
|---|---|
| Crear carrito | `POST /admin/carts`; crea `ACTIVE`, token aleatorio de 64 hex, moneda null y TTL configurado (72 h por defecto) |
| Agregar línea | Listing `PUBLISHED`, Variant habilitada/elegible, precio resoluble, moneda coherente, stock disponible y cantidad 1..9999 |
| Actualizar cantidad | Cantidad 1..9999; revalida elegibilidad, precio, moneda y stock cuando aumenta |
| Quitar línea | DELETE de una línea existente; limpia moneda cuando desaparece la última línea |
| Vaciar | DELETE de todas las líneas; limpia moneda |
| Asignar Customer | Customer opcional del mismo Tenant; permite volver a invitado |

Agregar una Variant ya presente incrementa la línea existente. Todas las mutaciones activas extienden el TTL. Cart no expone endpoints administrativos para cambiar manualmente estado, convertir, abandonar, expirar o eliminar físicamente el carrito; no se agregaron acciones para ello.

## Seguridad, aislamiento e integraciones

- Las rutas continúan bajo autenticación de sesión, verificación, CSRF, `tenant` y `store` middleware.
- `carts.view` autoriza lectura; `carts.manage` autoriza todas las mutaciones. Owner/Admin administran y Member conserva solo lectura.
- Cart, CartItem y Listing se resuelven por Tenant y Store activos. Customer y ProductVariant se limitan al Tenant; las acciones vuelven a validar coherencia Store/Product.
- UUID de otra Store o Tenant devuelve 404. No se acepta `tenant_id` ni `store_id` del frontend.
- El carrito consulta disponibilidad tenant-wide, pero no reserva ni mueve inventario. Las reservas empiezan al preparar Checkout.
- Pricing, Promotions y Tax se recalculan en servidor. No se modificaron sus reglas ni los snapshots posteriores de Checkout/Orders.
- La expiración de un `ACTIVE` vencido es server-side; los estados terminales no se reabren.

## UX, responsive e internacionalización

- Desktop conserva una tabla compacta de ocho columnas en ADM-040; mobile 390 px usa cards sin `min-width`, overflow horizontal ni navegación inferior.
- ADM-041 usa KPIs compactos, formularios adaptables y cards de línea; importes largos pueden truncarse dentro de su superficie sin ampliar el viewport.
- Inputs tienen `id`/label, controles numéricos conservan límites 1..9999, botones bloquean reenvíos y las operaciones destructivas piden confirmación localizada.
- Se agregó el catálogo `carts.*` completo en `en` y `es-PE`, junto con estados `ABANDONED`, `CONVERTED` y `EXPIRED`.
- Español (Perú) permanece predeterminado; fechas usan el locale activo. Datos persistidos como nombres, SKU y promociones no se traducen.
- Los mensajes flash conocidos se mapean a textos localizados. Los errores de validación continúan siendo autoridad de Laravel y se presentan junto al control o como aviso global.

## Rendimiento y bundles

- Se preserva paginación server-side de 20 y `withQueryString()`.
- El índice conserva el cálculo batch `calculateMany`; no se agregaron requests ni consultas por fila.
- Customers se limita a 100 y Listings/Variants solo se cargan cuando el carrito es administrable, como en el contrato existente.
- Build Vite (tamaño raw / gzip):
  - ADM-040 Index: **8.17 kB / 2.87 kB**.
  - ADM-041 Show: **13.90 kB / 4.20 kB**.
  - CSS global: **79.87 kB / 16.24 kB**; app JS: **361.43 kB / 117.37 kB**.

## Validación ejecutada

| Comando | Resultado |
|---|---|
| Guard `TestingDatabaseConnectionTest` | 1 passed, 4 assertions; `pgsql.testing/commerce_testing` confirmado |
| `php artisan test tests/Feature/Cart` | 15 passed, 86 assertions |
| Checkout + Orders | 34 passed, 280 assertions |
| Suite Laravel completa | 636 passed, 4,429 assertions, 146.24 s |
| `npm run test:i18n` | 1,172 mensajes Vue, 45 labels sidebar y 43 mensajes Laravel por locale |
| `npm run typecheck` | Passed |
| `npm run build` | Passed; 828 módulos; 1.38 s |
| `git diff --check` | Passed |

La suite completa incluye RBAC, aislamiento cross-tenant/cross-store, exactitud monetaria, impuestos, promociones, inventario, Checkout y Orders. `PurchaseReceiptCostingTest` no falló en esta ejecución; no existe evidencia de una regresión nueva.

## Limitaciones y omisiones deliberadas

- No se generaron referencias Stitch; ADM-040–041 siguen sin assets propios.
- No se modificaron controller, Form Requests, Application Services, modelos, rutas, permisos, consultas o contratos Inertia.
- No se agregó filtro de estado, ordenamiento, exportación, bulk actions, Customer en el alta, token completo ni búsqueda remota de Customers/Listings.
- No se agregó eliminación de Cart, transición manual de estado, reserva de stock, inicio de Checkout ni operación comercial inexistente.
- No se inició Checkout ADM-042–044.
- La validación visual manual en navegadores desktop y viewport 390 px queda pendiente.
