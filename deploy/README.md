# Deploy en un VPS Linux

Todo lo de esta carpeta está pensado para **Ubuntu 24.04** con Nginx, PHP-FPM 8.3, MySQL 8 y Supervisor.

| Archivo | Para qué |
| --- | --- |
| `setup-server.sh` | Prepara el servidor y hace la primera instalación. Se corre una vez (se puede repetir sin riesgo). |
| `deploy.sh` | Actualiza la app a lo último de `main`. Se corre a mano en el servidor. |
| `backup-db.sh` | Respaldo de la base. Lo usan `deploy.sh` y un cron diario. |
| `nginx.conf`, `supervisor.conf` | Plantillas que instala `setup-server.sh`. |

Los deploys son solo manuales: hacer push a `main` no cambia nada en el servidor.

**Todos los comandos de esta guía se corren en el servidor con `sudo`.** Los de `php artisan` usan
`sudo -u deploy`, donde `deploy` es el usuario dueño de la carpeta de la app: nunca corras artisan,
composer ni npm como root, porque dejan archivos que la app no puede escribir. Si tu carpeta es de
otro usuario o está en otra ruta (p. ej. `/var/www/cfdi.gpe.gob.mx`), cambia `deploy` y
`/var/www/cfdi` en los comandos. Para saber de quién es: `stat -c %U /var/www/cfdi`.

## La base de datos nunca se borra

- Antes de cada deploy se hace un respaldo completo; **si el respaldo falla, el deploy no hace nada**.
- Solo se corre `php artisan migrate`. Nunca `migrate:fresh`, `refresh`, `reset`, `rollback`, `db:wipe`
  ni seeders.
- Si una migración nueva trae `DROP`, `TRUNCATE`, `DELETE` o `RENAME`, el deploy **se detiene y deja
  funcionando la versión anterior**, sin tocar la base. Para aplicarla a propósito, ya revisada:
  `sudo PERMITIR_MIGRACION_DESTRUCTIVA=1 bash deploy/deploy.sh`
- El seeder solo corre en la primera instalación (con la tabla `users` vacía). Volver a correrlo
  regresaría los permisos de los roles a los de fábrica.
- Los permisos nuevos que traiga el código se agregan en cada deploy con
  `php artisan permisos:sincronizar`, que nunca quita nada (ver abajo).
- El `.env` y los archivos subidos (`storage/`) no se tocan.
- Respaldo diario a las 2:30 a. m. en `/var/backups/cfdi` (se guardan los últimos 60).

Ver los respaldos y restaurar uno (reemplaza el contenido actual de la base por el del respaldo):

```bash
sudo ls -lh /var/backups/cfdi
```

```bash
sudo zcat /var/backups/cfdi/cfdipmg-AAAAMMDD-HHMMSS.sql.gz | sudo mysql cfdipmg
```

## 1. Primera instalación

En el servidor, como un usuario con sudo:

```bash
curl -fsSLO https://raw.githubusercontent.com/trivi501/CFDIPMG/main/deploy/setup-server.sh
```

```bash
sudo DOMAIN=facturas.tu-dominio.gob.mx bash setup-server.sh
```

Al terminar te dice lo que falta:

1. Apuntar el DNS del dominio al servidor y activar HTTPS con `sudo certbot --nginx -d tu-dominio`.
   Sin HTTPS no se puede iniciar sesión (la cookie de sesión lo exige).
2. Poner en `/var/www/cfdi/.env` las claves de producción: `FACTURAPI_SECRET_KEY` (la **live**),
   `PAGOS_API_BASE_URL` y `PAGOS_API_TOKEN`. Luego
   `sudo -u deploy bash -c 'cd /var/www/cfdi && php artisan config:cache'`.
3. Entrar con el admin que crea `database/seeders/DatabaseSeeder.php` y **cambiar su contraseña**.

La contraseña inicial de los usuarios nuevos quedó en `USUARIOS_PASSWORD_INICIAL` del `.env`.

## 2. Cada actualización

Primero sube tus cambios a `main` y revisa que los tests de GitHub (Actions → tests) estén en verde.
Luego, en el servidor:

```bash
cd /var/www/cfdi && sudo bash deploy/deploy.sh
```

Con `sudo`, el script se cambia solo al usuario dueño de la carpeta; no hace falta `-u deploy`.

Para desplegar un commit específico de `main` (por ejemplo, regresar a uno anterior), pásale su SHA:

```bash
cd /var/www/cfdi && sudo bash deploy/deploy.sh 9c2b380
```

Regresar el código a un commit anterior no revierte migraciones: la base se queda como está.

## Sincronizar permisos

Los permisos y los roles de fábrica están en `app/Support/CatalogoPermisos.php`. Cada deploy corre
`php artisan permisos:sincronizar`, que:

- crea los permisos y roles del catálogo que falten en la base;
- asigna cada permiso **recién creado** a los roles que lo traen en el catálogo (Admin recibe todos);
- **no toca** los permisos que ya existían: si se lo quitaste a un rol desde la pantalla de Roles,
  así se queda;
- nunca quita permisos, roles ni asignaciones; los que sobren en la base solo los reporta.

Para ver qué haría sin cambiar nada, o correrlo a mano:

```bash
sudo -u deploy bash -c 'cd /var/www/cfdi && php artisan permisos:sincronizar --pretend'
```

```bash
sudo -u deploy bash -c 'cd /var/www/cfdi && php artisan permisos:sincronizar'
```

## Si algo sale mal

- **Falló el build o la instalación de dependencias**: el deploy regresa solo a la versión anterior
  y la base queda intacta.
- **Falló una migración**: la app se queda en mantenimiento. Revisa el error en la salida; el
  respaldo de justo antes está en `/var/backups/cfdi` (termina en `antes-deploy-<commit>`). Cuando
  esté resuelto: `cd /var/www/cfdi && sudo -u deploy php artisan up`.
- **En el navegador: `… is not a function` en una ruta (p. ej. `facturasXml`)**: el frontend se
  compiló con la caché de rutas vieja. Pasa al actualizar a mano (`git pull` + `npm run build`) en
  vez de usar `deploy.sh`. Arreglo:
  `cd /var/www/cfdi && sudo -u deploy bash -c 'php artisan route:clear && php artisan config:clear && npm run build && php artisan optimize'`
- Logs: `sudo tail -n 100 /var/www/cfdi/storage/logs/laravel-$(date +%F).log` (app),
  `sudo tail -n 100 /var/www/cfdi/storage/logs/worker.log` (cola),
  `sudo tail -n 100 /var/log/nginx/error.log` (Nginx).
