# Cobertura Laravel → Stitch

## Estado real verificado

- Verificación MCP más reciente: **2026-10-25**.
- Transporte OpenCode: **`stitch connected`**; las operaciones reales `list_projects`, `get_project`, `list_screens`, `get_screen` y `list_design_systems` respondieron correctamente.
- Proyecto seleccionado: **Commerce SaaS Admin Dashboard**.
- ID: `13874730587997489426` (`projects/13874730587997489426`).
- URL estable del proyecto: [abrir en Google Stitch][project].
- Tipo: `TEXT_TO_UI_PRO`; dispositivo base: `DESKTOP`; visibilidad: `PRIVATE`; rol MCP: `OWNER`.
- Pantallas de inventario representadas realmente: **9/69** (`ADM-001`, `ADM-013`–`ADM-020`).
- Artefactos de pantalla recuperables por ID conocido: **47**: los 45 anteriores más desktop/mobile de `ADM-020`. Una operación de `ADM-021` devolvió 503 y cambió el `updateTime`, pero no entregó ID ni apareció listada/incorporada; no se contabiliza como recurso recuperable.
- Assets de Design System encontrados: **2**. No cuentan como pantallas Laravel.
- Pantallas con prompt y plantilla asignada: **69/69 admin + 6 auth**.

`ADM-001` fue aprobado e implementado como piloto visual. `ADM-013` fue implementado en Vue durante FASE 26.8 y queda pendiente de validación visual manual del resultado. `ADM-014`–`ADM-018` fueron implementados en Vue durante FASE 26.10–26.14; requieren validación visual manual del resultado. El usuario aprobó visualmente la referencia Stitch `ADM-015` desktop, aunque su HTML recuperable mantiene la observación de sincronización; la referencia mobile no está aprobada. La implementación de `ADM-016` descartó deliberadamente el árbol de Stitch y usa la lista plana paginada del contrato Laravel. `ADM-017` conserva exclusivamente name, parent y position; slug y estado permanecen controlados por servidor. `ADM-018` conserva slug read-only, jerarquía acíclica, read-only por RBAC y Archive separado del update visual. `ADM-019` fue rediseñada e internacionalizada en FASE 26.16 con los contratos Laravel preservados y queda pendiente de validación visual manual; `ADM-020` fue generada como candidata desktop/mobile. La auditoría técnica FASE 26.17 de `ADM-001` y `ADM-013`–`ADM-019` está documentada en `FASE_26_17_ADMIN_AUDIT.md`: los checks automatizados pasan, pero la aprobación visual y brechas de i18n Laravel permanecen pendientes.

## Proyecto y artefactos recuperados

El MCP no devuelve una URL de editor específica para cada screen. Devuelve el identificador canónico, la URL estable del proyecto y enlaces descargables de captura/HTML. La navegación se realiza mediante el proyecto y la identificación inequívoca se mantiene con los IDs de cada variante.

| Visibilidad | Diseño | ID real | Tamaño informado | Enlaces reales devueltos |
|---|---|---|---|---|
| Visible; referencia previa no canónica | `ADM-001 — Administrative Dashboard (Corporate Slate)` | `feb91ca011ef43a98fd49587cf969446` | instancia 1280 × 2313; captura 2560 × 4626; `DESKTOP` | [Proyecto][project] · [Captura][desktop-current-shot] · [HTML][desktop-current-html] |
| Oculto; iteración anterior | `ADM-001 — Administrative Dashboard` | `11e60c8f94fb4fe2a931f47b7c5e0fa9` | instancia 1280 × 2258; captura 2560 × 4548; `DESKTOP` | [Proyecto][project] · [Captura][desktop-old-shot] · [HTML][desktop-old-html] |
| Oculto; variante responsive | `ADM-001 — Administrative Dashboard (Mobile)` | `0f4751740d69443293f1f354b62cff4d` | instancia 390 × 2925; captura 780 × 5850; `MOBILE` | [Proyecto][project] · [Captura][mobile-shot] · [HTML][mobile-html] |
| Oculto; estado de navegación | `ADM-001 — Mobile Navigation Drawer State` | `d2925ad51cab4affb0ae3200882d6a8c` | captura 780 × 1768; `MOBILE`; instancia redimensionada en canvas | [Proyecto][project] · [Captura][drawer-shot] · [HTML][drawer-html] |

### Refinamiento FASE 26.6.1

`edit_screens` no sobrescribió los IDs seleccionados: devolvió pantallas nuevas dentro del mismo recurso de proyecto. Se conservan todas las referencias anteriores. `generate_variants` se usó una vez para la propuesta tablet.

| Uso | Diseño | ID real nuevo | Metadatos devueltos | Enlaces reales | Estado para aprobación |
|---|---|---|---|---|---|
| Desktop canónico | `ADM-001 — Administrative Dashboard (Refined 1440 — Canonical)` | `a2f3fa1208bb46a290829db40ac13984` | recurso `DESKTOP`, captura 2560 × 5116; instancia 1280 × 2558 | [Captura][desktop-refined-shot] · [HTML][desktop-refined-html] | **Listado, incorporado y visible en canvas**. El ancho lógico comprobado es 1280, no 1440. |
| Desktop intermedio | `ADM-001 — Administrative Dashboard (Refined 1440)` | `8113256174604f419f9b40d438b35e62` | recurso `DESKTOP`, captura 2560 × 4598; instancia 1280 × 2299 | [Captura][desktop-refined-intermediate-shot] · [HTML][desktop-refined-intermediate-html] | **Listado, incorporado y visible**; debe ocultarse por no ser canónico. |
| Mobile canónico | `ADM-001 — Administrative Dashboard (Refined Mobile 390)` | `0eb6f9eccaed46bfb46746ae0d84e83a` | recurso `MOBILE`, captura 780 × 9434; instancia 390 × 4716 | [Captura][mobile-refined-shot] · [HTML][mobile-refined-html] | **Listado, incorporado y visible en canvas**; viewport lógico 390 comprobado. |
| Drawer mobile canónico | `ADM-001 — Mobile Navigation Drawer (Refined 390)` | `acda0c0a7de049a59b8cec214b0e8f21` | recurso `MOBILE`, captura 780 × 1768; instancia 390 × 844 | [Captura][drawer-refined-shot] · [HTML][drawer-refined-html] | **Listado, incorporado y visible en canvas**; viewport lógico 390 comprobado. |
| Tablet propuesta | `ADM-001 — Administrative Dashboard (Tablet 768 Reference)` | `454e4f18f0e442a48b76c9019cf0d2d1` | recurso `DESKTOP`, captura 2560 × 4524; instancia 1280 × 2262 | [Captura][tablet-proposal-shot] · [HTML][tablet-proposal-html] | **Listada, incorporada y visible**, pero no es tablet 768 validada: metadatos y ancho lógico son desktop/1280. |

### Verificación de sincronización — FASE 26.6.2

Verificación repetida después de abrir el proyecto en navegador: `get_project` actualizado a `2026-10-19T01:20:30.252939Z`.

| ID solicitado | `get_screen` | `list_screens` | `screenInstances` | Visible en canvas |
|---|---|---|---|---|
| `a2f3fa1208bb46a290829db40ac13984` · Desktop canónico | Sí | Sí | Sí, 1280 × 2558 | **Sí** (`hidden` ausente/false) |
| `0eb6f9eccaed46bfb46746ae0d84e83a` · Mobile | Sí | Sí | Sí, 390 × 4716 | **Sí** (`hidden` ausente/false) |
| `acda0c0a7de049a59b8cec214b0e8f21` · Drawer | Sí | Sí | Sí, 390 × 844 | **Sí** (`hidden` ausente/false) |
| `454e4f18f0e442a48b76c9019cf0d2d1` · Tablet propuesta | Sí | Sí | Sí, 1280 × 2262 | **Sí** (`hidden` ausente/false) |

Distinción comprobada:

- **Recurso recuperable:** `get_screen` devuelve título, dispositivo, captura y HTML para los cuatro IDs.
- **Pantalla listada:** `list_screens` incluye los cuatro IDs.
- **Pantalla incorporada:** `get_project.screenInstances` referencia los cuatro recursos.
- **Pantalla visible:** ninguno de esos cuatro `screenInstances` tiene `hidden: true`.

También están visibles `feb91ca011ef43a98fd49587cf969446` (Corporate Slate anterior) y `8113256174604f419f9b40d438b35e62` (desktop intermedio). Las variantes antiguas `11e60c8f94fb4fe2a931f47b7c5e0fa9`, `0f4751740d69443293f1f354b62cff4d` y `d2925ad51cab4affb0ae3200882d6a8c` permanecen incorporadas pero ocultas.

El MCP disponible no expone una operación para ocultar, mostrar, reordenar o eliminar una `screenInstance`; por tanto la limpieza del canvas debe hacerse manualmente en Stitch, sin borrar recursos:

1. Mantener visible `a2f3fa1208bb46a290829db40ac13984` como único Dashboard canónico.
2. En el canvas de Stitch, seleccionar cada referencia no canónica y usar su menú contextual para **Hide / ocultar del canvas**, no Delete: Corporate Slate `feb91ca011ef43a98fd49587cf969446` y desktop intermedio `8113256174604f419f9b40d438b35e62`.
3. Para que quede literalmente un único Dashboard visible, ocultar también las referencias responsive `0eb6f9eccaed46bfb46746ae0d84e83a`, `acda0c0a7de049a59b8cec214b0e8f21` y `454e4f18f0e442a48b76c9019cf0d2d1`; conservar sus IDs para revisión y futura implementación.
4. No eliminar Design Systems ni las variantes ocultas. `Executive Precision B2B` puede permanecer visible porque es un asset, no otra pantalla Dashboard.

Identificadores canónicos completos:

- `projects/13874730587997489426/screens/feb91ca011ef43a98fd49587cf969446`
- `projects/13874730587997489426/screens/11e60c8f94fb4fe2a931f47b7c5e0fa9`
- `projects/13874730587997489426/screens/0f4751740d69443293f1f354b62cff4d`
- `projects/13874730587997489426/screens/d2925ad51cab4affb0ae3200882d6a8c`
- `projects/13874730587997489426/screens/8113256174604f419f9b40d438b35e62`
- `projects/13874730587997489426/screens/a2f3fa1208bb46a290829db40ac13984`
- `projects/13874730587997489426/screens/0eb6f9eccaed46bfb46746ae0d84e83a`
- `projects/13874730587997489426/screens/acda0c0a7de049a59b8cec214b0e8f21`
- `projects/13874730587997489426/screens/454e4f18f0e442a48b76c9019cf0d2d1`

### Design Systems encontrados

