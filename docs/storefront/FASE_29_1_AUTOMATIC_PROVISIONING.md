# FASE 29.1 — Aprovisionamiento automático del Storefront

**Estado:** implementado
**Fecha:** 2026-10-28

## Contrato

Crear una Store mediante `CreateStore` crea, dentro de la misma transacción,
la Store activa, su `StoreSettings` y una `StorefrontPublication`
`UNPUBLISHED`. No hay observers ni publicación automática. Essential continúa
como theme efectivo predeterminado mediante los registros estáticos de SF-02;
no se añadió persistencia de themes.

`ProvisionStorefrontPublication` es el servicio compartido por la creación y
la reconciliación. Toma un advisory transaction lock PostgreSQL antes de buscar
y asignar el handle global. Normaliza el nombre, usa un identificador seguro si
el nombre queda vacío o reservado, limita a 63 caracteres y prueba sufijos
`-2`, `-3`, etc. dentro del lock. Las constraints únicas de PostgreSQL siguen
siendo la última garantía. Si la Store ya tiene publicación, devuelve esa fila
sin cambiar handle, estado o `published_at`.

## Stores anteriores a SF-01

La reconciliación explícita es masiva e idempotente:

```bash
php artisan storefront:reconcile
php artisan storefront:reconcile --tenant=<tenant-uuid>
```

Sólo selecciona Stores sin publicación. Cada Store se procesa en su propia
transacción y conserva su `tenant_id`; publicaciones existentes, incluso
`PUBLISHED`, nunca se actualizan. Las filas nuevas quedan `UNPUBLISHED`. El
comando puede repetirse y reportará cero cuando no quede trabajo.

Este comando no se ejecutó automáticamente ni contra `pgsql/commerce` durante
la implementación. Debe ejecutarlo un administrador después de revisar el
entorno y el alcance. `storefront:prepare`, `storefront:publish` y
`storefront:unpublish` se conservan como herramientas de mantenimiento.

## Store Settings

ADM-012 incorpora la sección compacta **Storefront público**. `stores.view`
permite consultar estado, handle y theme. `stores.update` permite publicar o
despublicar mediante endpoints POST con sesión, CSRF, Gate de ruta y una segunda
autorización Laravel en el controller. Toda Store se resuelve desde el Tenant
activo; un UUID de otro Tenant produce 404.

Publicar reutiliza `SetPublicStorePublication`: Tenant y Store deben estar
`ACTIVE` y debe existir una publicación válida. Las transiciones son
idempotentes y no exigen Listings. El enlace usa una ruta relativa y sólo se
entrega cuando la publicación está activa y la ruta temporal está habilitada
para local/testing y el Host actual está permitido. No se construyen URLs desde
Hosts no confiables.

## Flujo del cliente

1. Crear la Store desde **Stores → Crear tienda**.
2. Abrir **Configuración de tienda** y revisar **Storefront público**.
3. Pulsar **Publicar tienda** y confirmar.
4. En local/testing, abrir el enlace cuando el flag y Host permitidos estén
   configurados.
5. Pulsar **Despublicar tienda** para retirar el acceso.

Una Store nueva no requiere comandos Artisan. La reconciliación sólo aplica a
Stores históricas creadas antes de esta fase.

## Límites conservados

No se modificaron catálogo público, reglas comerciales, carrito, checkout,
dominios de producción, otras pantallas ADM, Stitch ni marketplace de themes.
FASE 30 no se inició.
