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

`spatie/laravel-permission`, sembrados en `RolesAndPermissionsSeeder` (Admin, Facturación,
Consulta). Las rutas usan middleware `permission:xxx.yyy` (ver `routes/web.php`); los recursos
usan `->middlewareFor([...], 'permission:...')` para separar permisos de lectura (`*.view`) de
escritura (`*.manage` / `*.create`). En el frontend, `HandleInertiaRequests` comparte
`auth.permissions`/`auth.roles`, y el hook `usePermissions()` (`resources/js/hooks/use-permissions.ts`)
expone `can()`/`hasRole()` para ocultar UI condicionalmente — eso es solo UX, la autorización real
vive en el middleware.

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