| Estado en canvas | Nombre | Asset ID | Evidencia MCP |
|---|---|---|---|
| Oculto; histórico y rechazado para el administrador | `Commerce Operational System` | `assets/39b6fc100ef74c5a98a50e923067fcbd` | No usar ni mezclar con pantallas administrativas |
| Visible; oficial | `Executive Precision B2B` | `assets/a08e56da2173415785f1ffb340ddd446` | Light; Inter + JetBrains Mono; primary `#0F172A`, secondary `#2563EB`, tertiary `#0284C7`, neutral `#64748B`; radio 8 px |

Ambos assets están en versión `1`. El MCP no devolvió design tokens serializados; sí devolvió theme y style guidelines.

### Design System principal elegido

**`Executive Precision B2B` (`assets/a08e56da2173415785f1ffb340ddd446`)** es el sistema principal del administrador. Es coherente con el Dashboard Corporate Slate vigente y con la dirección solicitada: alta densidad, jerarquía sobria, superficies blancas, slate `#0F172A`, bordes de 1 px, Inter y radio de 8 px. `Commerce Operational System` permanece intacto como referencia histórica y no se eliminó ni modificó.

## Evaluación de ADM-001 frente al inventario real

### Cumple o está bien representado

- Incluye selector de rango `today/yesterday/7d/30d/month/custom`, fechas inclusivas y límite máximo de 366 días.
- Incluye KPIs comparativos, tendencia de ventas, órdenes recientes, productos principales, collections/payment/refund, fulfillment, returns, fiscal/SUNAT, inventario y procurement.
- Distingue el alcance Store (`Lima Online`) del alcance tenant-wide mediante `All Company` para inventario y procurement.
- Mantiene visibles Tenant y Store, navegación agrupada, breadcrumbs/inbox/perfil y contexto operativo.
- La composición desktop es densa, border-first, con jerarquía legible, montos tabulares, badges y uso moderado de color.
- La variante mobile transforma la tabla de órdenes en filas/cards y conserva los datos esenciales; existe además un drawer con Tenant, Store, búsqueda, usuario y cambio de Tenant.
- Representa reconciliación fiscal manual y visibilidad de costes condicionada por permiso, coherente con las restricciones del dominio.

### Resultado del refinamiento y aprobación

Correcciones aplicadas en las variantes nuevas:

- Se retiraron `Export report`, refresh manual, referencias bancarias/settlement y el panel decorativo `System State Diagnostics & Edge Indicators`.
- Se mantuvieron solo proyecciones readonly documentadas por `Analytics`, con estados persistidos canónicos y sin acciones comerciales inventadas.
- Se separaron visualmente métricas `CURRENT STORE` de inventario/procurement `ALL COMPANY`.
- Rentabilidad quedó condicionada a `inventory.cost.view`; beneficio y margen solo se muestran con cobertura de costes **100% COMPLETE** y se retienen con cualquier cobertura incompleta.
- Desktop conserva shell, jerarquía y densidad con tipografía legible; mobile usa drawer y convierte tablas extensas en tarjetas; no usa navegación inferior competitiva.
- Se eliminaron valoración de inventario y agregados no suministrados por la proyección Analytics.
- Los ejemplos son sintéticos y no contienen datos personales reales.

Estado posterior a la aprobación:

- El usuario aprobó oficialmente el estilo visual de `ADM-001`; `Executive Precision B2B` pasa a ser obligatorio para las pantallas administrativas siguientes.
- El desktop canónico ya está incorporado, pero su instancia lógica comprobada mide 1280 px; el objetivo exacto 1440 sigue pendiente.
- La propuesta tablet está incorporada, pero Stitch la devolvió como `DESKTOP` y su instancia mide 1280 px; queda pendiente una variante con metadatos `TABLET`/768 verificables.
- Falta ocultar manualmente las cinco referencias no canónicas actualmente visibles para dejar solo el desktop canónico en el canvas.
- Los estados transversales quedan definidos como patrones documentales, no como pantallas separadas.

### Patrones de estados visuales

| Estado | Patrón acordado |
|---|---|
| Loading skeleton | Conservar shell, selectores y geometría final; esqueletos neutros por KPI/gráfico/fila, sin números ficticios ni cambios de layout. Marcar la región como ocupada en implementación. |
| Empty state | Vacío local dentro de la sección, con título breve, explicación del rango/alcance y solo un enlace contextual que exista y esté autorizado. El vacío global de RBAC es distinto: “No dashboard widgets available”. |
| Error recuperable | Aviso inline en la sección afectada, sin ocultar datos sanos. Mostrar reintento únicamente cuando exista un handler real; nunca simular refresh global. Mantener contexto de Tenant, Store y rango. |
| Sección sin permiso | Omitir por completo la sección y recomponer la grilla sin huecos, placeholders bloqueados ni revelación de nombres o valores financieros. |
| Sesión caducada | Estado bloqueante sin datos sensibles visibles detrás; mensaje corto y única acción real “Sign in again”. No mezclarlo con un error recuperable de widget. |

No se generaron pantallas independientes para estos estados durante esta fase.

## Primer lote solicitado — FASE 26.6

| Pantalla Laravel | Diseño Stitch correspondiente | ID real | Enlace real | Estado | Pendiente de aprobación |
|---|---|---|---|---|---|
| ADM-001 · `/admin/dashboard` · Dashboard administrativo | REF-03 Dashboard | canónico refinado `a2f3fa1208bb46a290829db40ac13984`; mobile `0eb6f9eccaed46bfb46746ae0d84e83a`; drawer `acda0c0a7de049a59b8cec214b0e8f21`; tablet propuesta `454e4f18f0e442a48b76c9019cf0d2d1` | [Proyecto][project] · [desktop][desktop-refined-shot] · [mobile][mobile-refined-shot] · [drawer][drawer-refined-shot] · [tablet propuesta][tablet-proposal-shot] | Refinado; previews reales disponibles; limitaciones de canvas/tablet documentadas | **Sí** |
| ADM-013 · `/admin/products` · Products Index | REF-05 Products Index | desktop `3a6c7377559d4d389795af2eee4f1c7d`; mobile `40e90b8a58b74ca599f933a4c2f10924` | [desktop][adm013-desktop-shot] · [mobile][adm013-mobile-shot] | Recuperable por ID; implementación Vue completada en FASE 26.8; validación visual manual pendiente | **Sí** |
| ADM-014/015 · `/admin/products/create` y `/admin/products/{product}/edit` · Product Create/Edit | REF-07 Product workbench | ADM-014: `007ac28b970744b894215b10aaf0dc08` / `ee284fbb10ce419aa2bc1546ad9f823d`; ADM-015 candidato funcional: `0e30c4efc15744069f6c01dc74b6689b` / `7c709b3ef7f94832856782397306bd93` | [create desktop][adm014-desktop-shot] · [create mobile][adm014-mobile-shot] · [edit desktop funcional][adm015-functional-desktop-shot] · [edit mobile funcional][adm015-functional-mobile-shot] | Create implementado en FASE 26.10 y workbench Edit completo en FASE 26.11; contratos Laravel preservados; validación visual manual pendiente | **Sí** |
| ADM-016–018 · Categories Index/Create/Edit | REF-08 Categories | ADM-016: `045b5a63c318431c9daee046a4924631` / `9afecad50e4a4138ab4d1022ad3d4dab`; ADM-017: `e89785d0231f4e03bfb343f0b5faf6b9` / `052cb6cd3d064e9f8b44a0b67d44af9d`; ADM-018: `1d520fc67a054eb1b52c22a3701b651c` / `53b14fe0f55440f2b7a9cf1104c82521` | [index desktop][adm016-desktop-shot] · [index mobile][adm016-mobile-shot] · [create desktop][adm017-desktop-shot] · [create mobile][adm017-mobile-shot] · [edit desktop][adm018-desktop-shot] · [edit mobile][adm018-mobile-shot] | ADM-016, ADM-017 y ADM-018 implementadas en FASE 26.12–26.14 con contratos Laravel preservados; validación visual manual pendiente | **Sí** |
| ADM-019 · `/admin/media` · Media Library | REF-09 Media library | desktop funcional `610e6ea35fde48f5a5e354b41f35c62d`; mobile funcional `3d9e23a8003c458087575f372e44697c` | [desktop funcional][adm019-functional-desktop-shot] · [mobile funcional][adm019-functional-mobile-shot] | Rediseñada e internacionalizada en FASE 26.16 con contratos Laravel preservados; validación visual manual pendiente; mobile visible, desktop solo recuperable | **Sí** |
| ADM-020 · `/admin/listings` · Listings Index | REF-14 Listings | desktop `84a1d011736549e699fd19c66c68837e`; mobile `12e94170e33e439092b7222f6fd8d499` | [desktop][adm020-desktop-shot] · [mobile][adm020-mobile-shot] | Recuperables por ID; no listados ni incorporados. Desktop conserva discrepancias no sincronizadas; mobile es funcionalmente más fiel pero añade navegación inferior no canónica | **Sí** |
| ADM-045 · `/admin/orders` · Orders Index | REF-17 Order workbench (index) | No asignado | No disponible | No encontrado; no generado | Sí; generación y revisión visual |
| ADM-046 · `/admin/orders/{order}` · Order Detail | REF-17 Order workbench (detail) | No asignado | No disponible | No encontrado; no generado | Sí; generación y revisión visual |
| ADM-012 · `/admin/stores/{store}/settings` · Store Settings | REF-10 Store settings | No asignado | No disponible | No encontrado; no generado | Sí; generación y revisión visual |

Cobertura generada de las rutas administrativas: **9/69**. Cobertura documental de prompts: **69/69**. Las pantallas aún no aprobadas son diseños reales recuperables, no cobertura documental.

### Catálogo administrativo — FASE 26.6.3

Generación ejecutada el 2026-10-18 en el proyecto existente `13874730587997489426`, siempre con `assets/a08e56da2173415785f1ffb340ddd446`. No se creó otro proyecto ni se modificó el Design System.

