# ADM-036–039 — Implementación Customers

Fecha: 2026-10-26
Fase: 26.23

## Alcance entregado

Se rediseñaron e internacionalizaron las cuatro pantallas administrativas existentes de Customers sin generar diseños Stitch, modificar contratos Laravel ni agregar dependencias:

| ID | Ruta | Vista | Resultado |
|---|---|---|---|
| ADM-036 | `/admin/customers` | `admin/customers/Index.vue` | Tabla desktop, cards mobile, búsqueda, estado, paginación y acciones RBAC |
| ADM-037 | `/admin/customers/create` | `admin/customers/Create.vue` | Alta accesible con identidad, contacto y notas reales |
| ADM-038 | `/admin/customers/{customer}` | `admin/customers/Show.vue` | Detalle, direcciones e historial de pedidos tenant-wide paginado |
| ADM-039 | `/admin/customers/{customer}/edit` | `admin/customers/Edit.vue` | Edición, estados, libreta de direcciones y archivado lógico |

El resultado usa `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState`, `FieldError` y `StatusNotice`, con el sistema Executive Precision B2B ya aprobado.

## Contrato funcional confirmado

### Customer

- Identidad tenant-wide, separada de usuarios autenticados y de Stores.
- Campos persistidos: `first_name`, `last_name`, `email`, `phone`, `status` y `notes`.
- No existen documento de identidad, RUC, tipo de cliente, múltiples correos/teléfonos, merge, aliases ni identidad fiscal en este módulo.
- El alta exige colectivamente al menos nombre, apellido, correo o teléfono; ninguno de esos campos es individualmente obligatorio.
- Email se normaliza a minúsculas. Teléfono elimina espacios, paréntesis, puntos y guiones; no implementa validación E.164.
- Email y teléfono son únicos por Tenant cuando están presentes.
- Estados reales: `ACTIVE`, `INACTIVE`, `BLOCKED`, `ARCHIVED`.
- El alta crea `ACTIVE`. Update admite los estados suministrados por Laravel.
- Archive es lógico y conserva relaciones e historial. No existe endpoint DELETE de Customer y no se agregó.

### Direcciones

- Campos: label, recipient, phone, ISO country code de dos letras, region, city, district, dos líneas, postal code, reference y flags default shipping/billing.
- Requeridos: `country_code`, `city`, `address_line_1` y ambos booleanos de default.
- Alta, edición, selección independiente de defaults y DELETE físico están respaldados por rutas existentes.
- Una dirección puede ser default de envío y facturación; cada tipo mantiene unicidad transaccional por cliente.
- Las direcciones son datos vivos. Su edición/eliminación no altera snapshots históricos de Checkout u Orders.

### Listado y detalle comercial

- Búsqueda real por first name, last name, email o phone; filtro por estado enum; máximo 120 caracteres; 20 clientes por página.
- El índice usa `withCount('addresses')`; no se introdujeron consultas adicionales por fila.
- El detalle carga la libreta real y pagina 10 órdenes por `orders_page`.
- El historial de órdenes es tenant-wide deliberadamente. Una orden solo es enlazable cuando pertenece a la Store activa y el usuario tiene `orders.view`; las demás filas se muestran sin enlace y con aviso.
- Sin `orders.view`, Laravel no consulta ni entrega el historial (`orderHistory = null`).

## Operaciones y permisos

| Operación | Permiso |
|---|---|
| Listar/ver | `customers.view` |
| Crear | `customers.create` |
| Editar Customer y direcciones | `customers.update` |
| Crear/editar/eliminar/default de dirección | `customers.update` |
| Archivar Customer | `customers.archive` |
| Ver historial de órdenes | `orders.view` |

Owner y Admin pueden crear, actualizar y archivar. Member conserva lectura de Customers y Orders, pero la UI queda en modo readonly y Laravel sigue siendo la autoridad. Todas las mutaciones usan Inertia/Laravel con CSRF de sesión; se bloquean reenvíos mientras cada formulario procesa.

## Privacidad y aislamiento

