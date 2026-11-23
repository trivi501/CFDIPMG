# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es esto

Sistema interno de facturación: recibe pagos de otro sistema (municipal, predial) y de un sistema
de cobranza genérico, y permite convertirlos en CFDI (facturas fiscales mexicanas) reales vía
Facturapi.io. Laravel 13 + Inertia.js + React 19/TypeScript (starter kit oficial de Laravel, con
Fortify para auth y Wayfinder para rutas tipadas), `spatie/laravel-permission` para roles, MySQL.

## Comandos

```bash
composer run dev              # server + queue + vite, todo junto (usar esto para desarrollar)
php artisan serve             # solo el servidor Laravel
npm run dev                   # solo Vite

composer run test             # config:clear + pint --test + phpstan + artisan test (lo que corre CI)
php artisan test                              # solo PHPUnit
php artisan test --filter=nombre_del_test     # un solo test
php artisan test tests/Feature/OpenInvoiceTest.php   # un solo archivo

./vendor/bin/pint --parallel          # formatear PHP
./vendor/bin/pint --parallel --test   # solo verificar, sin escribir
./vendor/bin/phpstan analyse          # análisis estático (nivel 7, ver phpstan.neon)

npx tsc --noEmit               # chequeo de tipos TS
npx eslint resources/js        # lint (--fix para autoarreglar)
npx prettier --write resources/js/...   # formatear

npm run build                  # build de producción (Vite)

php artisan migrate            # correr migraciones (MySQL local vía Laragon, ver .env)
php artisan db:seed            # roles/permisos + usuario admin (ver DatabaseSeeder)
php artisan permisos:sincronizar [--pretend]   # agrega permisos/roles nuevos del catálogo sin quitar nada
php artisan areas:revisar     # solo lectura: qué áreas son privadas y quién ve las facturas de todos
php artisan app:create-api-client "Nombre"   # genera una API key para que un sistema externo empuje recibos
php artisan wayfinder:generate --with-form   # regenerar las acciones TS tras cambiar rutas/controllers
```

Los tests usan SQLite en memoria (`phpunit.xml`), no la base de datos de desarrollo — no hace
falta tener MySQL corriendo para `php artisan test`.

## Arquitectura

### Dos integraciones externas — no confundirlas

- **Facturapi.io** (`app/Services/Facturapi/FacturapiClient.php`): el proveedor de CFDI. Recibe
  clientes y conceptos, regresa el timbrado (UUID, folio, PDF/XML). Basic Auth con
  `FACTURAPI_SECRET_KEY`.
- **Pagos municipales** (`app/Services/Pagos/PagosClient.php`): el sistema externo de origen real
  de los pagos (predial), consultado por folio vía `GET /api/pagos/{folio}` con Bearer token
  (`PAGOS_API_TOKEN`). No tiene nada que ver con Facturapi — es de donde *vienen* los datos que
  luego se facturan.

### Flujo de datos: de un pago a una factura

Hay tres formas de que un `PaymentReceipt` llegue a facturarse, y todas terminan en el mismo sitio:

1. **Push**: un sistema externo llama `POST /api/v1/receipts` (autenticado con `X-Api-Key` propio,
   ver `ApiClient` / `ApiKeyAuth` middleware) → `ReceiptIngestController`.
2. **Pull**: staff busca un folio en `/receipts/lookup` → `ReceiptLookupController` consulta
   `PagosClient` y crea el recibo (idempotente por `source_system` + `external_id`).
3. **Factura abierta**: no hay recibo — `/invoices/create` genera la factura directo con
   `InvoiceService::generate(null, ...)`.

En los dos primeros casos, `ReceiptController::create()/store()` (`GET|POST /receipts/{id}/invoice`)
arma y envía la factura. `InvoiceService::generate()` siempre hace el trabajo real: sincroniza el
cliente con Facturapi si hace falta, arma el payload CFDI, y guarda el `Invoice` resultante
(`status: valid|failed`, nunca deja excepciones sin capturar hacia el controller).

Un recibo ya facturado (`status: invoiced`) no se puede volver a facturar — tanto el `GET` como el
`POST` de `/receipts/{id}/invoice` redirigen a la factura existente en vez de mostrar el formulario.

### IVA: solo un caso es distinto

Los montos que vienen de **Pagos municipales** ya incluyen IVA (`tax_included: true` hacia
Facturapi); todo lo demás (facturas abiertas, catálogo de productos) es precio sin IVA + 16%
aparte. La constante `PaymentReceipt::SOURCE_PAGOS_MUNICIPALES` es la única fuente de verdad para
esta distinción — la usan `InvoiceService`, `ReceiptController` (para las props del formulario) y
el frontend (`receipts/invoice.tsx` cambia el label y el resumen de totales según
`taxIncludedInPrice`).

### Roles y permisos

`spatie/laravel-permission`. El catálogo de permisos y roles de fábrica (Admin, Facturación, Apoyos,
Consulta) vive en `App\Support\CatalogoPermisos`: un permiso nuevo se agrega ahí, con su texto en
español en `ETIQUETAS` (la pantalla de Roles lo muestra agrupado; un test exige que exista). Lo usan
`RolesAndPermissionsSeeder` (instalación nueva; deja los roles exactamente de fábrica) y
`php artisan permisos:sincronizar` (servidores en uso: solo agrega permisos/roles nuevos, nunca quita;
lo corre `deploy/deploy.sh`). Las rutas usan middleware `permission:xxx.yyy` (ver `routes/web.php`); los recursos
usan `->middlewareFor([...], 'permission:...')` para separar permisos de lectura (`*.view`) de
escritura (`*.manage` / `*.create`). En el frontend, `HandleInertiaRequests` comparte
`auth.permissions`/`auth.roles`, y el hook `usePermissions()` (`resources/js/hooks/use-permissions.ts`)
expone `can()`/`hasRole()` para ocultar UI condicionalmente — eso es solo UX, la autorización real
vive en el middleware.