| ID admin | Pantalla | Desktop real | Mobile real | Generación | Canvas | Aprobación |
|---|---|---|---|---|---|---|
| ADM-013 | Products Index | `3a6c7377559d4d389795af2eee4f1c7d` · `DESKTOP` · captura 2560 × 2048 | `40e90b8a58b74ca599f933a4c2f10924` · `MOBILE` · captura 780 × 3248 | Canónica corregida; `get_screen` sí | No listada/incorporada todavía | Pendiente |
| ADM-014 | Create Product | `007ac28b970744b894215b10aaf0dc08` · `DESKTOP` · captura 2560 × 2048 | `ee284fbb10ce419aa2bc1546ad9f823d` · `MOBILE` · captura 780 × 2706 | Canónica corregida; `get_screen` sí | **Listadas, incorporadas y visibles**; instancias 1280 × 1024 y 390 × 1352 | Pendiente |
| ADM-015 | Edit Product Workbench | `cf8372a7b08f4e6b9d828976827ffbd6` · `DESKTOP` · captura 2560 × 2048 | `e04c7f30aef5421f874b16f39eafc9a8` · `MOBILE` · captura 780 × 6168 | Canónica corregida; `get_screen` sí | No listada/incorporada todavía | Pendiente |
| ADM-016 | Categories Index | `045b5a63c318431c9daee046a4924631` · `DESKTOP` · captura 2560 × 2688 | `9afecad50e4a4138ab4d1022ad3d4dab` · `MOBILE` · captura 780 × 3324 | Referencia recuperable; implementación Vue plana completada en FASE 26.12 | No listada/incorporada todavía | Implementación pendiente de validación visual |
| ADM-017 | Create Category | `e89785d0231f4e03bfb343f0b5faf6b9` · `DESKTOP` · captura 2560 × 2048 | `052cb6cd3d064e9f8b44a0b67d44af9d` · `MOBILE` · captura 780 × 1768 | Referencia recuperable; implementación Vue completada en FASE 26.13 | No listada/incorporada todavía | Implementación pendiente de validación visual |
| ADM-018 | Edit Category | `1d520fc67a054eb1b52c22a3701b651c` · `DESKTOP` · captura 2560 × 2048 | `53b14fe0f55440f2b7a9cf1104c82521` · `MOBILE` · captura 780 × 1872 | Referencia recuperable; implementación Vue completada en FASE 26.14 | No listada/incorporada todavía | Implementación pendiente de validación visual |
| ADM-019 | Media Library | `4e02ae0351974f6db5b8b1f28aefc31b` · `DESKTOP` · captura 2560 × 2048 | `27f03933ca884281b1bdb3cfb793237f` · `MOBILE` · captura 780 × 3856 | Canónica corregida; `get_screen` sí | No listada/incorporada todavía | Pendiente |

Los tamaños anteriores son los metadatos reales de captura devueltos por `get_screen`. Solo `ADM-014` tiene ahora `screenInstance`: su ancho lógico desktop es 1280 px y el mobile es 390 px. Para las otras seis pantallas no se declara todavía un ancho lógico desktop de 1280/1440. Sus capturas mobile de 780 px son coherentes con una composición solicitada de 390 px a 2×, pero no sustituyen la comprobación de una instancia.

Artefactos no canónicos conservados, sin borrado:

- `8488aca3b35f4de5b0e035d2ebe5f91a`: titulado Mobile ADM-017, pero `get_screen` devuelve `DESKTOP`, captura 2560 × 3130.
- `76a7c80bdfc04bdea6250193d67b1a6f`: titulado Mobile ADM-018, pero `get_screen` devuelve `DESKTOP`, captura 2560 × 3362.
- Primeras salidas sustituidas al retirar UI no respaldada: ADM-013 `29d80da7ca204bccbd169cd365de0c6f` / `8131991a6c7c459a9d8ce4d79a177c03`; ADM-014 `da59e4498a414fa6b11200a7a64f835f` / `8dd79153e4ea47c2b339a363aa694c75`; ADM-015 `c5b7e9cffdf942c587005e55b687de82` / `83ae42d5cdc34126bd4406c9e9a08529`; ADM-016 `2d29b66639fb470eb578b81388a3be62` / `54b6f54083884a0bbd7ec1017b34ed2a`; ADM-017 `03b22dce10d948af84096e906720fd5a` / `4cfc16bdc80f4d4cb50e948466f86d03`; ADM-018 `0639a9649a4f4ee9980cc9e3b08d75ae` / `b0bc72bc934c49f9baa98367359edac1`; ADM-019 `9983b12ab7b54b52bbd4bcf62bd042e2` / `ed4a50c0a0524a6589e58905c0fc87d8`.

Las 14 correcciones canónicas retiraron estado de clúster/SLA/versiones, filtros no existentes, telemetría, bulk actions e inventario/costes no suministrados. La revisión detallada posterior detectó todavía discrepancias funcionales en `ADM-015` y `ADM-019`, registradas a continuación.

### Sincronización y revisión — FASE 26.6.4

Verificación MCP posterior a la apertura del proyecto:

- `get_project.updateTime`: `2026-10-19T01:58:48.463156Z`.
- `list_screens`: **12** recursos listados.
- `screenInstances`: **17** instancias totales: 15 pantallas y 2 Design Systems.
- Canónicos de catálogo: **14/14 recuperables**, **2/14 listados**, **2/14 incorporados** y **2/14 visibles**.
- El MCP no ofrece una señal distinta de render del navegador: se considera visible una instancia incorporada con `hidden` ausente o `false`. Bajo esa definición, solo `ADM-014` desktop/mobile está visible entre los canónicos del lote.

| Pantalla canónica | Recurso | Captura real | Instancia lógica | Listada / incorporada / visible |
|---|---|---:|---:|---|
| ADM-013 desktop | `3a6c7377559d4d389795af2eee4f1c7d` · `DESKTOP` | 2560 × 2048 | No disponible | No / No / No |
| ADM-013 mobile | `40e90b8a58b74ca599f933a4c2f10924` · `MOBILE` | 780 × 3248 | No disponible | No / No / No |
| ADM-014 desktop | `007ac28b970744b894215b10aaf0dc08` · `DESKTOP` | 2560 × 2048 | **1280 × 1024** | **Sí / Sí / Sí** |
| ADM-014 mobile | `ee284fbb10ce419aa2bc1546ad9f823d` · `MOBILE` | 780 × 2706 | **390 × 1352** | **Sí / Sí / Sí** |
| ADM-015 desktop | `cf8372a7b08f4e6b9d828976827ffbd6` · `DESKTOP` | 2560 × 2048 | No disponible | No / No / No |
| ADM-015 mobile | `e04c7f30aef5421f874b16f39eafc9a8` · `MOBILE` | 780 × 6168 | No disponible | No / No / No |
| ADM-016 desktop | `045b5a63c318431c9daee046a4924631` · `DESKTOP` | 2560 × 2688 | No disponible | No / No / No |
| ADM-016 mobile | `9afecad50e4a4138ab4d1022ad3d4dab` · `MOBILE` | 780 × 3324 | No disponible | No / No / No |
| ADM-017 desktop | `e89785d0231f4e03bfb343f0b5faf6b9` · `DESKTOP` | 2560 × 2048 | No disponible | No / No / No |
| ADM-017 mobile | `052cb6cd3d064e9f8b44a0b67d44af9d` · `MOBILE` | 780 × 1768 | No disponible | No / No / No |
| ADM-018 desktop | `1d520fc67a054eb1b52c22a3701b651c` · `DESKTOP` | 2560 × 2048 | No disponible | No / No / No |
| ADM-018 mobile | `53b14fe0f55440f2b7a9cf1104c82521` · `MOBILE` | 780 × 1872 | No disponible | No / No / No |
| ADM-019 desktop | `4e02ae0351974f6db5b8b1f28aefc31b` · `DESKTOP` | 2560 × 2048 | No disponible | No / No / No |
| ADM-019 mobile | `27f03933ca884281b1bdb3cfb793237f` · `MOBILE` | 780 × 3856 | No disponible | No / No / No |

Stitch incorporó también cuatro artefactos de catálogo **no canónicos**, todos visibles: primera salida desktop de ADM-014 `da59e4498a414fa6b11200a7a64f835f` (1280 × 1024), primera salida desktop de ADM-019 `9983b12ab7b54b52bbd4bcf62bd042e2` (1280 × 1566), salida anterior mobile de ADM-017 `4cfc16bdc80f4d4cb50e948466f86d03` (390 × 957) y salida incorrecta de ADM-018 `76a7c80bdfc04bdea6250193d67b1a6f` (recurso y ancho lógico desktop, 1280 × 1680). No deben aprobarse en lugar de los IDs canónicos.

#### Design System

Los 14 recursos fueron generados con `Executive Precision B2B` (`assets/a08e56da2173415785f1ffb340ddd446`) según la procedencia de generación registrada. El HTML de los 14 conserva Inter, slate `#0F172A` y radios de 8 px, por lo que la inspección visual es consistente con ese sistema. `get_screen` no expone un campo de asociación a Design System; por ello el MCP actual no permite una segunda comprobación independiente por recurso. El asset aprobado sigue visible, versión `1`, y no fue modificado.

#### Resumen visual y funcional por pantalla

| Pantalla | Revisión desktop/mobile contra inventario y backend | Estado para aprobación |
|---|---|---|
| ADM-013 · Products Index | Tabla desktop densa y cards mobile legibles. Conserva nombre, slug, estado, SKU default, variantes, categorías, búsqueda, paginación, editar y archivar; no introduce thumbnail, precio ni detalle. Añade un botón visual `Clear` que la Vue actual no tiene, aunque equivale a vaciar la búsqueda. | **Aprobable con observación menor**; falta canvas para ambos canónicos. |
| ADM-014 · Create Product | Formulario coherente para nombre, descripción, SKU, barcode y categorías, con cancelar/crear y adaptación mobile en una columna. No incorpora pricing, media ni inventario. | **Aprobable visual y funcionalmente**; único par canónico visible y con viewport lógico comprobado. |
| ADM-015 · Edit Product | Buena jerarquía para una página extensa y responsive; representa detalle, tax, categorías, options, variants, galerías y pricing. Sin embargo, inventa documentos en `MediaPicker`, categoría `Primary`, borrado/edición incremental de options y `Save Options`, auto-mapeo de imágenes por color y un precio base agregado. El backend actual crea options con sus values, adjunta media por variante exacta y edita precios por cada variante. | **Bloqueada** hasta una futura corrección autorizada; no editar en esta fase. |
| ADM-016 · Categories Index | La referencia presenta parent, position, status, product count, paginación y acciones sin búsqueda/filtros/drag-and-drop, pero usa indentación de árbol no respaldada por el índice. FASE 26.12 implementó tabla desktop y cards mobile planas, mostrando el nombre del padre sin construir el árbol. | **Implementada en Vue**; validación visual manual pendiente. |
| ADM-017 · Create Category | La referencia contiene name, parent y position sin status, pero muestra un preview de slug y códigos de categorías no enviados por Laravel. FASE 26.13 implementó solo los campos reales, documentó slug/ACTIVE server-side y mantuvo mobile con controles y CTAs completos. | **Implementada en Vue**; validación visual manual pendiente. |
| ADM-018 · Edit Category | La referencia conserva name, status, parent, position, back/save y slug bloqueado, pero enumera self/descendants y códigos no serializados. FASE 26.14 usa únicamente parents elegibles, normaliza sus claves, mantiene slug read-only, modo view-only y separación visual de Archive. | **Implementada en Vue**; validación visual manual pendiente. |
| ADM-019 · Media Library | La grilla/cards, uploader JPEG/PNG/WebP, búsqueda, metadata, title/ALT, usage count y bloqueo de borrado son coherentes en lo esencial. El rediseño de FASE 26.16 descarta `Uploaded By`, copiar Markdown/embed, filtros y listas de referencias; conserva `media.manage`, editor title/ALT, `media.delete` y manejo explícito del 409. El detalle usa un `<dialog>` nativo accesible en lugar del panel lateral de Stitch, y la confirmación de borrado sigue siendo `window.confirm` traducido. | **Implementada en Vue durante FASE 26.16**; validación visual manual pendiente. |