- Todas las rutas viven en el grupo autenticado/verificado con Tenant resuelto; Customers no exige Store activa.
- Ningún `tenant_id` del frontend se acepta como autoridad. Las consultas parten de `TenantContext` o vuelven a limitarse por `tenant_id` autorizado.
- Customer, Address y Order se resuelven dentro del Tenant activo; IDs cross-tenant o UUID inválidos devuelven 404.
- La Store de sesión solo controla si un enlace de Order es alcanzable; se revalida contra el Tenant y nunca amplía la consulta.
- No se agregaron logs, telemetría, exportaciones ni mensajes que expongan datos personales.
- Notas internas solo se muestran en superficies Customer ya autorizadas.
- No se modificaron snapshots, órdenes históricas, reglas de deduplicación ni validaciones tributarias.

## UX, responsive e internacionalización

- Desktop usa tablas compactas para Customers y Orders; mobile reemplaza ambas por cards, sin ancho mínimo ni scroll horizontal.
- Formularios pasan a una columna en mobile y dos columnas donde existe espacio; acciones principales ocupan ancho completo cuando corresponde.
- Labels están asociados por `for/id`, errores usan `FieldError`, requeridos reales están identificados y controles readonly quedan deshabilitados.
- Confirmaciones de Archive y DELETE Address explican preservación histórica.
- Se agregó el catálogo `customers.*` completo en `en` y `es-PE`, junto con `status.BLOCKED`; nombres, direcciones, documentos comerciales y notas almacenadas no se traducen.
- Español (Perú) continúa como idioma predeterminado y English como alternativa.

## Rendimiento y bundles

- Se preservaron paginaciones server-side: Customers 20 y Orders 10.
- No hay requests adicionales de lectura ni historiales completos; cada operación escribe mediante su endpoint existente.
- `AddressFields.vue` evita duplicar los once campos de alta/edición de dirección.
- Build Vite (tamaño raw / gzip):
  - ADM-036 Index: **9.52 kB / 3.19 kB**.
  - ADM-037 Create: **6.59 kB / 1.97 kB**.
  - ADM-038 Show: **11.11 kB / 3.31 kB**.
  - ADM-039 Edit: **14.07 kB / 3.75 kB**.
  - chunk compartido `AddressFields`: **3.42 kB / 1.32 kB**.
  - CSS global: **79.71 kB / 16.22 kB**; app JS: **354.04 kB / 115.07 kB**.

## Validación ejecutada

| Comando | Resultado |
|---|---|
| Guard `TestingDatabaseConnectionTest` | 1 passed, 4 assertions; `pgsql.testing/commerce_testing` confirmado |
| `php artisan test tests/Feature/Customers` | 17 passed, 194 assertions |
| Orders/Checkout/Cart seleccionados | 12 passed, 130 assertions |
| Suite Laravel completa | 636 passed, 4,429 assertions, 172.29 s |
| `npm run test:i18n` | 1,081 mensajes Vue, 45 labels sidebar y 43 mensajes Laravel por locale |
| `npm run typecheck` | Passed |
| `npm run build` | Passed; 828 módulos; 1.60 s |
| `git diff --check` | Passed |

La suite completa incluye RBAC, aislamiento cross-tenant/cross-store, creación, edición, detalle, direcciones, Orders, Checkout y Cart. El fallo intermitente previamente investigado en `PurchaseReceiptCostingTest` no se reprodujo en esta ejecución. Costing no fue modificado durante FASE 26.23.

## Limitaciones y omisiones deliberadas

- No se generaron referencias Stitch nuevas; ADM-036–039 continúan sin assets Stitch propios.
- No se agregó identificación fiscal/personal porque Laravel no la administra en Customers.
- No se agregó DELETE de Customer, bulk actions, exportación, ordenamiento, deduplicación, merge ni historial no paginado.
- No se cambió backend, dominio, Form Requests, rutas, contratos, migraciones, PostgreSQL, Orders, Checkout ni storefront.
- La validación visual manual en navegadores desktop y viewport 390 px queda pendiente.