### Visibilidad por área (regidurías y secretarías)

Los apoyos y las **facturas de compra** son de quien los captura y de su área (`users.area_id`):
cada regidor ve solo lo suyo y lo de su área; `apoyos.view-all` (Admin, Facturación) ve todo. En un
área **privada** (`areas.privada`, p. ej. Cabildo, donde están juntos varios regidores y sus asistentes)
cada integrante ve solo lo suyo: usa `$user->areaCompartida()` (null si no comparte) en vez de leer
`area_id` directo. Para
facturas de compra usa siempre el scope `FacturaCompra::visiblePara($user)` (lista, XML, inventario
que se ofrece al capturar apoyos, paquetes); `reserveStock()` rechaza artículos de facturas que el
usuario no ve. Las facturas sin `user_id` ni `area_id` (subidas antes de que tuvieran dueño) solo las
ve `apoyos.view-all` hasta que se les asigna área en la pantalla de Facturas de compra.

Para apoyos, el equivalente es el scope `Apoyo::visiblePara($user)`; lo usan la pantalla de Apoyos y
las gráficas del dashboard (`DashboardController`: facturas vigentes de apoyos por área, por usuario en
las áreas privadas y por mes), así que cada quien solo ve en las gráficas lo que ve en Apoyos.

Un apoyo **rechazado** ya devolvió su inventario: editarlo o eliminarlo no lo devuelve otra vez, y
`reabrir` (regresar a preparado) lo vuelve a apartar.

### Bitácora de auditoría

`App\Support\RegistroAuditoria` (registrado en `AppServiceProvider`) escribe en la tabla `auditoria`
sin tocar controladores: eventos de Eloquent de todos los modelos (alta, modificación y eliminación
con valores antes/después), cualquier INSERT/UPDATE/DELETE que no venga de un modelo como "cambio
masivo" con la instrucción, e inicios/cierres de sesión e intentos fallidos. Las descargas (PDF/XML
de facturas, XML de compras, documentos de apoyos) las registra el middleware
`RegistrarConsultaAuditoria` según su lista `RUTAS`: **al agregar una ruta que descargue o exporte,
hay que añadirla ahí**. Contraseñas, tokens y atributos ocultos quedan como "[oculto]"; nunca
interrumpe la operación (si falla, solo deja un warning en el log). Pantalla `/auditoria`, solo para
el rol Admin (por rol, no por permiso, para que no se pueda otorgar). Al agregar una tabla nueva, su
nombre legible va en `AuditoriaController::MODULOS`. La app guarda en UTC y la pantalla muestra y
filtra en hora de Ciudad de México.

### Visor de logs

`/log-viewer` (paquete `opcodesio/log-viewer`, config en `config/log-viewer.php`) muestra los logs de
`storage/logs`. Solo el rol Admin, por el gate `viewLogViewer` de `AppServiceProvider` (por rol, igual
que la bitácora); los gates `deleteLogFile`/`deleteLogFolder` siempre niegan, así que nadie borra logs
desde la pantalla. Su índice va en caché de archivos (`cache_driver`), no en la base. Sus rutas de API
no pasan por el grupo `web`: por eso `RegistrarConsultaAuditoria` está en `api_middleware` para que las
descargas de logs queden en la bitácora. El enlace del menú es una `NavItem` con `externa: true` porque
no es una página de Inertia. En producción, `APP_URL` debe coincidir con el dominio real o la API del
visor responde 401.

### Wayfinder (rutas/acciones tipadas)

`resources/js/actions/**` y `resources/js/routes/**` están **gitignored** y se regeneran solos
(el plugin de Vite lo hace en `dev`/`build`; si se corre `php artisan route:list` o similar sin
Vite corriendo, hay que regenerarlos a mano con `php artisan wayfinder:generate --with-form`).
Al agregar una ruta nueva con parámetro dinámico (`/invoices/{invoice}`), las rutas literales como
`/invoices/create` deben registrarse **antes** en `routes/web.php` o el binding de modelo intenta
resolver "create" como ID.

Convención de formularios en React: `<Form {...Controller.action.form()}>` (de Wayfinder) para
formularios simples de campos fijos; `useForm()` de Inertia cuando hay arrays dinámicos (líneas de
conceptos de una factura, como en `receipts/invoice.tsx` e `invoices/create.tsx`).

### Variables de entorno relevantes

`FACTURAPI_SECRET_KEY`, `FACTURAPI_BASE_URL`, `PAGOS_API_BASE_URL`, `PAGOS_API_TOKEN` — todas en
`.env` únicamente, nunca en código ni committeadas (ver `config/services.php`).

## Nota de entorno (Windows/Laragon)

Si `Http::` falla contra un host real con `cURL error 60: SSL certificate problem`, no asumas que
el certificado del servidor está mal — puede ser que el `cacert.pem` de PHP (la ruta que apunta
`curl.cainfo` en `php.ini`) no tenga el root CA correspondiente. Comparar la cadena que ve Windows
(`certutil`/PowerShell `X509Chain`) contra el bundle de PHP y agregar el root que falte suele
arreglarlo sin tocar código.
