# Tokens oficiales del administrador

## Fuente de verdad

- Proyecto: `projects/13874730587997489426` — **Commerce SaaS Admin Dashboard**.
- Design System: **Executive Precision B2B**.
- Asset: `assets/a08e56da2173415785f1ffb340ddd446`, versión `1`.
- Verificación MCP: `list_design_systems` y prueba controlada de `apply_design_system`, 2026-10-18.

Este asset es la única fuente visual autorizada para Dashboard, Catalog, Media, Orders, Inventory, Pricing, Stores y el resto del administrador. `Commerce Operational System` es un asset histórico oculto y no debe usarse como referencia de implementación.

## Identidad oficial

| Rol oficial | Token MCP | Valor |
|---|---|---|
| Primary | `overridePrimaryColor` / `customColor` | `#0F172A` |
| Secondary | `overrideSecondaryColor` | `#2563EB` |
| Tertiary | `overrideTertiaryColor` | `#0284C7` |
| Neutral | `overrideNeutralColor` | `#64748B` |

La identidad es slate/azul. `#176B4D`, `#12563E` y `#D9F06A` no son colores de marca autorizados.

Los verdes solo pueden aparecer como semántica de éxito mediante los tokens oficiales de estado; nunca como primary, navegación, foco o acento de módulo.

## Superficies, texto y bordes

Valores expuestos por `Executive Precision B2B`:

| Uso | Token | Valor |
|---|---|---|
| Canvas operativo | `canvas-bg` | `#F8F9FA` |
| Fondo dinámico | `background` | `#F8F9FF` |
| Superficie/card | `surface-card`, `surface-container-lowest` | `#FFFFFF` |
| Superficie baja | `surface-container-low` | `#EFF4FF` |
| Superficie | `surface-container` | `#E5EEFF` |
| Superficie alta | `surface-container-high` | `#DCE9FF` |
| Superficie más alta | `surface-container-highest` / `surface-variant` | `#D3E4FE` |
| Texto principal | `text-primary` | `#0F172A` |
| Texto secundario | `text-secondary` | `#334155` |
| Texto atenuado | `text-muted` | `#64748B` |
| Borde sutil | `border-subtle` | `#F1F5F9` |
| Borde estándar | `border-default` | `#E2E8F0` |
| Borde fuerte | `border-strong` | `#CBD5E1` |

`designMd` también contiene roles dinámicos Material como `primary: #000000`, `primary-container: #131B2E`, `secondary: #0051D5` y `tertiary-container: #001D31`. No sustituyen los overrides de identidad confirmados. Al revisar una pantalla se debe distinguir entre:

1. identidad oficial y aliases compartidos;
2. roles dinámicos generados por Stitch;
3. estados semánticos.

No se deben inventar equivalencias fuera del asset.

## Estados semánticos

| Estado | Foreground | Background | Border |
|---|---|---|---|
| Success | `#16A34A` | `#F0FDF4` | `#BBF7D0` |
| Warning | `#D97706` | `#FFFBEB` | `#FDE68A` |
| Danger | `#DC2626` | `#FEF2F2` | `#FECACA` |
| Neutral/Draft | `#475569` | `#F1F5F9` | `#E2E8F0` |

Un badge `ACTIVE` puede ser verde semántico. Una acción primaria, link, foco, sidebar o selección no debe usar ese verde.

## Tipografía

- Body, headings y controles: **Inter**.
- Labels técnicos, IDs, SKU, hashes y montos: **JetBrains Mono**.
- Base body: 14/20 px.
- Display: 30/38 px, weight 600.
- Headings: 24/32, 18/24 y 15/20 px, weight 600.
- Labels: 12/16 y 11/14 px.
- Datos tabulares: `font-feature-settings: "tnum" 1, "cv05" 1`.

## Componentes compartidos

### Botones

