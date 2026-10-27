# FASE 26.38 — Stores · ADM-009–012

## Alcance

- **ADM-009:** Stores (`/admin/stores`).
- **ADM-010:** Create store (`/admin/stores/create`).
- **ADM-011:** Edit store (`/admin/stores/{store}/edit`).
- **ADM-012:** Store settings (`/admin/stores/{store}/settings`).

Las cuatro pantallas fueron rediseñadas e internacionalizadas con Executive Precision B2B sin modificar backend, dominio, rutas, contratos HTTP, consultas, migraciones ni dependencias. No se utilizó ni generó Stitch.

## Contrato funcional auditado

- El índice lista, por nombre, todas las Stores del Tenant activo entregadas por Laravel. Expone nombre, slug, estado, moneda, zona horaria, locale y la selección de sesión calculada por el servidor.
- Las acciones reales son crear, seleccionar una Store activa, editar identidad/ciclo de vida y abrir settings. No existen búsqueda, filtros, paginación, eliminación ni endpoint de archivado.
- Crear acepta únicamente `name`, `currency`, `timezone` y `locale`; la Store y su fila `StoreSettings` se crean atómicamente como activas. Laravel selecciona automáticamente solo la primera Store.
- La UI de creación permite cualquier zona horaria IANA válida y cualquier locale `ll_CC` aceptado por Laravel; los valores frecuentes se ofrecen como sugerencias, sin restringir el contrato.
- Editar acepta únicamente nombre, estado `ACTIVE`/`INACTIVE`, moneda, zona horaria y locale. El slug no cambia. Desactivar la Store seleccionada limpia `current_store_id`.
- Settings conserva identidad visible, descripción, logo/favicon de Media, dos colores, contacto, dirección peruana, enlaces sociales oficiales, moneda, zona horaria y locale.
- Settings guarda versiones `store_updated_at` y `settings_updated_at`; una versión obsoleta devuelve HTTP 409 y exige recargar antes de reintentar.
- No se añadieron flags de checkout, notas, autoconfirmación, entrega, shipping, fiscal, pagos ni otras configuraciones no soportadas.

## Contexto, RBAC y aislamiento

- `TenantContext` y la membresía activa son autoritativos. Vue no envía `tenant_id` ni adopta IDs de contexto del cliente.
- Todas las resoluciones de Store se limitan a la relación `Tenant::stores()`. Una Store ajena no puede listarse, seleccionarse, leerse ni actualizarse y devuelve 404.
- `stores.view` permite índice y Store Settings; `stores.create`, `stores.select` y `stores.update` habilitan exclusivamente sus operaciones correspondientes.
- ADM-012 ofrece modo readonly real para miembros con `stores.view` sin `stores.update`. ADM-011 requiere `stores.update` desde el controller y no simula readonly sobre una ruta no autorizada.
- Solo una Store `ACTIVE` puede guardarse en sesión. `ResolveStore` elimina selecciones ajenas/inactivas, autoselecciona una única Store activa y solicita selección cuando hay varias.
- Cambiar de Tenant continúa limpiando la Store anterior. Cambiar de Store no altera membresía ni datos tenant-wide.

## Configuraciones e integraciones

- Moneda, zona horaria, locale, Listings, pagos, series fiscales, órdenes y demás operaciones Store-scoped siguen separados de datos tenant-wide como catálogo, membresías, listas de precios e inventario.
- Cambiar moneda no convierte precios ni reescribe snapshots de Checkout, Orders, Payments, inventario o fiscal.
- La lista de precios predeterminada continúa administrándose desde Listings/Pricing; no se duplicó en Store Settings.
- Logo y favicon aceptan solo `MediaAsset` de imagen, listo y del mismo Tenant. `MediaPicker` conserva búsqueda/paginación, permiso de subida y el límite `media.max_image_mb` entregado por Laravel.
- La configuración de pagos y entrega permanece en sus módulos existentes. Fiscal issuer sigue siendo tenant-wide y las series siguen siendo Store-scoped.

## UI, accesibilidad, responsive e i18n

- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `EmptyState`, `StatusBadge`, `FieldError`, `MediaPicker` y `MediaThumbnail`.
- Las selecciones y formularios evitan doble envío; la desactivación requiere confirmación localizada y advierte especialmente cuando afecta a la Store actual.
- Los campos incluyen labels, ayudas, estados `aria-invalid`, asociación de errores y mensajes de procesamiento. Settings anuncia readonly, éxito y protección de concurrencia; los flash de guardado se presentan localizados.
- Desktop usa cards y formularios compactos. Mobile 390 px apila controles y acciones, quiebra slugs/zonas largas y evita tablas o navegación inferior.
- `es-PE` permanece predeterminado y `en` alternativo. Títulos, estados, campos, acciones, vacíos, confirmaciones, ayudas y mensajes accesibles usan vue-i18n.

## Rendimiento y límites deliberados

- Se conservan las consultas y props originales; no se añadieron polling, precarga, requests automáticos ni consultas frontend salvo la carga existente de `MediaPicker` al abrirlo.
- La lista continúa sin paginación porque el backend entrega la colección completa. No se inventó paginación cliente o servidor.
- No existen archivado/eliminación de Store, Store duplicada, dominio storefront, multi-moneda transaccional, conversión FX, configuración de courier/shipping ni publicación de archivos maestros.

## Cobertura

- Cobertura Vue de esta fase: **4/4** (`ADM-009`–`ADM-012`).
- Cobertura Vue administrativa acumulada: **69/69** (`ADM-001`–`ADM-069`).
- Cobertura Stitch real: **9/69**; ADM-009–012 no fueron generadas en Stitch.
- La aprobación visual manual acumulada permanece separada de la implementación y las pruebas automatizadas.

## Validación

Los comandos y resultados de guard, suites Laravel, i18n, typecheck, build y `git diff --check` se registran en el informe final único de FASE 26.38.
