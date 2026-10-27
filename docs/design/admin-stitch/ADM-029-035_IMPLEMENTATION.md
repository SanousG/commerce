# ADM-029–035 — Inventory/Costing: implementación FASE 26.22

## Alcance

Rediseño e internacionalización completa de las pantallas administrativas reales de Inventory/Costing, sin modificar backend, endpoints, modelos, migraciones ni reglas comerciales:

| ID | Pantalla | Ruta |
|---|---|---|
| ADM-029 | Inventory · Stock | `/admin/inventory` |
| ADM-030 | Inventory · Variant stock | `/admin/inventory/variants/{variant}` |
| ADM-031 | Inventory · Transfer stock | `/admin/inventory/transfers/create` |
| ADM-032 | Inventory · Locations | `/admin/inventory/locations` |
| ADM-033 | Inventory · Create location | `/admin/inventory/locations/create` |
| ADM-034 | Inventory · Edit location | `/admin/inventory/locations/{location}/edit` |
| ADM-035 | Inventory · Valuation | `/admin/inventory/valuation` |

No existen referencias Stitch para ADM-029–035 y no se generaron diseños. La implementación aplica Executive Precision B2B directamente al contrato Laravel auditado.

## Revisión previa de PurchaseReceiptCostingTest

- El fallo registrado en FASE 26.21 no representa una regresión de Costing ni contaminación confirmada entre pruebas.
- `occurred_at` está definido como `timestampTz` sin precisión fraccional. Dos receipts creados dentro del mismo segundo pueden empatar y la consulta del test usa únicamente `orderBy('occurred_at')`; el orden de esas dos filas no queda garantizado por PostgreSQL.
- Los importes, el promedio ponderado y la valoración final observados eran correctos. El método fallido pasó tres ejecuciones aisladas consecutivas, con 3 assertions en cada ejecución.
- PHPUnit 12.5.34 no admite `--repeat`; las repeticiones se ejecutaron como invocaciones aisladas independientes.
- No se cambió Costing ni el test por una hipótesis fuera de alcance. En la validación final, PurchaseReceiptCostingTest pasó dentro del bloque Inventory/Costing y la suite Laravel completa pasó con 636 tests.

## Contrato funcional verificado

- **Scope:** Inventory y Valuation son tenant-wide mediante `TenantContext`; no dependen de `StoreContext`. Las consultas, route-model lookups y mutaciones permanecen restringidas al Tenant activo.
- **RBAC:** `inventory.view` permite stock, detalle y locations; `inventory.adjust` protege ajustes, transferencias y administración de locations; `inventory.cost.view` protege valoración y payloads de costo; `inventory.cost.manage` protege inicialización.
- **Stock:** `inventory_levels` conserva autoridad sobre `on_hand` y `reserved`; disponible es `on_hand - reserved`. Index busca Product/SKU y pagina 20; detalle pagina 50 movimientos.
- **Ajustes:** cantidad entera distinta de cero, motivo obligatorio, Location activa y stock posterior válido. Laravel escribe nivel y movimiento de forma atómica.
- **Transferencias:** dos Locations activas distintas, variantes tenant-scoped y únicas, cantidades enteras positivas y disponibilidad suficiente. La transferencia queda `COMPLETED` y todas sus líneas/movimientos comparten una transacción y correlación.
- **Locations:** estados `ACTIVE`, `INACTIVE` y `ARCHIVED`; no existe DELETE. Solo una Location activa puede ser default y la default debe permanecer activa. Create siempre genera una Location no-default; Edit requiere efectivamente `inventory.adjust`.
- **Costing:** promedio ponderado móvil por Tenant/Location/Variant, costo interno de escala 6 y valores de escala 4 con `BigDecimal`, sin floats ni conversión FX. La inicialización solo aplica una vez a stock positivo `UNINITIALIZED` y no cambia cantidad.
- **Ausencias deliberadas:** no se inventaron valued transfers/adjustments, FIFO/LIFO, contabilidad, landed cost, FX, acciones masivas, exportación, eliminación física ni requests auxiliares.

## Implementación por pantalla

### ADM-029 — Stock

- Tabla desktop compacta y cards mobile con Product/SKU, on hand, reserved, available y cantidad de Locations con stock.
- Búsqueda backend, paginación server-side de 20 y vacíos diferenciados.
- Accesos a transferencias, Locations y Valuation condicionados por capacidades reales.

### ADM-030 — Variant stock

