# ADM-017 — Create Category implementation

Fecha: 2026-10-25. Fase: 26.13.

## Referencias

- Proyecto Stitch: `projects/13874730587997489426`.
- Desktop: `e89785d0231f4e03bfb343f0b5faf6b9`.
- Mobile: `052cb6cd3d064e9f8b44a0b67d44af9d`.
- Design System: Executive Precision B2B.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

Stitch se utilizó como referencia visual. El controller, FormRequest, servicio
de aplicación y permisos Laravel permanecen como fuente de verdad.

## Contrato funcional verificado

### Ruta y autorización

- Formulario: `GET /admin/categories/create`.
- Creación: `POST /admin/categories`.
- Permiso requerido en ambos flujos: `categories.create`.
- Alcance: Tenant-wide mediante `TenantContext`; no usa StoreContext.
- Éxito: redirect a `admin.categories.index` con flash
  `Category created.`.

### Campos reales

| Campo | Contrato |
|---|---|
| `name` | Requerido, string, máximo 120; Laravel elimina whitespace exterior antes de validar |
| `parent_id` | Opcional/nullable, UUID existente en el Tenant activo |
| `position` | Opcional, integer entre 0 y 4,294,967,295; default 0 |

No existe `status` en el request de creación. `SaveCategory` crea siempre la
categoría como `ACTIVE`. Tampoco se acepta un slug: el servicio lo genera desde
el nombre y resuelve colisiones dentro del Tenant (`slug`, `slug-2`, etc.).

### Padres elegibles

El controller entrega `parents` con `id` y `name` únicamente:

- categorías del Tenant activo;
- cualquier nivel jerárquico existente;
- estado distinto de `ARCHIVED`, por lo que ACTIVE e INACTIVE son elegibles;
- orden alfabético por nombre.

La opción `No parent — root category` envía `null`. El FormRequest vuelve a
validar que cualquier UUID seleccionado pertenezca al Tenant activo. No se
carga un árbol ni se confía en valores del frontend.

## Diseño desktop

- Breadcrumb `Catalog / Categories / Create`.
- `PageHeader` con título, contexto `All company` y alcance Tenant-wide.
- Aviso informativo que explica el estado ACTIVE y slug controlados por
  servidor sin convertirlos en campos editables.
- Card border-first dividida en `Identity` y `Placement`.
- Labels laterales en desktop y controles en una columna de contenido.
- Contador 0/120, ayudas breves, errores junto a cada campo y tokens oficiales.
- Footer único con Cancel y Create category; no se duplican acciones.

## Diseño mobile 390

- Las secciones y campos colapsan a una columna.
- Name, Parent y Position ocupan todo el ancho disponible.
- Los errores permanecen debajo de su control.
- Cancel y Create category son botones táctiles de ancho completo; la acción
  principal queda visualmente primero por el orden responsive del footer.
- Sin scroll horizontal ni barra inferior; se reutiliza el drawer de
  `AdminLayout`.

## Estado del formulario y accesibilidad

- Se conserva `useForm`, por lo que los valores y errores Inertia permanecen
  después de una validación fallida.
- El botón submit queda deshabilitado mientras `form.processing` y cambia a
  `Creating…`, evitando dobles envíos desde la interfaz.
- Los controles usan `label for`, `aria-invalid`, `aria-describedby`, límites
  HTML equivalentes y `FieldError` identificado.
- Position usa `v-model.number`, `step=1`, mínimo 0 y el máximo real.
- Cuando no existen padres elegibles, el selector conserva la opción root y se
  muestra un estado vacío contextual.

## Componentes reutilizados

- `AdminLayout`.
- `Breadcrumbs`.
- `PageHeader`.
- `FieldError`.

`StatusNotice` no se monta en Create porque un éxito redirige al índice, donde
ADM-016 ya muestra el flash compartido. No se extrajo un componente de
formulario antes de implementar ADM-018: Create y Edit comparten campos, pero
Edit incorpora status y reglas de exclusión de descendientes adicionales.

## Diferencias frente a Stitch

- No se muestra preview ni input de slug porque Create no recibe ese dato.
- No se muestran códigos de categoría junto a los padres porque el prop solo
  incluye `id` y `name`.
- No se presenta un selector de status; la creación fuerza ACTIVE.
- Se omitió el aviso de ciclos de Stitch: una categoría todavía inexistente no
  puede seleccionarse a sí misma ni tener descendientes. La pertenencia del
  padre al Tenant sí permanece validada por Laravel.
- No se agregaron SEO, imágenes, categorías primarias, drag-and-drop,
  subcategorías automáticas ni acciones duplicadas.

## Rendimiento

- Props y consulta backend: sin cambios.
- Se reutiliza el array Inertia actual de categorías no archivadas; no existen
  requests adicionales ni carga de árbol.
- Watchers y dependencias nuevas: ninguno.
- Bundle anterior de Create Category: 2.14 kB, 1.00 kB gzip.
- Bundle final de Create Category: 6.92 kB, 2.63 kB gzip.
- El aumento corresponde a Breadcrumbs, PageHeader, ayudas, estados accesibles
  y la estructura responsive.

## Pruebas y validación

- Guard de conexión: 1 test, 4 assertions; confirma
  `pgsql.testing/commerce_testing`.
- `CategoryTest`: 11 tests, 108 assertions.
- Suite Catalog: 43 tests, 292 assertions.
- `npm run typecheck`: correcto.
- `npm run build`: correcto; solo permanece el aviso no bloqueante del paquete
  opcional `fontaine`, no instalado.
- Pint sobre `CategoryTest.php`: correcto.
- `git diff --check`: correcto.

La cobertura ejecutada verifica creación root con defaults, nombre requerido y
trim, slug generado, ACTIVE forzado, posición default y explícita, creación de
subcategoría, parents ACTIVE/INACTIVE, exclusión de archived/foreign parents,
rechazo cross-tenant, permiso create, redirect con flash y aparición posterior
en Categories Index.

El repositorio no tiene pruebas de componentes Vue. El bloqueo visual de doble
submit, foco, layout responsive y persistencia visual de controles después de
errores requieren revisión manual, aunque el binding `form.processing` y el
flujo `useForm` sí pasaron typecheck/build.

## Archivos de FASE 26.13

- `resources/js/pages/admin/categories/Create.vue`.
- `tests/Feature/Catalog/CategoryTest.php`.
- `docs/design/admin-stitch/ADM-017_IMPLEMENTATION.md`.
- `docs/design/admin-stitch/STITCH_DESIGN_COVERAGE.md`.

## Pendientes

No existe navegador autenticado disponible en el harness. Queda pendiente la
revisión visual manual a 1280, 1440 y 390 px, incluyendo:

- parent vacío, lista extensa y nombres largos;
- errores simultáneos en los tres campos;
- submit processing y prevención efectiva de doble click;
- persistencia visual tras respuesta 422;
- foco, teclado y lector de pantalla;
- redirect, flash y nueva fila en ADM-016.

ADM-018 Edit Category quedó deliberadamente fuera de alcance y no fue
rediseñada.
