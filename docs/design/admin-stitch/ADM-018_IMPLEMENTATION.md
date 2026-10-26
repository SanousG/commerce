# ADM-018 — Edit Category implementation

Fecha: 2026-10-25. Fase: 26.14.

## Referencias

- Proyecto Stitch: `projects/13874730587997489426`.
- Desktop: `1d520fc67a054eb1b52c22a3701b651c`.
- Mobile: `53b14fe0f55440f2b7a9cf1104c82521`.
- Design System: Executive Precision B2B.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

Stitch se utilizó como referencia visual. Los props, FormRequest, permisos y
servicio Laravel permanecen como contrato funcional autoritativo.

## Contrato funcional verificado

### Rutas, alcance y autorización

- Formulario: `GET /admin/categories/{category}/edit`, autorizado con
  `categories.view`.
- Actualización: `PUT /admin/categories/{category}`, autorizado con
  `categories.update`.
- Scope: `TenantContext`; Category se resuelve desde la relación del Tenant
  activo y no utiliza StoreContext.
- Éxito: `back()` con flash `Category updated.`.
- Un usuario con view pero sin update puede abrir la ruta; la implementación
  muestra todos los datos en modo read-only y omite Save changes.

### Campos editables

| Campo | Contrato |
|---|---|
| `name` | Requerido, string, máximo 120; se aplica trim antes de validar |
| `status` | Requerido; enum `ACTIVE`, `INACTIVE`, `ARCHIVED` en el request |
| `parent_id` | Opcional/nullable, UUID del Tenant activo, distinto de la propia Category |
| `position` | Opcional, integer entre 0 y 4,294,967,295 |

`slug` llega en los props y se presenta read-only. `SaveCategory::update` no lo
incluye en el update, por lo que cambiar `name` nunca lo regenera.

La operación bloquea Tenant y Category dentro de una transacción y actualiza
solo name, status, parent_id y position. No sincroniza ni elimina asociaciones
con Products.

## Jerarquía

El controller reutiliza la lista Tenant-scoped de parents y, para Edit:

- excluye categorías `ARCHIVED`;
- excluye la propia Category;
- recorre `parent_id` y excluye sus descendientes;
- incluye categorías ACTIVE e INACTIVE restantes;
- ordena alfabéticamente;
- serializa únicamente `id` y `name`.

El FormRequest vuelve a rechazar self y referencias cross-tenant. Dentro de la
transacción, `assertAcyclicParent` recorre la cadena completa de ancestros y
rechaza también un descendiente enviado manipulando el request.

Después de `reject`, Laravel puede conservar claves no contiguas y serializar
`parents` como objeto JSON. La UI normaliza localmente con `Object.values`; no
se modificó controller ni payload. Esto evita asumir que siempre llega un array
denso y no genera requests adicionales.

Si el padre actual dejó de estar entre las opciones —por ejemplo, porque fue
archivado posteriormente— se conserva su UUID con la opción
`Current parent — unavailable for new selection`. Así guardar otro campo no
cambia accidentalmente la relación; el usuario aún puede mover la Category a
root o a otro parent elegible.

## Estados y Archive

El contrato backend de update acepta los tres enums y no bloquea la edición de
una Category archivada. Una Category archivada puede conservar ARCHIVED o
reactivarse como ACTIVE/INACTIVE mediante el update existente.

Archive también dispone de una operación independiente:

- `PATCH /admin/categories/{category}/archive`;
- permiso `categories.archive`;
- redirect a Categories Index.

Para no permitir archivar mediante el permiso distinto `categories.update`, el
selector de una Category ACTIVE/INACTIVE ofrece solo ACTIVE e INACTIVE. ARCHIVED
aparece únicamente cuando ya es el estado actual, para preservar o reactivar el
recurso. ADM-018 no duplica el botón Archive de ADM-016. Esta separación es una
decisión frontend de RBAC; no cambia las validaciones backend existentes.

## Diseño desktop

- Breadcrumb `Catalog / Categories / {Category} / Edit`.
- `PageHeader` con contexto `All company` y regreso a Categories.
- Metadata compacta con StatusBadge, nombre y slug técnico.
- Flash de actualización mediante `StatusNotice`.
- Aviso de regla acíclica sin enumerar datos no presentes en los props.
- Card única, correspondiente al único endpoint, dividida en `Identity` y
  `Placement`.
- Name y Status editables; slug read-only; Parent y Position agrupados.
- Footer único con Cancel y Save changes.

## Diseño mobile 390

