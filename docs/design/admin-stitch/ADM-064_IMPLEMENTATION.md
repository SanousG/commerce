# FASE 26.32 — Tax · ADM-064

## Alcance

- **ADM-064:** Taxes (`/admin/taxes`), configuración inline de categorías tributarias y tasas efectivas.

ADM-065 inicia el módulo Fiscal y queda fuera de esta fase. No se modificaron backend, rutas, dominio, modelos, migraciones ni reglas de negocio. No se generaron diseños Stitch.

## Contrato funcional

Tax es tenant-wide e independiente de la Store activa para su administración. `TaxController` entrega las colecciones completas, ordenadas por nombre, sin parámetros de búsqueda, filtros ni paginador. Por ello la interfaz no simula búsqueda o paginación server-side inexistente.

La pantalla conserva únicamente las operaciones HTTP reales:

- crear y actualizar categorías con código `STANDARD`, `EXEMPT` o `UNAFFECTED`, nombre de hasta 120 caracteres y estado `ACTIVE`/`INACTIVE`;
- crear y actualizar tasas asociadas a una categoría del Tenant actual;
- preservar la tasa como string decimal entre 0 y 100 con hasta seis decimales;
- usar fechas `valid_from` y `valid_until` opcionales e inclusivas;
- impedir periodos activos superpuestos para una misma categoría;
- exigir tasa cero para categorías exoneradas o inafectas.

No existen endpoints de eliminación, archivado, operaciones masivas ni transiciones adicionales.

## Seguridad e integridad

- Sesión Laravel, CSRF, middleware `tenant`, Gates y Form Requests permanecen como autoridad.
- La vista requiere `taxes.view`; todas las mutaciones requieren `taxes.manage` tanto en ruta como en `authorize()`.
- Los recursos y referencias se resuelven mediante `TenantContext`; `tenant_id` y `store_id` están prohibidos en ambos requests.
- Los updates hacen lookup dentro de las relaciones del Tenant activo. Las categorías de otro Tenant no son elegibles para una tasa.
- `SaveTaxRate` conserva transacción, locks, validación de solapamiento y reglas de tasa cero.
- Vue no calcula impuestos ni decide tasas efectivas. `TaxCalculator` y `ResolveEffectiveTaxRate` mantienen precisión `BigDecimal`, fecha local de Store y snapshots históricos de Checkout/Order.

## UI, i18n y responsive

- Executive Precision B2B mediante `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `EmptyState` y `FieldError`.
- Categorías y tasas usan tablas compactas en desktop y cards/formularios apilados por debajo de sus breakpoints.
- A 390 px no se renderizan tablas ni anchos mínimos; controles y acciones ocupan el ancho disponible sin navegación inferior.
- El rol sin `taxes.manage` obtiene un modo explícito de solo lectura, controles deshabilitados y ninguna acción de guardado/alta.
- Los botones se bloquean durante procesamiento y los updates sin cambios permanecen deshabilitados.
- Errores de cada campo, solapamiento y tasa cero se presentan junto al formulario correspondiente.
- Todos los textos están bajo `taxes.*` en es-PE/en; códigos y estados tienen etiquetas localizadas.

## Rendimiento e integración

- Se reutilizan exactamente los props Inertia existentes; no se agregan requests, polling, dependencias ni consultas.
- La carga sigue siendo dos consultas tenant-scoped sin relaciones lazy ni N+1.
- Catalog, Cart, Checkout, Orders y Fiscal continúan consumiendo la configuración mediante servicios backend; no se altera ningún snapshot persistido.

## Omisiones deliberadas

- Sin búsqueda, filtros o paginación ficticios: el endpoint no los soporta.
- Sin delete, archive, bulk actions, exportación, constantes IGV hardcodeadas ni cálculo cliente.
- Sin cambios Fiscal/SUNAT, ADM-065–067, StoreContext, Supabase, migraciones, Stitch o servicios externos.