Las capturas/previews canónicas permanecen enlazadas en la tabla del primer lote y al final de este documento. La cobertura real continúa en **8/69** pantallas administrativas representadas; solo `ADM-001` está aprobado. `ADM-013`–`ADM-019` continúan pendientes de aprobación; `ADM-019` ya no requiere corrección funcional tras FASE 26.16, aunque `ADM-015` sigue requiriendo corrección.

#### Pendientes de esta revisión

1. Conseguir que los 12 canónicos restantes aparezcan en `list_screens` y `screenInstances` sin regenerarlos ni duplicarlos.
2. Verificar sus dimensiones lógicas cuando Stitch cree las instancias.
3. Ocultar manualmente del canvas los cuatro artefactos no canónicos de catálogo, sin borrarlos.
4. Corregir `ADM-015` solo si el usuario autoriza una fase posterior con `edit_screens`; `ADM-019` ya fue corregida en FASE 26.16 y solo requiere validación visual.
5. Mantener bloqueada la implementación Vue y no iniciar ADM-020–028 hasta decisión explícita.

### Corrección funcional — FASE 26.6.5

Se contrastaron directamente `resources/js/pages/admin/products/Edit.vue`, `MediaPicker.vue`, `admin/media/Index.vue`, controladores, Form Requests, modelos, rutas, permisos y el inventario administrativo. No se modificó Laravel para acomodar el diseño.

#### ADM-015 — Edit Product

IDs de esta fase:

| Variante | ID | Captura | Canvas | Evaluación |
|---|---|---:|---|---|
| Desktop seleccionado | `0e30c4efc15744069f6c01dc74b6689b` | 2560 × 2048 | Listado, incorporado y visible; instancia 1280 × 4063 | Candidato canónico funcional |
| Mobile | `7c709b3ef7f94832856782397306bd93` | 780 × 7860 | Recuperable; no listado ni incorporado | Candidato canónico funcional |
| Desktop duplicado no canónico | source original `0c7bb7fd319f4bff841fd1bfb414e165`; source tras prueba de Design System `905a5d2add5e42a1a1976683b8215134` | 2560 × 2048 | Misma instancia `0c7bb7fd319f4bff841fd1bfb414e165`, visible, 1280 × 4084 | Prueba controlada; conservar, no aprobar |

Correcciones verificadas en HTML/captura:

- MediaPicker limitado a imágenes privadas JPEG/PNG/WebP, con búsqueda, paginación y upload condicionado; sin selector de documentos, SVG, video ni masters públicos.
- Categorías ordinarias multi-select, sin categoría primaria.
- Options existentes readonly; única mutación mediante alta de una option con nombre y values separados por coma.
- Variants editables individualmente; cada una guarda name/SKU/barcode/status/default/option values y gestiona sus propios attachments, sin auto-mapeo por color ni bulk save.
- Galería de producto con una imagen primaria, orden izquierda/derecha, ALT contextual, detach y picker reutilizable.
- Pricing del default price list representado por variante, escala 4, con Save/Remove según `pricing.update`; no existe precio agregado de producto.

Discrepancia residual: Stitch añadió `Save Product` global y `Discard / Back` sin handlers equivalentes en la Vue actual. Mobile separa además `Save Details` y `Save Taxonomy`, mientras Laravel usa un único `UpdateProductRequest` para name, description, status, tax y categories. Por ello ambas variantes quedan como **candidatas, no aprobables todavía**. No se ejecutó una nueva escritura porque las operaciones empezaron a devolver `service unavailable` y luego crearon recursos asíncronos duplicados.

Versiones anteriores conservadas: desktop `cf8372a7b08f4e6b9d828976827ffbd6`; mobile `e04c7f30aef5421f874b16f39eafc9a8`.

#### ADM-019 — Media Library

| Variante | ID | Captura | Canvas | Evaluación |
|---|---|---:|---|---|
| Desktop funcional | `610e6ea35fde48f5a5e354b41f35c62d` | 2560 × 2048 | Recuperable; no listado ni incorporado | **Funcionalmente corregido** |
| Mobile funcional | `3d9e23a8003c458087575f372e44697c` | 780 × 4348 | **Listado, incorporado y visible**; instancia 390 × 2174 | **Funcionalmente corregido** |

Correcciones verificadas:

- Capability de edición `media.manage`; no aparece `media.update` como permiso.
- Sin `Uploaded By`, Copy Embed/Markdown, listas de referencias, filtros de status/tipo ni cards persistidas PROCESSING/FAILED.
- Índice fijo a assets `READY` de tipo `IMAGE`; uploader de un archivo JPEG/PNG/WebP, máximo 10 MB, title/ALT opcionales y progreso/error.
- Búsqueda por title/ALT/original filename, paginación, thumbnail, MIME, dimensiones, bytes, fecha y `usage_count` agregado.
- Editor limitado a title/ALT bajo `media.manage`, sin reemplazo de archivo.
- Delete bajo `media.delete`; el backend lo bloquea si existe cualquier referencia (producto, variante, logo o favicon), con 409 manejado en la UI.
- Recursos privados para administración; sin documentos, SVG, video, CDN ni acceso público a masters.

Implementación FASE 26.16:

- `resources/js/pages/admin/media/Index.vue` fue rediseñada con `AdminLayout`, `Breadcrumbs`, `PageHeader`, `StatusNotice`, `Pagination`, `EmptyState`, `FieldError`, `MediaThumbnail` y `MediaUploader`.
- Desktop: contexto "Toda la empresa", uploader colapsable bajo `media.upload`, búsqueda real por title/ALT/filename, grid 2/3/4 columnas, metadata técnica, indicador de uso de catálogo y detalle con edición title/ALT bajo `media.manage`.
- Mobile 390: grid de dos columnas, thumbnails proporcionales, paginación compacta, sin scroll horizontal y sin navegación inferior; el detalle usa un `<dialog>` nativo con cierre por teclado.
- i18n: catálogo `media.*` completo en es-PE/en con paridad de claves y placeholders; `deleteConfirm` exacto, `fileHelp` con `{max}` y `usageCount` con `{count}`; fallback es-PE validado.
- Delete: axios DELETE con `Accept: application/json`; un 409 muestra mensaje localizado, conserva el asset y nunca fuerza la eliminación; confirmación exacta "¿Deseas eliminar esta imagen?" / "Do you want to delete this image?".
- Privacidad: masters y derivados privados, entrega por rutas autorizadas, `TenantContext` y aislamiento cross-tenant preservados; sin `StoreContext` en Media.
- Rendimiento: paginación server-side de 24, sin cargas completas ni N+1 nuevos; el uploader se monta solo al abrir el panel y `MediaPicker` conserva su carga diferida en Product Edit.
- Bundle: chunk de página `Index` 2.836 kB → 12.92 kB (4.18 kB gzip); `MediaUploader` 53.68 kB → 54.11 kB; `MediaPicker` 3.76 kB → 3.84 kB.
- Pruebas: 15 tests Media, 119 tests Catalog/Localization/Access/Auth/Tenancy, `test:i18n` con 471 mensajes Vue, `typecheck`, `build`, `npm audit` y `npm audit --omit=dev` sin vulnerabilidades.
- Diferencias con Stitch: sin `Uploaded By`, copy embed, filtros, listas de referencias ni estados PROCESSING/FAILED; `usage_count` solo agrega producto/variante, por lo que el 409 también cubre logo/favicon; el detalle es un diálogo nativo en vez del panel lateral de Stitch.

Las versiones anteriores `4e02ae0351974f6db5b8b1f28aefc31b` y `27f03933ca884281b1bdb3cfb793237f` se conservan, pero dejan de ser las referencias funcionales recomendadas.

#### ADM-016 — resultado de revisión

El modelo `Category` sí soporta `parent`, `children` y `position`, y las validaciones evitan self/descendant. Sin embargo, el índice entrega una colección **plana y paginada de 20 filas** con solo `parent` como nombre; no entrega `parent_id`, depth, children ni el árbol completo. Por tanto:

- La indentación de la captura actual puede entenderse únicamente como representación visual de relaciones existentes.
- No puede implementarse como árbol completo y fiable con los props actuales, especialmente cuando parent/child caen en páginas diferentes.
- No existen reorder, drag-and-drop, expand/collapse ni movimientos inline.
- Antes de implementar Vue, ADM-016 debería volver a una lista/card plana que muestre parent, position, status y product count. No se generó otra variante en esta fase porque el alcance de escritura estaba limitado a ADM-015/019.

#### Sincronización posterior

Estado MCP final de esta fase: `get_project.updateTime = 2026-10-19T02:38:25.518088Z`, `list_screens = 15` y `screenInstances = 20`.

- Los cinco IDs nuevos responden por `get_screen`.
- Visibles entre las selecciones funcionales: ADM-015 desktop `0e30c4efc15744069f6c01dc74b6689b` y ADM-019 mobile `3d9e23a8003c458087575f372e44697c`.
- Solo recuperables: ADM-015 mobile `7c709b3ef7f94832856782397306bd93` y ADM-019 desktop `610e6ea35fde48f5a5e354b41f35c62d`.
- El duplicado desktop ADM-015 usa la instancia `0c7bb7fd319f4bff841fd1bfb414e165`; después de la prueba controlada su `sourceScreen` es `905a5d2add5e42a1a1976683b8215134`. Sigue visible y debe ocultarse manualmente, no borrarse.
- El MCP no expone una operación para ocultar/mostrar instancias. En Stitch debe usarse el menú contextual del duplicado para **Hide from canvas**. Los recursos solo recuperables requieren abrir/localizar el resultado desde el historial de generación del proyecto; no se harán más llamadas de escritura para forzar sincronización.

Los resultados de `edit_screens` devolvieron explícitamente `Executive Precision B2B` (`assets/a08e56da2173415785f1ffb340ddd446`, versión `1`); el desktop directo también se generó pasando ese asset. El Design System no se modificó.

Cobertura posterior: **8/69** pantallas administrativas representadas. ADM-019 queda lista para revisión visual; ADM-015 y ADM-016 mantienen las discrepancias residuales descritas. ADM-020–028 no se iniciaron.

### Paleta oficial y prueba controlada del Design System

Verificación MCP contra `assets/a08e56da2173415785f1ffb340ddd446`, versión `1`:

