# SF-01 — Fundaciones públicas y resolución de Stores

**Estado:** implementado
**Fecha:** 2026-10-27
**Alcance:** contexto público, aprovisionamiento controlado y shell Inertia; sin catálogo ni compra

## 1. Arquitectura implementada

SF-01 agrega `app/Modules/Storefront` como capa pública de entrega. El módulo
consume Store/Tenant y su configuración permitida, pero no es propietario de
reglas comerciales. La resolución es independiente de los contextos de sesión
administrativos:

```text
GET /tienda/{publicHandle}
  -> EnsureDevelopmentStorefrontRoute
  -> ResolvePublicStore
  -> PublicStoreContext (request-scoped)
  -> StorefrontHomeController
  -> Inertia storefront/Home
```

`PublicStoreContext` se limpia en `finally` y no escribe
`current_tenant_id`/`current_store_id`. Para rutas `storefront.*`, el middleware
Inertia tampoco comparte usuario, membresía, permisos, contexto o
notificaciones del administrador.

## 2. Identificador y modelo de publicación

La tabla `storefront_publications` contiene una relación 1:1 con Store:

- `tenant_id` y `store_id` protegidos por FKs, más trigger PostgreSQL que exige
  que la Store pertenezca al Tenant indicado;
- `public_handle` globalmente único, en minúsculas y con formato de label URL;
- estado `UNPUBLISHED` o `PUBLISHED`;
- `published_at`, obligatorio sólo para `PUBLISHED`;
- un registro máximo por Store.

`stores.slug` no participa en la resolución. El handle se normaliza con
`PublicHandle`: transliteración/slug, longitud máxima 63 y rechazo de nombres
reservados. La base de datos repite las garantías críticas de formato,
unicidad y coherencia de estado.

Desde FASE 29.1, crear una Store crea también su publicación `UNPUBLISHED` en
la misma transacción. La asignación automática del handle está serializada para
resolver colisiones globales. `storefront:prepare` se conserva para
mantenimiento manual; publicar continúa siendo una operación distinta e
idempotente.

## 3. Ruta y política de hosts

Ruta temporal:

```text
/tienda/{publicHandle}
```

Sólo responde cuando:

- `STOREFRONT_DEVELOPMENT_ROUTE_ENABLED=true`;
- `APP_ENV` es `local` o `testing`;
- `Request::getHost()` pertenece a `STOREFRONT_DEVELOPMENT_HOSTS`.

La lista predeterminada es `localhost,127.0.0.1,::1`. La comprobación ocurre
antes de consultar la publicación. Un entorno `production` devuelve 404 aun
si el flag se configuró accidentalmente en `true`. No se generan enlaces
absolutos desde el Host recibido y no hay resolución de subdominios o dominios
personalizados.

## 4. Reglas de resolución y seguridad

Una publicación sólo resuelve si:

1. el handle coincide exactamente;
2. la publicación está `PUBLISHED`;
3. Tenant está `ACTIVE`;
4. Store está `ACTIVE`;
5. Store pertenece al Tenant de la publicación por garantía de base de datos.

Cualquier fallo devuelve 404. Los parámetros `tenant_id` y `store_id` se
ignoran. La sesión admin puede coexistir en el navegador, pero no determina la
Store pública, no cambia y no se expone como identidad de comprador.

Props permitidas:

- handle público;
- nombre visible con fallback a `Store.name`;
- descripción corta;
- moneda y timezone de Store;
- locale Vue resuelto;
- colores primario/secundario validados.

No se exponen UUID internos, contactos, redes, Media IDs, masters, usuarios,
roles, permisos o rutas administrativas.

## 5. Locale y frontend

`PublicStoreLocale` adapta el contrato Store `ll_CC` a los catálogos Vue:

- `es_PE` → `es-PE`;
- `en_US` → `en` por fallback de idioma;
- locale no soportado → `localization.fallback` (`es-PE`).

Se añadieron `StorefrontLayout` y `storefront/Home`. Son responsive, incluyen
skip link, jerarquía semántica y tokens visuales propios. No reutilizan ni
modifican el layout administrativo. No renderizan imágenes privadas.

## 6. Aprovisionar una Store en desarrollo

Una Store nueva ya queda preparada. El cliente abre ADM-012 y usa **Publicar
tienda**; no necesita comandos. Para Stores históricas sin publicación, un
administrador puede ejecutar una reconciliación masiva e idempotente:

```bash
php artisan storefront:reconcile
# o limitar el alcance:
php artisan storefront:reconcile --tenant=<tenant-uuid>
```

Habilitar temporalmente la ruta local:

```dotenv
STOREFRONT_DEVELOPMENT_ROUTE_ENABLED=true
STOREFRONT_DEVELOPMENT_HOSTS=localhost,127.0.0.1,::1
```

Los comandos `prepare`, `publish` y `unpublish` permanecen disponibles para
mantenimiento. Los comandos de publicación exigen la combinación exacta de
Tenant y Store activos. La administración habitual se realiza desde Store
Settings con `stores.update`.

## 7. Migración

Archivo:

```text
database/migrations/2026_10_27_000001_create_storefront_publications_table.php
```

La migración está registrada como `Ran` en el batch 2 de `pgsql/commerce`. La
consolidación Git verificó ese estado con `migrate:status` y no volvió a
ejecutarla. Para una instalación que aún no la tenga aplicada, el comando
acotado es:

```bash
docker compose exec laravel.test php artisan migrate --path=database/migrations/2026_10_27_000001_create_storefront_publications_table.php
```

Las pruebas la ejercen mediante `RefreshDatabase` exclusivamente en
`pgsql.testing/commerce_testing`, después del guard de conexión aislada.

## 8. Cobertura

La cobertura nueva valida:

- normalización, reservados, longitud y conflicto global del handle;
- publicación no accidental, prepare/publish/unpublish idempotentes;
- relación Tenant/Store exacta;
- resolución válida e inexistente;
- Tenant o Store inactivo y Store no publicada;
- aislamiento entre Stores y Tenants;
- query IDs ignorados y sesión admin no usada/no mutada;
- Host no confiable, flag apagado y producción bloqueada;
- props públicas sin contactos, IDs o identidad admin;
- locale exacto, fallback por idioma y fallback global;
- contexto request-scoped limpiado.

## 9. Dependencias para SF-02

SF-02 puede reutilizar `PublicStoreContext` y la ruta contextual, pero deberá
añadir antes de mostrar catálogo:

1. queries/DTO públicos de Listings elegibles;
2. precios/promociones/disponibilidad batch;
3. entrega pública autorizada y versionada de derivados Media;
4. metadata/canonical relativos a la Store;
5. paginación, búsqueda y categorías aisladas;
6. pruebas cross-Store/cross-Tenant y presupuesto de queries.

Subdominios, dominios personalizados, catálogo, carrito, checkout, pagos y
autenticación de compradores siguen deliberadamente fuera de SF-01.