- Secciones y campos en una columna.
- Inputs y selectores a ancho completo, con errores inmediatamente debajo.
- Metadata y badges usan wrap sin overflow horizontal.
- Cancel y Save changes usan targets táctiles de ancho completo.
- Se conserva el drawer de `AdminLayout`; no se añadió barra inferior.

## Formulario y accesibilidad

- `useForm` precarga exactamente los props y conserva valores/errores después
  de una respuesta 422.
- `form.processing` deshabilita el único submit y muestra `Saving…`.
- `preserveScroll` evita saltos después del update/back.
- Labels, `aria-invalid`, `aria-describedby`, contadores y `FieldError` se
  asocian al campo correspondiente.
- Position usa `v-model.number`, mínimo, máximo y step reales.
- El fieldset completo se deshabilita sin `categories.update`; el enlace de
  retorno permanece disponible.

## Componentes reutilizados

- `AdminLayout`.
- `Breadcrumbs`.
- `PageHeader`.
- `StatusBadge`.
- `StatusNotice`.
- `FieldError`.

No se extrajo un componente CategoryForm. Create no acepta status ni slug y
Edit requiere read-only mode, exclusión de descendientes, estado archivado y
preservación del parent actual; una abstracción ocultaría diferencias reales.

## Diferencias frente a Stitch

- El slug se muestra con el valor real, pero nunca se edita.
- No se muestran códigos, niveles o listas nominales de descendientes porque
  los props de parents solo contienen `id` y `name` de opciones elegibles.
- No se renderizan self/descendants como opciones disabled: Laravel ya los
  excluye por completo.
- El aviso de ciclos describe la regla sin inventar nombres de nodos.
- ARCHIVED no se ofrece como transición ordinaria del formulario; Archive
  conserva su endpoint y permiso independientes.
- No se agregaron SEO, imágenes, drag-and-drop, movimientos masivos ni acciones
  duplicadas.

## Rendimiento

- Query y props backend: sin cambios.
- No se carga un árbol adicional ni se hacen requests secundarios.
- Computed locales: permisos, normalización de parents, estados visibles y
  detección del parent actual; no existen watchers.
- Bundle anterior de Edit Category: 2.29 kB, 1.05 kB gzip.
- Bundle final de Edit Category: 10.68 kB, 3.56 kB gzip.
- El aumento corresponde a componentes compartidos, modo read-only, estados,
  accesibilidad, normalización defensiva y layout responsive.
- Dependencias nuevas: ninguna.

## Pruebas y validación

- Guard de conexión: 1 test, 4 assertions; confirma
  `pgsql.testing/commerce_testing`.
- `CategoryTest`: 14 tests, 143 assertions.
- Suite Catalog: 46 tests, 327 assertions.
- `npm run typecheck`: correcto.
- `npm run build`: correcto; permanece solo el aviso no bloqueante del paquete
  opcional `fontaine`, no instalado.
- Pint: correcto mediante `php vendor/bin/pint`.
- `git diff --check`: correcto.

La cobertura ejecutada verifica edición de name, slug inmutable, cambio de
parent, position, ACTIVE/INACTIVE, reactivación de ARCHIVED, redirect/back con
flash, exclusión de self/descendants/archived/foreign parents, rechazo de self,
descendientes y referencias cross-tenant, permiso categories.update y
preservación de asociaciones Product.

La primera ejecución del nuevo test de props detectó las claves no contiguas de
`parents`; la UI fue corregida con normalización local y las ejecuciones finales
pasaron. El repositorio no tiene tests de componentes Vue, por lo que read-only
visual, doble submit y responsive requieren revisión manual.

## Archivos de FASE 26.14

- `resources/js/pages/admin/categories/Edit.vue`.
- `tests/Feature/Catalog/CategoryTest.php`.
- `docs/design/admin-stitch/ADM-018_IMPLEMENTATION.md`.
- `docs/design/admin-stitch/STITCH_DESIGN_COVERAGE.md`.

## Pendientes

No existe navegador autenticado disponible en el harness. Queda pendiente la
revisión manual a 1280, 1440 y 390 px, incluyendo:

- usuario con view pero sin update;
- Category ACTIVE, INACTIVE y ARCHIVED;
- parent actual archivado y lista sin alternativas;
- nombres/slugs largos y errores simultáneos;
- processing, foco, teclado y persistencia visual tras 422;
- redirect/back y flash en la misma pantalla.

ADM-019 no fue modificada ni iniciada en esta fase.