- Overrides oficiales: primary `#0F172A`, secondary `#2563EB`, tertiary `#0284C7`, neutral `#64748B`.
- Tipografía: Inter; labels técnicos: JetBrains Mono.
- Canvas/card/bordes compartidos: `#F8F9FA`, `#FFFFFF`, `#F1F5F9`, `#E2E8F0`, `#CBD5E1`.
- Estados: Success `#16A34A/#F0FDF4/#BBF7D0`; Warning `#D97706/#FFFBEB/#FDE68A`; Danger `#DC2626/#FEF2F2/#FECACA`.
- La especificación completa y sus reglas de precedencia están en `ADMIN_DESIGN_TOKENS.md`.

Auditoría de HTML sobre ADM-001 y los canónicos desktop/mobile de ADM-013–ADM-019:

- No se detectaron los tres valores de marca del sistema histórico rechazado.
- ADM-001 usa de forma predominante `#0F172A`, `#E2E8F0`, `#FFFFFF` y slate auxiliares.
- Las salidas de catálogo/media usan el asset correcto, pero varias materializan roles dinámicos como `#000000`, `#131B2E`, `#0051D5`, `#F8F9FF` y `#D3E4FE` en vez de expresar siempre los overrides oficiales como literales.
- `#2563EB` aparece explícitamente solo en algunas variantes; `#0284C7` no aparece como literal en los HTML auditados.
- Los verdes observados en badges son Success/Active semántico, no primary de módulo.

Prueba única de `apply_design_system`, sin tocar ADM-001:

1. Se seleccionó el duplicado no canónico ADM-015, instancia `0c7bb7fd319f4bff841fd1bfb414e165`.
2. Se aplicó `a08e56da2173415785f1ffb340ddd446` mediante el mecanismo MCP documentado.
3. Stitch creó el recurso `905a5d2add5e42a1a1976683b8215134` y lo asignó como nuevo `sourceScreen` de la misma instancia.
4. `list_screens` permaneció en 15 y `screenInstances` en 20; no se añadió otra instancia al canvas.
5. El HTML resultante sigue sin la paleta histórica, pero conserva la misma expansión Material. El mecanismo confirma el asset, no normaliza automáticamente cada literal a los overrides de identidad.

Conclusión: `apply_design_system` es seguro si se aplica por instancia y en lotes pequeños, pero no debe ejecutarse masivamente hasta aprobar visualmente la expansión dinámica. No se aplicó a ninguna pantalla canónica ni al Dashboard.

### Cierre visual y funcional del catálogo — FASE 26.6.7

Se volvió a contrastar el diseño con los contratos efectivos de Catalog, Pricing y Media, sin modificar Laravel/Vue:

- `UpdateProductRequest` persiste en una sola operación `name`, `description`, `status`, `tax_category_id` y `categories`; requiere `products.update`. No existen endpoints separados para Details y Taxonomy.
- Options existentes son readonly en la pantalla; el único endpoint de mutación crea una option con `name`, `position` opcional y uno o más `values`.
- Variants tienen altas y actualizaciones independientes de name/SKU/barcode/status/default/option values. No existe borrado de variant.
- Product/variant media tiene endpoints independientes de attach, detach y reorder bajo `products.update` + `media.view`; upload requiere además `media.upload`.
- Default pricing se guarda o elimina por variante y price list bajo `pricing.update`; la sección requiere `pricing.view`.
- Categories Index entrega 20 filas planas paginadas con `id`, `name`, `slug`, `status`, `position`, nombre de `parent` y `products_count`. No procesa parámetros de búsqueda o filtros y no entrega depth, children ni un árbol completo.

#### Escritura controlada y bloqueo de sincronización

Se hizo una sola llamada de escritura, sobre ADM-015 desktop `0e30c4efc15744069f6c01dc74b6689b`. El prompt solicitó retirar las acciones globales, combinar Details/Taxonomy bajo un único `Save product`, conservar únicamente mutaciones respaldadas y respetar los tokens oficiales.

`edit_screens` devolvió un evento de operaciones DOM para el mismo screen ID, sin proponer un ID nuevo. Sin embargo, las verificaciones inmediata y diferidas mediante `get_screen`, descarga de HTML, `list_screens` y `get_project` continuaron devolviendo el recurso anterior:

- título anterior: `ADM-015 — Edit Product Workbench (Desktop — Functional Canonical)`;
- mismo archivo HTML `f97f3a0aecc5425d860f980588012248` y misma captura `2bdf979693c8424f9b2e808b8987f2b4`;
- siguen presentes `Save Product (products.update)`, `Discard / Back` y `Save Details`;
- no aparecen `Back to products` ni el título `Final Contract` solicitados;
- `get_project.updateTime = 2026-10-19T03:08:08.899795Z`, `list_screens = 15`, `screenInstances = 20`.

La edición no se considera aplicada ni validada. No se repitió la llamada y se detuvieron todas las escrituras para evitar duplicados o revisiones parciales. Por ello:

- ADM-015 desktop/mobile conserva los IDs candidatos previos y sigue bloqueada.
- ADM-016 desktop/mobile conserva los IDs previos y sigue bloqueada por la representación de árbol.
- No hay IDs nuevos ni previews nuevas verificables.
- El estado del canvas no cambió.

#### Revisión ADM-019

Se revisaron HTML y capturas de `610e6ea35fde48f5a5e354b41f35c62d` (desktop) y `3d9e23a8003c458087575f372e44697c` (mobile):

- uploader de un archivo, búsqueda real, paginación, grid responsive de cuatro columnas desktop y dos mobile;
- selección azul, inputs con anillos de foco, editor title/ALT bajo `media.manage` y borrado bajo `media.delete`;
- assets con uso no ofrecen borrado efectivo; el estado de confirmación mostrado corresponde al ejemplo de un asset sin uso;
- no aparecen `#176B4D`, `#12563E` ni `#D9F06A`;
- la UI mantiene jerarquía slate/azul y estados semánticos. El HTML conserva roles dinámicos Material del asset además de aliases oficiales, limitación ya documentada y no suficiente por sí sola para regenerar una pantalla funcional.

ADM-019 queda **funcional y visualmente revisada, pendiente de aprobación humana**. No se regeneró.

Cobertura tras esta fase: **8/69** pantallas representadas. Solo ADM-001 está aprobada oficialmente; ADM-019 queda lista para aprobación, ADM-015 y ADM-016 siguen bloqueadas. ADM-020–028 no se iniciaron.

### Continuación Listings/Pricing/Promotions — FASE 26.6.8

#### Identificación desde el inventario

Los tres primeros IDs del bloque `ADM-020–028` no son Pricing/Promotions mezclados; corresponden íntegramente a Listings:

| ID admin | Nombre real | Ruta | Componente | Alcance |
|---|---|---|---|---|
| ADM-020 | Listings · Listings | `/admin/listings` | `admin/listings/Index.vue` | Tenant + Store activa |
| ADM-021 | Listings · Create listing | `/admin/listings/create` | `admin/listings/Create.vue` | Tenant + Store activa |
| ADM-022 | Listings · Edit listing | `/admin/listings/{listing}/edit` | `admin/listings/Edit.vue` | Tenant + Store activa |

Pricing empieza en ADM-023 y Promotions en ADM-026; no se avanzó a esas pantallas.

#### Contratos funcionales verificados

**ADM-020 — Listings Index**

- Requiere `listings.view`; Create usa `listings.create`.
- Búsqueda real por nombre de Product, `storefront_slug` o SKU de Variant; sin filtro de status, sort, export ni bulk actions.
- Consulta estrictamente la Store activa y el Tenant autorizado, ordena por `created_at` descendente y pagina 20.
- Props por fila: `id`, product name, status, slug, `enabled_variants_count`, effective price-list name/currency o null, y `published_at`.
- Configuración independiente de default price list de Store mediante `PUT /admin/listings/settings/price-list`, solo `listings.update`, con active tenant price lists y fallback al company default.
- Acciones reales: Edit/View, Publish, Unpublish y Archive según `listings.update`, `listings.publish`, `listings.archive` y estado. Archive usa `window.confirm` en la Vue actual.
- No entrega total de variants, amounts, Store code, cache/sync status, listing code, storefront URL pública ni analytics.

**ADM-021 — Create listing**

- Requiere `listings.create`.
- Product search por nombre o SKU; devuelve máximo 50 Products ACTIVE del Tenant que todavía no están listados en la Store activa.
- Props de Product: `id`, `name`, `slug`, default SKU nullable. Selector radio único.
- Formulario: `product_id` requerido, `storefront_slug` nullable/máx. 180 en backend —la Vue actual lo muestra required y lo precarga— y `price_list_id` opcional de una lista ACTIVE del Tenant.
- Create normaliza y hace único el slug por Store, crea status DRAFT e inicializa relaciones de variants habilitando las variants ACTIVE. Luego redirige a ADM-022.
- No configura variants, publicación, media, inventario, promociones ni amounts durante el alta.

**ADM-022 — Edit listing**

- Vista bajo `listings.view`; mutación bajo `listings.update`; Publish/Unpublish y Archive usan permisos separados.
- Formulario único: slug requerido/máx. 180, override opcional de active price list y array presente `enabled_variant_ids` limitado a variants del Product.
- Archived es permanentemente readonly. Una Variant de catálogo no ACTIVE no puede habilitarse.
- Search real por Variant SKU/name; 20 variants por página, default primero y luego SKU.
- Props por Variant: id, SKU, name, status, configured, enabled y preview server-side nullable con base amount, final amount, total discount y promoción ganadora.
- El preview usa Pricing/Promotions server-side, cuatro decimales, sin stacking; no permite editar precios o promociones desde Listings.
- Publish exige Store ACTIVE, Product ACTIVE, al menos una Variant ACTIVE habilitada, effective price list ACTIVE y precio base para cada SKU habilitado. Estados: DRAFT, PUBLISHED, UNPUBLISHED y ARCHIVED.

#### Estado previo revisado

- ADM-015 desktop: el usuario la aprobó visualmente. `get_screen`/`download_file` aún muestran `Save Details` y `Discard`; se conserva como observación MCP, sin intentar otra corrección. Mobile sigue sin aprobación explícita.
- ADM-016: desktop/mobile responden por ID, pero los archivos recuperables conservan slugs/representación jerárquica; no existe corrección plana verificable ni instancia en canvas. No se volvió a editar.
- ADM-019: desktop/mobile funcionales; mobile visible, desktop recuperable; pendiente de aprobación visual. No se regeneró.

#### Generación y sincronización

