# FASE 26.33 — Fiscal / SUNAT · ADM-065–067

## Alcance

- **ADM-065:** configuración fiscal (`/admin/settings/fiscal`).
- **ADM-066:** documentos fiscales (`/admin/fiscal-documents`).
- **ADM-067:** detalle de documento fiscal (`/admin/fiscal-documents/{fiscalDocument}`).

Las tres pantallas fueron rediseñadas e internacionalizadas con Executive Precision B2B sin modificar backend, dominio, rutas, contratos HTTP, migraciones ni integraciones. No se generaron diseños Stitch.

## Contrato funcional auditado

### Configuración

- El perfil emisor es **tenant-wide** y contiene identidad tributaria, razón social, nombre comercial, dirección fiscal, ubigeo y estado.
- Las series son propiedad de la **Store activa**. Laravel entrega tipo, serie, longitud numérica, siguiente correlativo readonly y estado.
- El alta y actualización conservan los endpoints existentes. `next_number`, `tenant_id` y `store_id` nunca se aceptan desde Vue.
- Solo puede existir una serie activa por Store y tipo de comprobante. Tipo, serie y padding quedan bloqueados visualmente después de iniciar la asignación; Laravel mantiene la validación definitiva.
- La pantalla no contiene credenciales SOL, certificados, claves, tokens ni selector de ambiente SUNAT.

### Documentos

- El índice consulta exclusivamente la Store activa y el Tenant autorizado, incluye Order con eager loading, ordena por fecha de emisión/creación descendente y pagina 20.
- La búsqueda real cubre número fiscal, nombre/documento del cliente y número de Order. Los filtros reales son tipo, estado y fechas inclusivas `from`/`until`.
- El detalle entrega snapshots de emisor, cliente, líneas, categoría/tasa, importes, Order, usuario generador, intentos SUNAT y notas de crédito ya relacionadas.
- Factura y Boleta se generan únicamente desde Order Detail mediante el endpoint existente. ADM-065–067 no añade otro endpoint de emisión.
- Las descargas existentes son XML firmado y CDR, solo cuando el artefacto está disponible y el usuario posee `fiscal_documents.manage`.

### SUNAT

- El único envío funcional es Factura mediante `POST .../submit-sunat`; usa rate limit y el pipeline síncrono existente.
- La UI ofrece el envío solo con `fiscal_documents.manage`, tipo `INVOICE` y estado `GENERATED` o `SUBMISSION_PENDING`; exige confirmación y evita doble interacción mientras procesa.
- Los estados de documento son `DRAFT`, `GENERATED`, `SUBMISSION_PENDING`, `SUBMITTED`, `ACCEPTED`, `REJECTED` y `CANCELLED`.
- Los estados de intento son `PENDING`, `PROCESSING`, `SENT`, `ACCEPTED`, `REJECTED`, `ERROR` y `UNKNOWN`.
- `ERROR` pre-delivery/remoto puede devolver el documento a `GENERATED`; `UNKNOWN` conserva `SUBMITTED` y bloquea reenvío ciego. Solo un CDR código 0 acepta un documento.
- Boleta individual está bloqueada. Daily Summary y `getStatus` existen solo como preparación técnica, sin flujo funcional.
- Producción permanece no disponible. Las pruebas usan `FakeSunatTransport` y `FakeFiscalArtifactStorage`; no se contacta SUNAT.

## Integración e integridad

- `Order` conserva el acuerdo comercial; `FiscalDocument` copia su snapshot tributario confirmado y no consulta Product/Tax al reconstruir historia.
- Se preservan precios tax-inclusive, moneda, escala decimal, bases imponible/exonerada/inafecta, tributo, descuentos y total general recibidos de Laravel. Vue solo formatea strings; no recalcula.
- La generación requiere `tax_snapshot_version = 1`, bloquea shipping no definido, reconcilia líneas/agregados y usa locks de Order/FiscalSeries con constraints de unicidad.
- La numeración y el correlativo siguen siendo server-authoritative. No se promete garantía legal de ausencia total de huecos.
- Los snapshots emitidos y sus líneas siguen siendo inmutables. No se regeneran documentos ni se modifican relaciones con Orders.
- La idempotencia por documento/hash, el intento único en vuelo, artefactos privados, transacciones, locks e historial de intentos permanecen intactos.