- Niveles por Location con disponibilidad y costos omitidos completamente sin `inventory.cost.view`.
- Inicialización inline solo con `inventory.cost.manage`, estado `UNINITIALIZED` y stock positivo; moneda ISO y escala 6 preservadas.
- Ajuste autorizado con Location activa, entero signed y motivo; errores localizados y doble envío bloqueado.
- Kardex paginado con tipos de movimiento traducidos, cantidad signed, fecha UTC, Location y motivo.

### ADM-031 — Transfer stock

- Selector de origen/destino entre Locations activas y líneas dinámicas de variantes activas.
- Restricciones reales explicadas: ubicación distinta, variante única, cantidad positiva, disponibilidad y atomicidad.
- Estado no disponible cuando faltan dos Locations o una variante; sin simular operaciones parciales.

### ADM-032–034 — Locations

- Index con default destacado, estados reales y acciones Edit/Make default según RBAC y estado.
- Create/Edit conservan exclusivamente name, code y status; normalización uppercase y unicidad tenant-wide quedan en Laravel.
- Protección visible de Location default, transición de estados soportada y preservación de stock/historial; no se presenta DELETE.

### ADM-035 — Valuation

- Totales por moneda separados, sin FX y calculados únicamente con costos inicializados.
- Tabla desktop y cards mobile por Product/Variant/Location con on hand, status, average unit cost e inventory value.
- Búsqueda backend por Product/SKU/Location, paginación server-side de 30 y advertencia para filas no inicializadas visibles.

## Diseño, i18n y accesibilidad

- Reutiliza `AdminLayout`, `Breadcrumbs`, `PageHeader`, `Pagination`, `StatusBadge`, `StatusNotice`, `EmptyState` y `FieldError` según cada pantalla.
- Desktop mantiene densidad B2B, tablas compactas, superficies blancas y números tabulares. Mobile 390 px convierte tablas en cards, apila formularios y conserva acciones accesibles sin scroll horizontal intencional.
- Catálogo `inventory.*` completo y paritario para es-PE/en: títulos, ayudas, estados de costo, tipos de movimiento, acciones, vacíos, errores, confirmaciones y textos accesibles.
- Labels asociados, required real, `aria-invalid`, límites HTML coherentes y estados processing para evitar doble envío. SKU, códigos y monedas conservan representación técnica.

## Seguridad, aislamiento y rendimiento

- Auth de sesión, CSRF, Gates/FormRequests, `InventoryAccess`, transacciones, locks y queries tenant-scoped siguen siendo autoritativos.
- No se acepta `tenant_id` ni `store_id` desde frontend. Los recursos cruzados devuelven 404 o 403 según el contrato existente.
- Se conservan paginaciones 20/50/30 y agregados server-side. No se añadieron consultas, endpoints, dependencias ni N+1.
- Bundles de producción: Stock **7.13 kB / 2.37 kB gzip**; Variant **12.13 kB / 3.75 kB gzip**; Transfer **8.45 kB / 2.53 kB gzip**; Locations Index **6.19 kB / 2.02 kB gzip**; Create **4.90 kB / 1.68 kB gzip**; Edit **5.73 kB / 1.93 kB gzip**; Valuation **8.44 kB / 2.64 kB gzip**.

## Validación

- Guard aislado `pgsql.testing / commerce_testing`: 1 test, 4 assertions, PASS.
- Inventory + Costing: 46 tests, 271 assertions, PASS.
- Procurement + Fulfillment + Returns + Access + Tenancy: 109 tests, 782 assertions, PASS.
- Suite Laravel completa: 636 tests, 4429 assertions, PASS; incluye PurchaseReceiptCostingTest.
- `npm run test:i18n`: 956 mensajes Vue, 45 labels de sidebar y 43 mensajes Laravel por locale, PASS.
- `npm run typecheck` y `npm run build`: PASS. Build conserva únicamente el warning preexistente de `fontaine` opcional.
- `git diff --check`: PASS.

## Pendientes y omisiones

- Pendiente validación visual manual en navegador desktop y viewport 390 px.
- No se generó Stitch, no se modificó backend y no se iniciaron Customers ADM-036–039.
- No se añadieron tests frontend porque el repositorio no tiene runner de componentes configurado; contratos HTTP, RBAC, dominio e aislamiento quedan cubiertos por la suite existente.
- El empate de timestamps del test de Costing queda documentado como fragilidad preexistente; no se cambió por estar fuera del alcance funcional y pasar toda la validación final.