| Pantalla | Desktop | Mobile | Captura real | Listada / incorporada / visible | Estado |
|---|---|---|---|---|---|
| ADM-020 Listings | `84a1d011736549e699fd19c66c68837e` · DESKTOP | `12e94170e33e439092b7222f6fd8d499` · MOBILE | desktop 2560 × 2048; mobile 780 × 1768 | No / No / No para ambas | Candidatas recuperables; pendientes de aprobación |
| ADM-021 Create listing | Sin ID verificable | No generada | No disponible | No / No / No | Desktop devolvió HTTP 503 ambiguo; no se repitió |
| ADM-022 Edit listing | No generada | No generada | No disponible | No / No / No | Omitida al detener escrituras tras el 503 |

Estado MCP final: `get_project.updateTime = 2026-10-25T15:23:29.438333Z`, `list_screens = 15`, `screenInstances = 20`. Los dos recursos ADM-020 responden mediante `get_screen` y `download_file`, pero no aparecen en `list_screens` ni en `screenInstances`; por tanto no se declara viewport lógico, solo tamaño real de captura. La captura mobile 780 px es coherente con la solicitud 390 a 2×, pero no sustituye una instancia lógica verificada.

La llamada desktop de ADM-021 devolvió `The service is currently unavailable` sin ID. Tras comprobación inmediata y diferida, no apareció por título en `list_screens`/canvas. El `updateTime` avanzó, pero la reconciliación posterior demostró que ese campo también puede cambiar sin una escritura atribuible desde esta sesión; no constituye evidencia de creación. No se repitió la generación y se detuvieron las escrituras para evitar un duplicado.

#### Validación ADM-020

- Ambas variantes fueron generadas pasando explícitamente `assets/a08e56da2173415785f1ffb340ddd446`.
- HTML desktop/mobile sin `#176B4D`, `#12563E` o `#D9F06A`; usan los aliases slate/azul aprobados y roles dinámicos del asset.
- Mobile usa cards sin tabla horizontal, búsqueda única, configuración de default list, acciones RBAC y counts enteros. Discrepancia visual: añadió una navegación inferior `Listings / Catalog / Prices / Settings`, no canónica frente al drawer aprobado.
- Desktop conserva la tabla de seis columnas, búsqueda, configuración de Store y acciones reales, pero Stitch inventó `PE-01`, telemetría Live Sync/cache, fracciones enabled/total y diagnóstico/link de resolución no suministrado.
- Se solicitó una única corrección desktop mediante `edit_screens`; el evento reportó 15 operaciones correctivas, pero `get_screen`/`download_file` continuaron entregando el archivo original. No se repitió.
- Ninguna variante se considera aprobada automáticamente. Desktop queda funcionalmente bloqueada por las discrepancias; mobile es candidata con observación visual.

Cobertura tras esta fase: **9/69** pantallas administrativas representadas. Solo ADM-001 está aprobado oficialmente como referencia general y ADM-015 desktop cuenta con aprobación visual expresa del usuario; ADM-020 queda pendiente de revisión.

### Reconciliación read-only de Stitch — FASE 26.6.9

No se ejecutaron `generate_screen_from_text`, `edit_screens`, `generate_variants`, `apply_design_system` ni otras escrituras.

#### Estado observado

- `get_project`: proyecto correcto `projects/13874730587997489426`, privado, `TEXT_TO_UI_PRO`, dispositivo base DESKTOP.
- Consulta final: `updateTime = 2026-10-25T15:47:24.450354Z`, `list_screens = 15`, `screenInstances = 20`.
- Las 15 entradas listadas y las 20 instancias fueron inspeccionadas individualmente por ID, label y `sourceScreen`.
- No existe en esos conjuntos ningún título, label o source identificable como ADM-021/Create Listing.
- ADM-020 desktop/mobile tampoco aparecen en `list_screens` ni `screenInstances`, pero sus IDs conocidos siguen respondiendo mediante `get_screen` y sus HTML/capturas mediante `download_file`.
- No hay nuevas instancias con labels `Generating Screen...` atribuibles temporalmente a ADM-020/021; las existentes apuntan a recursos históricos ya identificados.
- `get_project` expone metadata e instancias, pero no operaciones, jobs, sesiones de generación ni historial. `list_screens`/`get_screen` no exponen fechas de creación/actualización por pantalla.
- El servidor MCP Stitch no publica recursos ni templates mediante `list_mcp_resources`; tampoco existe otra herramienta de lectura de historial en el catálogo disponible.

La diferencia ADM-020 confirma que **recurso recuperable**, **pantalla listada** e **instancia de canvas** son estados independientes: `get_screen` puede resolver un ID ausente de `list_screens` y `screenInstances`. Por ello, la ausencia de ADM-021 en esos dos listados no demuestra que no exista un recurso no incorporado.

#### Resultado ADM-021

Estado: **INDETERMINADO**.

- No hay ID, título listado, instancia, archivo HTML o captura que confirme su existencia.
- Tampoco hay un endpoint de historial o enumeración exhaustiva que permita descartar un recurso no listado.
- El cambio de `updateTime` no es prueba: durante esta fase read-only volvió a cambiar de `15:23:29.438333Z` a `15:47:24.450354Z` sin una escritura de Stitch ejecutada por esta sesión. Puede corresponder a actividad externa, reconciliación interna u otra actualización; el MCP no permite atribuirlo.
- No se repitió ADM-021.

#### HTTP 503: hechos e hipótesis

**Hechos confirmados:**

- La respuesta fue HTTP 503 con texto `The service is currently unavailable`; esto acredita indisponibilidad del servicio en ese momento.
- La descripción MCP de `generate_screen_from_text` advierte que una falla de conexión puede completar la generación y ordena no reintentar; para timeout recomienda consultar el recurso, pero aquí no se recibió ID.
- ADM-020 demuestra retraso o ausencia de incorporación a list/canvas pese a existir como recurso recuperable.
- La [documentación pública oficial localizada][stitch-official-intro] describe Stitch como experimento generativo e iterativo, pero no documenta garantías transaccionales del MCP, semántica del 503, retención de jobs ni consistencia entre `get_screen`, `list_screens` y `screenInstances`.

**No confirmado / hipótesis:**

- Que el 503 haya ocurrido antes o después de persistir ADM-021.
- Que exista un job asíncrono todavía pendiente.
- Que el recurso exista sin instancia o sin entrada listada.
- Que el cambio de `updateTime` provenga de ADM-021.

La causa más prudente es **indisponibilidad temporal del servicio**. Persistencia parcial/asíncrona y retraso de canvas son posibilidades compatibles con las herramientas, pero no están demostradas para ADM-021.

#### ADM-020 reconciliada contra el contrato

| Variante | Recurso | Listada | Instancia/canvas | Estado funcional |
|---|---|---|---|---|
| Desktop | `84a1d011736549e699fd19c66c68837e` · 2560 × 2048 | No | No | Recuperable; bloqueada por elementos no suministrados |
| Mobile | `12e94170e33e439092b7222f6fd8d499` · 780 × 1768 | No | No | Recuperable; contrato principal presente, navegación inferior no canónica |

Cumplimiento común confirmado: scope de Store, búsqueda real, paginación visual de 20, configuración de default price list, Create, Edit/View, Publish, Unpublish y Archive. Los HTML no contienen la antigua paleta verde y conservan Executive Precision B2B.

Correcciones que deben aplicarse en una futura fase de diseño, **no en implementación Vue**:

- Desktop: eliminar `PE-01`, Live Sync/cache, fracciones enabled/total, código/diagnóstico técnico, `Resolve Prices` y cualquier enlace sin ruta real. Mostrar solo `enabled_variants_count` y los errores Laravel suministrados.
- Mobile: eliminar la barra inferior `Listings / Catalog / Prices / Settings`; conservar el header y drawer oficiales. No convertirla en patrón reusable.
- En ambas: mantener exclusivamente el Store name disponible, search product/slug/SKU, 20 por página, default list y transiciones separadas por `listings.create/update/publish/archive`.

#### Procedimiento seguro de recuperación

**A. ADM-021 localizada con ID**

1. No generar otra pantalla.
2. Ejecutar solo `get_screen` y `download_file` sobre ese ID.
3. Comparar título, dispositivo, HTML, captura y contrato ADM-021.
4. Consultar `list_screens`/`get_project` para clasificarla como recuperable, listada, incorporada y visible.
5. Registrar el ID como candidata; cualquier corrección posterior requiere autorización y una única edición controlada.

**B. ADM-021 visible únicamente desde Stitch web**

1. No generar otra pantalla.
2. Abrir su detalle/historial en la web y registrar manualmente ID/URL, título, dispositivo y captura si la interfaz los expone.
3. Con el ID, volver al escenario A.
4. Si la web no expone ID, conservar evidencia visual y mantenerla como `visible web / no recuperable MCP`; solo repetir lecturas tras reabrir/sincronizar el proyecto.

**C. ADM-021 no localizada y generación todavía ambigua — estado actual**

1. Mantener el bloqueo de escritura.
2. Revisar manualmente canvas e historial web y repetir posteriormente solo `get_project`/`list_screens`.
3. No usar `updateTime` como prueba y no intentar IDs aleatorios.
4. Si persiste, reportar el job al proveedor con project ID, hora y respuesta 503. Una futura regeneración exige decisión explícita aceptando el riesgo de duplicado.

**D. Evidencia suficiente de que no se creó recurso**

Solo se alcanza si una fuente exhaustiva del proveedor o el historial web confirma fallo terminal sin output y no existe pantalla/recurso asociado. Entonces sería seguro registrar baseline de counts y ejecutar **una** nueva generación ADM-021, verificar inmediatamente el ID devuelto y no repetir ante otro resultado ambiguo. Las APIs de lectura actuales, por sí solas, no permiten alcanzar este escenario.

Conclusión: ADM-021 permanece **INDETERMINADA**; ADM-020 existe como dos recursos recuperables pero no incorporados. No se altera la cobertura de **9/69**.

## Otro proyecto detectado y descartado

También existe `B2B Commerce Admin Dashboard` (`projects/4588129243879558020`) con una pantalla `ADM-001 — Administrative Dashboard`. No se selecciona porque es un proyecto distinto, anterior y con menos variantes; no se mezclan sus IDs ni diseños con la cobertura oficial.

## Matriz de cobertura