## Seguridad y aislamiento

- Sesión Laravel, CSRF, Gates, Form Requests, `TenantContext` y `StoreContext` permanecen como autoridad.
- ADM-065 requiere `fiscal_settings.view`; mutaciones requieren `fiscal_settings.manage`. ADM-066/067 requieren `fiscal_documents.view`; envío y artefactos requieren `fiscal_documents.manage`; generación desde Order requiere `fiscal_documents.issue`.
- Todos los documentos, intentos y descargas se re-resuelven por Tenant + Store; una referencia cross-tenant o cross-store devuelve not found.
- Los artefactos permanecen privados y se descargan con `Cache-Control: private, no-store`.
- No se exponen ni registran XML/CDR completos, certificados, contraseñas, tokens o claves privadas.

## UI, responsive e internacionalización

- Se reutilizan `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusBadge`, `Pagination`, `EmptyState` y `FieldError`.
- Desktop usa tablas compactas para series, índice y líneas; el detalle agrupa identidad, importes, transmisión e historial.
- Mobile 390 px reemplaza todas las tablas extensas por cards, apila formularios/descargas y aplica cortes de texto técnico, sin navegación inferior ni scroll horizontal.
- es-PE es el idioma predeterminado y `en` el alternativo. Títulos, campos, tipos, estados, acciones, confirmación, advertencias, errores y vacíos usan `fiscal.*`/`status.*`.
- Los códigos técnicos almacenados, RUC/documentos, series, categorías tributarias, response codes y nombres de artefactos no se traducen ni alteran.

## Rendimiento

- Se reutilizan exactamente los props y la paginación existentes; no se añadieron queries, polling, refresh, endpoints ni dependencias.
- El índice conserva eager loading de Order y 20 filas por página. El detalle conserva las relaciones precargadas existentes y no introduce N+1.
- Los bundles se generan con Vite y `public/build` permanece fuera del cambio.

Bundles Vite de producción registrados en esta fase:

| Pantalla | Chunk | Tamaño | Gzip |
|---|---|---:|---:|
| ADM-065 Settings | `Settings-CNSpnlBt.js` | 17.87 kB | 4.26 kB |
| ADM-066 Index | `Index-BcOKmvhd.js` | 7.25 kB | 2.45 kB |
| ADM-067 Detail | `Show-B2FPt2mD.js` | 15.99 kB | 4.38 kB |

## Funcionalidades no disponibles

- Producción SUNAT, certificación legal y OSE.
- Envío individual de Boleta o flujo funcional de Resumen Diario.
- Reconciliación automática por `getStatus` y reenvío automático de `UNKNOWN`/`SUBMITTED`.
- Emisión/transmisión funcional de Nota de Crédito, anulaciones, comunicación de baja o contingencia.
- PDF, detracciones, percepciones, retenciones, ISC avanzado, guías de remisión, documentos de proveedor y contabilidad.
- Edición o regeneración de documentos emitidos, eliminación, exportación masiva y acciones bulk.

## Validación

- Guard `pgsql.testing/commerce_testing`: **1 test, 4 assertions**, aprobado antes de pruebas destructivas y repetido después de reiniciar Docker.
- Fiscal/Tax/SUNAT + seguridad unitaria + auditoría fiscal: **64 tests, 412 assertions**, aprobados.
- Orders + Access/RBAC + Analytics fiscal + Notifications fiscal: **38 tests, 301 assertions**, aprobados.
- Suite Laravel completa: **636 tests, 4,429 assertions**, aprobados.
- `npm run test:i18n`: **2,340 mensajes Vue**, 45 etiquetas de navegación y 43 mensajes Laravel por locale, aprobado.
- `npm run typecheck`: aprobado.
- `npm run build`: aprobado; 828 módulos transformados. Vite informó únicamente que `fontaine` es opcional; no se añadió la dependencia.
- `git diff --check`: aprobado.

La validación de transporte SUNAT usó `FakeSunatTransport` y `FakeFiscalArtifactStorage`. No se ejecutó smoke Beta ni tráfico de producción.
