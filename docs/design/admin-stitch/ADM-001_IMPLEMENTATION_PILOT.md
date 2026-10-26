# ADM-001 — piloto de implementación

Fecha: 2026-10-25. Fase: 26.7.

## Alcance

Piloto de presentación del Dashboard administrativo basado exclusivamente en:

- Desktop aprobado: `a2f3fa1208bb46a290829db40ac13984`.
- Mobile aprobado: `0eb6f9eccaed46bfb46746ae0d84e83a`.
- Drawer aprobado: `acda0c0a7de049a59b8cec214b0e8f21`.
- Design System: Executive Precision B2B.
- Tokens: `ADMIN_DESIGN_TOKENS.md`.

No se modificaron rutas, controllers, services, queries, props Inertia,
tenancy, StoreContext, RBAC, auth, modelos ni base de datos. Tampoco se
rediseñaron los contenidos de ADM-020–028.

## Arquitectura visual

`AdminLayout` conserva su contrato de slot (`user`, `currentTenant`,
`currentStore`) y ahora compone:

- `AdminSidebar`: navegación centralizada y filtrada por los permisos ya
  compartidos en `currentTenantMembership.permissions`.
- `AdminHeader`: contexto actual, notificaciones y trigger mobile.
- `MobileDrawer`: reutiliza exactamente `AdminSidebar`, bloquea el scroll de
  fondo, recibe foco al abrir y cierra por botón, backdrop, navegación o Escape.
- `ContextSwitcher`: acceso tipado a las pantallas reales de Companies y
  Stores. No simula listas que no existen en los shared props.

La navegación sigue usando `Link` de Inertia. Ocultar links continúa siendo
solo una mejora de usabilidad; Laravel Gates y los queries scoped conservan la
autoridad.

## Componentes creados

- `resources/js/components/admin/AdminHeader.vue`
- `resources/js/components/admin/AdminSidebar.vue`
- `resources/js/components/admin/Breadcrumbs.vue`
- `resources/js/components/admin/ContextSwitcher.vue`
- `resources/js/components/admin/MobileDrawer.vue`
- `resources/js/components/admin/PageHeader.vue`
- `resources/js/components/admin/StatusBadge.vue`
- `resources/js/components/admin/navigation.ts`

Componentes reutilizados y adaptados:

- `AdminLayout.vue`
- `DashboardTrendChart.vue`
- `MetricDelta.vue`

No se crearon Button, DataTable, FormSection, EmptyState o LoadingState
genéricos porque el piloto no justificaba todavía sus contratos y hacerlo
habría adelantado el rediseño de otras pantallas.

## Tokens

`resources/css/app.css` expone aliases Tailwind CSS v4 para primary,
secondary, tertiary, neutral, canvas, surface, textos, bordes y estados
Success/Warning/Danger/Information. Inter es la fuente UI y JetBrains Mono la
fuente técnica/tabular. La carga usa el plugin de fuentes ya instalado; no se
añadieron dependencias.

## Dashboard

Se conservaron todas las secciones opcionales y condiciones existentes:

- attention, sales, payments, profitability y trend;
- order/payment/fulfillment statuses;
- recent orders y top products;
- inventory y procurement con scope `All company`;
- returns y fiscal;
- estado sin widgets autorizados.

No se copiaron los importes, KPIs, clientes, timestamps, porcentajes,
telemetría, badges o botones sintéticos del HTML de Stitch. La UI solo itera los
props reales de `BuildAdminDashboard`.

En mobile, recent orders, inventory y purchase orders usan cards; las tablas se
mantienen desde `md`. No existe barra inferior. No quedan `overflow-x-auto` ni
anchos mínimos en el Dashboard piloto.

## Diferencias deliberadas frente a Stitch

- Los switchers abren las pantallas reales de selección; no contienen un menú
  inline porque los shared props solo entregan el contexto activo.
- No se muestran “updated moments ago”, realtime, readonly analytics, cluster
  status, latencia o búsqueda global porque no existen esos contratos.