| Plantilla | Pantallas | Cobertura Stitch real |
|---|---|---|
| REF-03 Dashboard | ADM-001 | **Aprobado: 1/1**; tablet con limitación de metadatos |
| REF-06 Simple form | ADM-003, 006, 010, 011, 014, 017, 018, 024, 033, 034, 037, 059 | 3/12 (`ADM-014`, `ADM-017`, `ADM-018`) |
| REF-11 Settings/access | ADM-004, 005, 007, 008, 009, 049, 064, 065 | 0/8 |
| REF-05 Products index | ADM-013 | 1/1; implementada en Vue, pendiente de validación visual manual |
| REF-07 Product workbench | ADM-014, 015 | 2/2; pendiente de aprobación |
| REF-08 Categories | ADM-016, 017, 018 | 3/3; pendiente de aprobación |
| REF-09 Media | ADM-019 | 1/1; pendiente de aprobación |
| REF-10 Store settings | ADM-012 | 0/1 |
| REF-13 Merchandising | ADM-023–028 | 0/6 |
| REF-14 Listings | ADM-020–022 | 1/3 (`ADM-020` candidata desktop/mobile) |
| REF-12 Inventory | ADM-029–035 | 0/7 |
| REF-15 Customers | ADM-036–039 | 0/4 |
| REF-16 Cart/Checkout | ADM-040–044 | 0/5 |
| REF-17 Orders | ADM-045, 046 | 0/2 |
| REF-18 Payments/reverse logistics | ADM-047, 048, 052–057 | 0/8 |
| REF-19 Fulfillment | ADM-050, 051 | 0/2 |
| REF-20 Procurement | ADM-058–063 | 0/6 |
| REF-21 Fiscal/SUNAT | ADM-064–067 | 0/4 |
| REF-22 Notifications/Audit | ADM-068, 069 | 0/2 |
| REF-06 Tenant selection variant | ADM-002 | 0/1 |
| REF-23 Authentication | AUTH-001–006 | 0/6; fuera del denominador admin |

## Próximo paso recomendado, no ejecutado

Antes de continuar con ADM-023–028, localizar o descartar manualmente en el historial de Stitch la posible generación asíncrona de ADM-021 y conseguir sincronización estable. Después, completar ADM-021/022 sin repetir el desktop ambiguo; solo tras aprobación visual de Listings continuar con ADM-023–025 Pricing y ADM-026–028 Promotions.

La implementación Vue y toda generación adicional quedan detenidas hasta aprobación explícita.