- Primary: `#0F172A`; texto `#FFFFFF`; hover `#1E293B`; active `#020617`.
- Focus: `#2563EB`, ring visible de 2 px.
- Secondary: superficie `#FFFFFF`, texto `#0F172A`, borde `#CBD5E1`.
- Tertiary/ghost: texto `#334155`; hover `#F1F5F9`.
- Destructive: texto `#DC2626`, borde `#FECACA`, hover `#FEF2F2`.
- Altura: 36 px por defecto y 30 px en controles compactos/tablas.

### Inputs

- Fondo `#FFFFFF`, borde `#E2E8F0`, texto `#0F172A`, placeholder `#94A3B8`.
- Radio 6 px; foco `#2563EB`; error `#DC2626`.

### Cards y tablas

- Cards: `#FFFFFF`, borde 1 px `#E2E8F0`, radio 8 px.
- Header de tabla: `#F8F9FA`, texto `#64748B`, borde `#E2E8F0`.
- Filas: 44–48 px; hover `#F8F9FA`.
- Selección: `#F8FAFC` con marca izquierda `#2563EB`.

### Forma y espaciado

- Radio del Design System: `ROUND_EIGHT`.
- Cards/modales: 8 px; inputs/botones: 6 px; badges: 4 px.
- Escala: 4, 8, 12, 16, 24 y 32 px.
- Arquitectura border-first; sombras mínimas y sin gradientes decorativos.

## Auditoría de pantallas actuales

Se inspeccionaron los HTML canónicos de ADM-001 y ADM-013–ADM-019, desktop/mobile, más la pantalla no canónica usada para la prueba.

- Ningún HTML inspeccionado contiene `#176B4D`, `#12563E` o `#D9F06A`.
- ADM-001 usa predominantemente `#0F172A`, `#E2E8F0`, `#FFFFFF` y slate auxiliares.
- ADM-013–ADM-019 mezclan los aliases oficiales con roles dinámicos del mismo asset (`#000000`, `#131B2E`, `#0051D5`, `#F8F9FF`, `#D3E4FE`).
- El uso de emerald/green encontrado en badges corresponde a Success/Active; no se detectó como primary prohibido.
- Secondary `#2563EB` aparece de forma explícita solo en algunas variantes; Tertiary `#0284C7` no aparece como literal en los HTML auditados.

Por tanto, las pantallas no usan la paleta verde rechazada, pero todavía necesitan una revisión visual controlada para uniformar acciones, links, foco y selección con los aliases oficiales.

## Prueba de `apply_design_system`

Se aplicó el asset únicamente al duplicado no canónico de ADM-015:

- Instancia de prueba: `0c7bb7fd319f4bff841fd1bfb414e165`.
- Source previo: `projects/13874730587997489426/screens/0c7bb7fd319f4bff841fd1bfb414e165`.
- Source resultante: `projects/13874730587997489426/screens/905a5d2add5e42a1a1976683b8215134`.
- La instancia se conservó; `list_screens` permaneció en 15 y `screenInstances` en 20.
- El HTML resultante no incorporó los verdes prohibidos, pero mantuvo la misma expansión dinámica de roles Material. Aplicar el asset no convierte automáticamente todos los literales en `#0F172A/#2563EB/#0284C7`.

## Método seguro para futuras aplicaciones

1. Seleccionar una instancia no canónica y no aprobada.
2. Capturar `screenInstance.id`, `sourceScreen`, screenshot y frecuencia de colores antes del cambio.
3. Ejecutar `apply_design_system` con el asset ID sin prefijo: `a08e56da2173415785f1ffb340ddd446`.
4. Volver a consultar `get_screen`, `list_screens` y `get_project.screenInstances`.
5. Comparar HTML/captura, tokens oficiales, semántica y funcionalidad.
6. Promover el resultado solo después de aprobación visual; conservar el source anterior.
7. Aplicar en lotes pequeños. Nunca incluir ADM-001 aprobado sin autorización explícita.

No se autoriza una aplicación masiva hasta revisar la prueba y definir si los roles dinámicos del asset son aceptables o deben corregirse mediante prompts/edición controlada.