- No se muestran totales globales o subtítulos inventados como “5 of 1,428”.
- El gráfico conserva la implementación SVG real y sus series actuales; no
  reproduce la curva sintética de Stitch.
- Las secciones visibles dependen de RBAC y pueden ser menores que la referencia.

## Validación y rendimiento

- `npm run typecheck`: correcto.
- `npm run build`: correcto. Aviso no bloqueante sobre `fontaine`, paquete
  opcional; no se instaló.
- Guard de base de datos: 1 test, 4 assertions, correcto contra
  `pgsql.testing/commerce_testing`.
- `tests/Feature/Analytics`: 28 tests, 297 assertions, correctos.
- No existe script JS lint en `package.json`.
- Build observado: CSS 72.24 kB (14.74 kB gzip), `AdminLayout` 14.27 kB
  (4.55 kB gzip), Dashboard 34.12 kB (8.69 kB gzip). No se capturó un baseline
  de build antes de editar, por lo que no se afirma un delta exacto.
- Se mantuvo el chart sin dependencia, no hay polling ni nuevas requests, y no
  cambió el query count backend. `DashboardIsolationTest` confirma estabilidad
  de queries con mayor volumen.

## Validación visual y pendientes

Se inspeccionaron las referencias Stitch y el HTML generado, pero el harness no
dispone de navegador automatizado autenticado para capturar la aplicación real.
Por eso no se declaran capturas ni mediciones visuales ejecutadas a 1280, 1440
y 390. El responsive fue validado estáticamente por breakpoints y estructura,
no por screenshot.

Antes de extender el sistema:

1. Revisar el Dashboard autenticado a 1280, 1440 y 390.
2. Verificar trap de foco completo del drawer con tecnología asistiva; el piloto
   implementa foco inicial y Escape, pero no incorpora una librería de dialogs.
3. Aprobar shell, densidad, cards, tabla desktop y cards mobile.
4. Solo después extraer primitives adicionales durante una siguiente pantalla
   aprobada; no migrar masivamente los estilos heredados.

## Pulido posterior — FASE 26.7.1

El usuario validó visualmente el piloto inicial en desktop y mobile. El pulido
posterior mantuvo el shell, el sidebar de 248 px, los tokens y los contratos,
con estos ajustes limitados al Dashboard:

- El contenedor conserva `max-width: 90rem`, ocupa todo el ancho disponible y
  mantiene el padding compartido del layout. En 1280/1440 el límite no fuerza
  líneas largas porque el sidebar y el padding reducen naturalmente el área.
- `Needs attention` queda estable en una, dos o tres columnas según breakpoint;
  se eliminó la variante de seis columnas que podía estrechar las tarjetas en
  algunos monitores grandes.
- El ritmo vertical mobile baja de 24 a 20 px entre bloques y las cards de KPI,
  órdenes, inventario, compras y top products usan padding/gaps ligeramente más
  compactos. Los controles conservan 36 px mínimos y no cambian sus targets.
- El aviso de comparación usa menos padding en mobile y conserva `line-height`
  legible; desktop mantiene el espaciado anterior.
- El gráfico baja a 192 px de alto en mobile y conserva 240 px desde `sm`.
- Si todos los puntos reales tienen simultáneamente venta bruta y órdenes en
  cero —incluida una serie vacía— el gráfico muestra un estado compacto con
  `role="status"`. Una sola observación distinta de cero mantiene el gráfico y
  toda su información. Los máximos visibles ahora representan máximos reales,
  mientras escalas internas siguen protegidas contra división por cero.

Validación de la fase:

- `npm run typecheck`: correcto.
- `npm run build`: correcto; CSS 72.93 kB (14.82 kB gzip), Dashboard 35.20 kB
  (9.02 kB gzip), AdminLayout sin cambio en 14.27 kB (4.55 kB gzip).
- `tests/Feature/Analytics`: 28 tests, 297 assertions, correctos.
- `git diff --check`: correcto.
- Sin navegador autenticado disponible: la comprobación final 1280/1440/390,
  zoom 100 %, foco y drawer continúa pendiente de una sesión visual manual.