[project]: https://stitch.withgoogle.com/projects/13874730587997489426
[desktop-current-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UvYP1MbuedNsxTMziWvKZVHak9YV0PXPG2iCPqRUkhvUEgVsmjny9joosM7a3KSF1NmEXD_xu9S6FrWov_FZ9DE2xf4NXofjvnFoOYhUKAE4ZkdzMjNKw28Vmmy9YOa9EYG-RvBkjSSHFj_WbWAV8C-91CpTa4JS8E0PkQO-8vcjZvWoMAB2uLnTloQn4vBkBc9PogKBswiWDwpm60h6aYmXjBhQ8Ry3--EVxHsutmfal6rVpPlRArSI0
[desktop-current-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzAwMDY1ZTI1NzIxZjE4M2MwNWMyZmQ3MWEwM2IxMzhkEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=89354086
[desktop-old-shot]: https://lh3.googleusercontent.com/aida/AEtjO1VIFTy8lA4DN6qMzEQFQErxwe3l4cboPngo4jYOs752siERoXlPE2ckbt8cv4n7KY_2lc9K46qv5d6pGRKDpw81RBgYdQClsbSGk4O9rDkH6uC4H9RtSDUBFPNyQzg3JoRd3JLz-Fwp4gapGv8SW8yNCutwWrkbFxHzxQtLMttUl9tjL9w76sx65lHsdWaYbMA1N-AKHq5piMmXdj2BQ0y1j9UrXyulowV1AbFTPmfp8uFnoUWdrjOTa-o
[desktop-old-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzAwMDY1ZTI0Yjk1ODdhNTIwMzM4NWMyYzUxMzI4MTIyEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=89354086
[mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1Vkg0eDC60ZP0u8X44FxbvCopP5VtcrXoGqCpf8mouADlMegnP4hlOrSrxWJ51nHoC8_e-JvUsELVx8jL-AGnsBkj71l4yjLT6A836LEEGPmlQttK5wUL3NnU2m-pGRVqurxF5Xd6IsEKKRklqTZayj2uklnsMhQ1pvafUaPK9P-bWxDuGP-dz3ocsRvYfsuXFw7iPmh5CObdUU2oQGKaQO7n5hbJJG1KVr6dBHAb29bCnYEx7WSbk2gCIN
[mobile-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzAwMDY1ZTI1NTg4Y2M0ZWUwMzc0OTkwNjkyMWI5ZGQwEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=89354086
[drawer-shot]: https://lh3.googleusercontent.com/aida/AEtjO1WmVww5p5yhF5544fAWllYf5Z6oqKkIdyyU4t3vb7adQiGsuFWitbUCZYYdZikU6K6ei0r9sZ34JYGO6b_MZXE-oPBzD211ZpErEFSDm1jCw9zBwg7AaA3iSILQwp8OnJPDZPtJ9Zv-InGRWgNsQ3gySCGGU7ImpKHyPezPA2Hedz6DsTsIGrhnhgaeJCa0-o7n4Qxw8LyZgLxGlZU3vv3W9mOYKAQACjsN8BghS2VyZ5yzOwLuTNudEDna
[drawer-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzAwMDY1ZTI1NjFiNDg4NzIwMzM4NWMyYzUxMzI4MTIyEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=89354086
[desktop-refined-intermediate-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XxrryhKXnwgzoFf19TqcXUi-03dUvKbVuI3b64iB67570E3XOSnsr32dqV--5tjPEf5iBkp-sXSOvnHiugWCUwJjEXUqDRzDb8i2fw-etQWN4rYLjuiOUrFSyQcnj4ADiAmU0uLr0J-ArNLgIATiXZRxHzLG4WkC_3iGV252UANlR5JVRx52ue4lcKT36WiucBkcQlWHqDz2eXXuKqlVtyitoggY8duIAf4A4jmqFBixVGQ7Km7frTn97x
[desktop-refined-intermediate-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzRkNTFjNzc4ZDYyMzQ5M2M5YjA2YTM4MjQwZGZmOWM1EgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[desktop-refined-shot]: https://lh3.googleusercontent.com/aida/AEtjO1Ui_jkvZ_p1FGxxd7G_Wu3IuM97J4yZFvnnILKoosuVlVu_CgN-09Q0Ukz97-MMlBb3R9h5ZltVUuXAki8tatTOOT06-F_uDfk1Xz_WcPMq6zTl2aQfufAOMGyl8gRWYk_jz4HTL7qQfa-NlHBiSJsBeYV--dR1eeyG0SBf2oIf_Sy_ILQHUNd4mb0m57RE771xNXXKyfKMJ04OoOEojGGstk1RNJLjZ0qXAsg33FOWeGBmC86L2PBPExa3
[desktop-refined-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sX2Q1ODQ5MzRiZWIyZjQwZWM4ZGIwNjgzMGE3YjIxOWQ3EgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[mobile-refined-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UD6k7kEAt5WVDUt-dUmYzSqqbWrr2jNmqE852qPFrStH_z-NCn3Zv21JNWidJjieic3dL_kZln85axqflKKDMRHHKresELdhYz5hmWViZPw3hN09cjBmMpXsrRWV0Iuuc2th-P-ifKOlnCyzxhtX8j-RSRZvlCm7_8WyhtZjWIiHeIFBHeus8DegShBtfqyjzV_77YYE-C-APTDTC1aXcQTOQYkwtKy3lGg9tIp1DdECRr7ewPHUCoboMw
[mobile-refined-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sX2RhNDE0ZjBiYmE2ZjRlYWVhODU4NDRiNjM3NzZhZTA1EgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[drawer-refined-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UEGKndDvXcJpoYHGrPbaM6itJvI3KDW0ziC0vyJOP32bzm4lmg4h0DRLJhTyu9Wkm7R6QiKAfDwRUql6SWLhRxrjMCaQOIoyoF-UNetZTQ0Vu8-YJ86aIgGvmVD6BRhnygXZhWpvFCFw2XWvCAFE5w1XggCHprLH-TgpB5sVNehj6kvUx-f32Ujn2Yo7dPMCwvTVEWbwHPy-lfnV7qhG1HJz0dNg13UMLEwqxS948oS6ANcOk_245beyOd
[drawer-refined-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzA2ODE1ZDU5MmM2NTQwMWQ4NzU5OTJhYTJiYWIxYzQxEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[tablet-proposal-shot]: https://lh3.googleusercontent.com/aida/AEtjO1U2h4YnzZSIkgQMO6Ziv6GszBPI0w9fy_VhiWVWW7Y8_m_KNtcJ2ssAwe-_d3YjIQ59lbGxYR0yEkwGR5LB8s-TQIbtEXUgO7E4PMFVlbTT3YRBWCfnKvIVAae-07SssnnpWcf37wKbtKGyKJlSEiJKPiyIwqyySzA5LyQ5KSknrZWh2XL0hT5owIUqNrioxGYJUeg9SagsPke8HT2n_ZKs5A27BfzsuwT79M1j8Qr1ZeHk30LfPQCgpe0
[tablet-proposal-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sX2Y4M2ZjNjJlODIwMTQ0ZGJhYTgwNTE4NzM5ZmZlM2Q1EgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[adm013-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UShLvh0Pbp8Od8KWKWlhkV-wm0XyVV4L4W0YpTK4-HbAP4ooN00oWSOHgQo7kv1U_2dFxApj6lQ2hNJy6dXgBQ13EK_lW1xdoA1C1iMa5qom7lrLB5L2NPtVTsyueKsj-z8WFyj3O_5oTvXQpHMOxWi9FjyBwuQQyeqzLmeX1ebXZrk9Br_psNWxLDBl9w1fu3cXeDtW3lZwyAipmjVKUE2Ne0H-AJv3NDgchhl7HElV6pHDRrLEwKR54
[adm013-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XgH5-w6SP6TPMKq1pOcU86WZvKNkG_NW7J9ZDjKOCBraU88gc5EdiKpgEJm582wg8m8qGXUAVpJ76qYuYEJqP0kuELwF_cuhp_HpqDvza2Q_2zRJB_HTFyQiZlqnDALUBOs9C8AJb7IxyCKSneRrOV0dNlQKPc6AI1gBewoHsA9mNc6W9te4jdMp9wOGixgHkI5YWA3HVlaBAcyxp5VKlpVkyG-3eYxmAfQV9GYWQrUe95Y_5WTWHm1nXA
[adm014-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XBmcqMjwfgMUiEuSs5HKRDSR3W4SC8_zMaS4a09EJHrr9HghZ8z8iGq5y2NYg43aDJOv7WoNe3rP1We0UAFmiJIaZUA-33T5ZvFHbQYhG7JhVsB2cOshb2mX_WkJV8Mzvuu92gLjfObe4K3qd0wVdVynaR7yH2EGmOfbp2riGhbvQTRJ38KtZUXN4FC_BRjfWqFVpJsuG2-FtGBjZwtAq3qCGsLuxENjy3nmg5hAE5EwiUONfS1ujkAYUZ
[adm014-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UaXIxqW-ZldZuZFUc4qadNfRuvbxczKs1u21hlZKN00aU2jsr8Cvh8qUAtdpCbRDnhYuP0vbUdOAI9OS29XeRGgQ_zRhSwUTT65zKihrKy0kM5d-PK726peg-53Kntl4alGTsHD8CZtYh2E7S0cHwDEGxfXHcEd7EdnyZjb55nvwnIw4kVKWcaEQeuE70MqlMGUaAGpiah4Vop7Z16IjQRQsBHPDwF2MTG_Qcc5D8xqXcY0ljZPCDgd_c
[adm015-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1VM08_5zJjaA7jAeXvfvZqXHdXTrIMdG05tiow1s7rJr3fKsW-zKL7yBoHb_gucA-Dm__1uZnYn7VtmJlHQBxkNJXttL37Ha4paTEgsCpMqtE954YJyiLllYExEglRFSKOpqKf2Xeu-8aE6ySOLoR30cmTtrpq-AVD3QszvcW4F2H8QD_wSsVUfLm625hJhRhy12nMq_LL0EYau6rfHq8-LbLKOTz_v-ZOgE7Hw-kOpBAz_Hy2dtfmv9r_T
[adm015-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1VxLw2XA08voAef1N8GgVnEFpxfnCXtgQ1t7eI5oXVUS30Ecsv4jFLkO2rq2elDyGd60ahrvBe9Rc_ZfXW2gtG_PfII1q4w-0OkFPO_D1ycAP8_7BSH8hU7ySZ-Cz4q953P4wWNR2wp6JeNeLdL9OV3fqDCwMayZOWsNmMlVSgrvhRISGyqcGmQ8qBIRq5RSECV1rDqW_IvTj1UwOpZQOr5J8FrrJ4jRMeA6f7bnXUI49w_vgu4oHkQ4y9_
[adm016-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1WfmXD8RYIb0Jto-MgWjuU-s6LkMkMa9_D8b54AgrxIJHR1trLbPXZjLZHy0eZWN699NTHTLnrt8LoTBk6-0qO5zUmOeR7_3AhDhe0uX9Sqclf686Lnqd4ZiY9Qh-XsOIQwyslFeIeK7aJ8b-fAws09tAzi8KVEADWbG_FuJfj66T8dKeKPGJHhv5bFMkUQ6iVwoE0RKYpUQmw4wUciYmMnumTa2BTisjZr59sc5aKToaR64ClagRrUlD4h
[adm016-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XXPpvdcoHWu5roMHhT7sJwqZcNi9Cpc6RequC_x-V86TMcBOiumMbKfNt9FkpyBbD1DqwUhgsF_GGtliC8XnP46TTGTOQuMjW99UZryHQcqZU-jpTqJBBhoKo66gVE7W0C-8B-SpoK_KObl49DGFA54_VmBZ-ouBAXskBs9sLPPrGV0k9LStV6laI4rTwkVJVmwF913rxYIz_xgPz2XD3dQ7vIjkofHHY0dBWe5lmWGWUaPPv6hufd6Xlr
[adm017-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XS7pnGf7xGf9f6L3SSpNaAzgHJ6_itklGCp3qfatfGbTZhNyLk-SFLsNuV_mgPMOVc0VphlKvxGqxYQa7ficWFe2syfxxJFHR0mIY0GnxZdzGgUqXX6TN3woDdnq1jRa1_II7-O2qFvgq3ruA3tUFAEGI1hPqzdwywxvcV91gq8_OZXuRKTNg2lPkSPGABGR8CVOqlT8AWhIBcUUdzkgK9ePg99Zs079fq3HDS9FzBOq2bUy829Ac-j7_f
[adm017-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1WGjhjaG8ULRdKeUqJ_fLvjzdXXlBtJblWZoCLaaZsOo1wnFY0ofiw0wmOZTKSnFB74f8EFdxnC1-aKyuvMjpVY6XdeQMN2po64ohDv0lAR6LlhoMFUg_1SnGUVeMWUUFWL03SD6U9utHzSL3Fc2kgKaPQFFVXfgdLurWQYgG_jSbkCh-Xl6g0N6fnOzpNefCQYdMMyxXrp2O2zNzg-Oi1SO7sfFp75pUH0GefHjYe2zeGfyzErDy8iJHvn
[adm018-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1USI4MeC16Hws6wY0NsNeVZpEZPJg8YLcICPpGebkhakjbiQsMQMVuxfOy7zErU4Q2upWDgjMmx_pcTW2TBt_fhFma5xB1mK6E8bBSB8fZ3jzJOE-C4mvnaQlKywmjxotmiVENQzl7ig3gyFq6cYWm8UL617pNGsTqMEuUAlV8eFPeU_nAHD8s7dsNyBnc_HLMlgcV4dy6slcdF6rVgSV5c_bVhgXLXUpqlA7wF2IBJKoI0EdFr8hVELAvA
[adm018-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XwaQ_9D6d9DSR8EpwhVCwD6JiSQIaXpZO2DaXFk5zepSUx5STTTI1hqmvCavtfB2bpKuAxrJC2P7NwQVdL77hAC7Ig5vxgdZ81Q66-IF-DbwPws15C4YJEVoHJWQ4taMKl-15mLRplxfrLXPciB-7mFAyOIJ6PO9kdHExHGYEI7kT3s_wV2LyyyOUIDBs1M0_GcTanKAeI5wOLJhmP3-nIg76V9V90hjVGGk0MmkpwEphZ49TZ-1oeiUMS
[adm019-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1U49wGXb3BWqngrdm-CRlGdUBWl-sDRf_ZzRQqtPJP0sLQxQVxcxJAaPg26b8mVXLLWjmQ_gqrYZZZYcZ33OE8v2vgFMTcom0rQYbw0uzPGVKhwwtEyIe83NKahXa5-WUjQJVFTIUMPMeqvJeO_ESvV2gG9SGIRx6iHLlWJbDwlG0I1rWGoQl3stt43B7yr9bN5HcqCU3ZSiEwb_qwHCDMV1x2VecChGCA9e4pyeOkBGVRXnUJ3UbFUFhvD
[adm019-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1Wkh630LnlR0xSlZYsw_1xLHM9GsJZmDRvQxUQrV3InP1Vae5wyaQztcgv8uA70FPoOXkrpLOdamCBnbOjE-Cb7sm3KzMltL3aJt_n6D4uWQYn-m-hac7yjyLJ5ve8IUqQcJ0zeIb3uXNbn56YRNHY9zrgO4t4CHkDJB_0AsSqWB8u3syMqPMOyqc6v_AHlwqAx1CTUFhkoYPLHA8ETf9Xa-TTil2YZn4zBrH3NTlr4PZU4vjBi1V5mkLm0
[adm015-functional-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UbO5PQ-Bedp04_T0mftLuvy1IvS8af61fjwjQAQdFNJZuZZx_5m8Lke53OvDI71tRn7gQzgt2zrnqj2MuHq_I8DtRnBWC5I8SmoTS1gMlJ1tgSy2Ci029pAE52IZHQXFCDTEuX4sXnF9HDhTIJDXlFeUhPoSFUsmeC0YPa2nf9h-sM7oBQYwBgqG93rhMgLJuSLPB8KCICKJtcL8oKhlDqtXodMcqmfS7BXwMo7dlPKPuA8tiWYklNnzWy
[adm015-functional-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1XTdNnmtlygUmHCqQHqWf6TcmocOxqrA854_RlSdFkYeI0kouEDTaNsLkd12HXUdhsn4jKRYHknvhHdkxiZpQmYHSFIB-cUZzLN82ELCNKxSjKPLLZW5B0kllvNYcYmfXegvEPm87Zs7x9MHmcKXEFXoC_Mpwof7r6PAghscPwIl4taSrxdIFtNSyrv4lYXR5awEKvyFL7TIj5TRwyBpjlpP2ojKOY0i53kAbiUA66PgsSEIdWBHQd4D2Vb
[adm019-functional-desktop-shot]: https://lh3.googleusercontent.com/aida/AEtjO1X4quA5sq4f48tunR4QVkDkWI_mOpAYD78jOjO3MIyfuGzfKbqbwFr_tB_8WUSxwZ8dLZfIV9GqNW4mbR2tW1QKnzBg8C7CIAJFLWcZydXS1u2Dh_S-EKTSOoqKaYmN1Okbr_aHcR9wxKnudE6QN0lgPJ7dVDxgSP51ryfE5Hr1a48kQ9u902deKIibZduG5WwEq3MK1NQ0hYzDauNVjFkK7CJ1iJL4hJ3Xjtrakl-MJNefCXSbg7AThuCb
[adm019-functional-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UePfuNrQmzI8OB3Gh4gla1HpjWGMSVKBVYhH_L_uJ8ERethpgIYBMLLIgFGua2B2ZI-avgR9mRxELPNdl0Uaj6vtzHBwP5ljXYUm8momuITvNBbz32iXuo2PCXO4jKnNcMAWerEP9J5q9LHveGH_Mflivt-ePIp-NCtvlfQEv-aX-yeE6S44EgKhor3dY8nvXjwcPf-6Z_HYvcf_9Yny1ZmIDf28C8RWyrTgQ6qHO2s29hT--nBBNs0RtG
[adm020-desktop-shot]: https://stitch.googleapis.com/v1/projects/13874730587997489426/files/6279f3904be84065af55586f47574101:download
[adm020-desktop-html]: https://stitch.googleapis.com/v1/projects/13874730587997489426/files/a91527ffe5ee4983baf4ba34cad97039:download
[adm020-mobile-shot]: https://lh3.googleusercontent.com/aida/AEtjO1UAK0VgfkrXV8ehvPUr5oQwKPiexYr5ZiuqhMc8sazbQIlVD7T0Eaz8EggarsUqUFxYcUesnbYURQXXJduGXs_JxlAqRzf4J2xOQFxsvy-2kimRP-5l-Lo4_yCN5AhfTpmdRp0v96AFdyCn5V761NO_SNwhOq5P01XstV44O6631_jfSw3ZDOMSZ3m_oKbdMVzRqcqESaZq7ui6JhIicId5iA1yCdKQqL9Sw7X3Q83BZEc9Si_hL86xLHWV
[adm020-mobile-html]: https://contribution.usercontent.google.com/download?c=CgthaWRhX2NvZGVmeBJ8Eh1hcHBfY29tcGFuaW9uX2dlbmVyYXRlZF9maWxlcxpbCiVodG1sXzc5MjhlNDQzODYxYzQxMDdiNzgxMjhiYWIxODkzMzQzEgsSBxD1jbGIuggYAZIBJAoKcHJvamVjdF9pZBIWQhQxMzg3NDczMDU4Nzk5NzQ4OTQyNg&filename=&opi=96797242
[stitch-official-intro]: https://developers.googleblog.com/en/stitch-a-new-way-to-design-uis/
