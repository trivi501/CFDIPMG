#!/usr/bin/env bash
# Prepara un VPS Ubuntu 24.04 para la app y hace la primera instalación.
#
#   sudo DOMAIN=facturas.ejemplo.gob.mx bash setup-server.sh
#
# Se puede volver a correr sin riesgo: NUNCA borra la base de datos, sus usuarios, el .env ni los
# archivos subidos. La base y el usuario de MySQL solo se crean si no existen; el .env solo se crea
# si no existe; el seeder solo corre si la tabla users está vacía.
#
# Variables (DOMAIN es obligatoria):
#   DOMAIN        dominio de la app, p. ej. facturas.ejemplo.gob.mx
#   APP_DIR       /var/www/cfdi
#   DEPLOY_USER   deploy        (dueño del código; con él se corren los deploys)
#   REPO_URL      https://github.com/trivi501/CFDIPMG.git
#   BRANCH        main
#   DB_NAME       cfdipmg
#   DB_USER       cfdipmg
#   DB_PASSWORD   solo si el usuario de MySQL ya existe y todavía no hay .env
#   BACKUP_DIR    /var/backups/cfdi

set -euo pipefail

DOMAIN="${DOMAIN:?Define DOMAIN, p. ej.: sudo DOMAIN=facturas.ejemplo.gob.mx bash setup-server.sh}"
APP_DIR="${APP_DIR:-/var/www/cfdi}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
REPO_URL="${REPO_URL:-https://github.com/trivi501/CFDIPMG.git}"
BRANCH="${BRANCH:-main}"
DB_NAME="${DB_NAME:-cfdipmg}"
DB_USER="${DB_USER:-cfdipmg}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/cfdi}"
PHP=8.3

paso() { printf '\n==> %s\n' "$1"; }
como_deploy() { sudo -u "$DEPLOY_USER" -H bash -c "umask 002; cd '$APP_DIR' && $1"; }

if [[ $EUID -ne 0 ]]; then
    echo "Córrelo con sudo." >&2
    exit 1
fi

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ || ! "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "DB_NAME y DB_USER solo pueden tener letras, números y guion bajo." >&2
    exit 1
fi

paso "Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx mysql-server supervisor git unzip curl ca-certificates gnupg openssl \
    certbot python3-certbot-nginx composer \
    "php$PHP-fpm" "php$PHP-cli" "php$PHP-mysql" "php$PHP-intl" "php$PHP-mbstring" "php$PHP-xml" \
    "php$PHP-zip" "php$PHP-gd" "php$PHP-curl" "php$PHP-bcmath" "php$PHP-sqlite3"

if ! command -v node >/dev/null || [[ "$(node -v)" != v22.* ]]; then
    paso "Node.js 22 (repositorio oficial de NodeSource)"
    install -d -m 0755 /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list
    apt-get update
    apt-get install -y nodejs
fi

paso "PHP: límites de subida y tiempo (documentos de 10 MB, ZIPs de hasta 200 XML)"
cat > "/etc/php/$PHP/fpm/conf.d/99-cfdi.ini" <<'INI'
upload_max_filesize = 10M
post_max_size = 60M
max_execution_time = 300
memory_limit = 256M
INI

# Archivos que crea PHP-FPM (logs, vistas compiladas) con permiso de escritura para el grupo, así
# el usuario deploy y www-data pueden escribir los mismos archivos de storage/.
install -d "/etc/systemd/system/php$PHP-fpm.service.d"
printf '[Service]\nUMask=0002\n' > "/etc/systemd/system/php$PHP-fpm.service.d/umask.conf"
systemctl daemon-reload
systemctl restart "php$PHP-fpm"

paso "Usuario $DEPLOY_USER"
if ! id "$DEPLOY_USER" &>/dev/null; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
grep -qx 'umask 002' "/home/$DEPLOY_USER/.profile" 2>/dev/null \
    || echo 'umask 002' >> "/home/$DEPLOY_USER/.profile"

paso "Base de datos (solo se crea lo que no existe)"
existe_usuario="$(mysql -Nse "SELECT COUNT(*) FROM mysql.user WHERE user='$DB_USER' AND host='localhost'")"
if [[ "$existe_usuario" == "0" ]]; then
    DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 32)}"
    mysql -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'"
    echo "Usuario MySQL $DB_USER creado."
else
    echo "El usuario MySQL $DB_USER ya existe: no se cambia su contraseña."
fi
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES"

paso "Código en $APP_DIR"
install -d -o "$DEPLOY_USER" -g www-data -m 2775 "$APP_DIR"
if [[ ! -d "$APP_DIR/.git" ]]; then
    sudo -u "$DEPLOY_USER" -H git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
else
    echo "Ya hay un repositorio: lo actualiza deploy.sh."
fi

paso "Archivo .env"
if [[ ! -f "$APP_DIR/.env" ]]; then
    if [[ -z "$DB_PASSWORD" ]]; then
        echo "El usuario MySQL $DB_USER ya existía y no hay .env: vuelve a correr con DB_PASSWORD=<su contraseña>." >&2
        exit 1
    fi
    como_deploy "cp .env.example .env"
    poner() {
        local clave="$1" valor="$2"
        if grep -qE "^#? ?$clave=" "$APP_DIR/.env"; then
            sed -i -E "s|^#? ?$clave=.*|$clave=\"$valor\"|" "$APP_DIR/.env"
        else
            printf '%s="%s"\n' "$clave" "$valor" >> "$APP_DIR/.env"
        fi
    }
    poner APP_ENV production
    poner APP_DEBUG false
    poner APP_URL "https://$DOMAIN"
    poner LOG_STACK daily
    poner LOG_LEVEL info
    poner DB_DATABASE "$DB_NAME"
    poner DB_USERNAME "$DB_USER"
    poner DB_PASSWORD "$DB_PASSWORD"
    poner SESSION_SECURE_COOKIE true
    poner USUARIOS_PASSWORD_INICIAL "$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 14)Aa1!"
    NUEVO_ENV=1
else
    echo "Ya existe: no se toca."
fi
chown "$DEPLOY_USER:www-data" "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"

paso "Permisos de storage/ y bootstrap/cache/"
chown -R "$DEPLOY_USER:www-data" "$APP_DIR"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} +
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} +

paso "Carpeta de respaldos $BACKUP_DIR"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 700 "$BACKUP_DIR"

paso "Dependencias, build y migraciones"
# Con la base sin tablas no hay datos que perder: las migraciones antiguas que quitan columnas
# viejas pueden correr. Si ya tiene tablas, deploy.sh aplica su revisión normal.
tablas="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"
permitir=""
if [[ "$tablas" == "0" ]]; then
    permitir="PERMITIR_MIGRACION_DESTRUCTIVA=1"
fi
if [[ -n "${NUEVO_ENV:-}" ]]; then
    como_deploy "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress && php artisan key:generate --force"
fi
como_deploy "FORZAR=1 BACKUP_DIR='$BACKUP_DIR' $permitir bash deploy/deploy.sh"

paso "Datos iniciales (roles, permisos y usuario admin)"
usuarios="$(mysql -Nse "SELECT COUNT(*) FROM \`$DB_NAME\`.users")"
if [[ "$usuarios" == "0" ]]; then
    como_deploy "php artisan db:seed --force --no-ansi"
    SEMBRADO=1
else
    echo "Ya hay $usuarios usuario(s): no se corre el seeder."
fi

paso "Nginx"
sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_DIR__|$APP_DIR|g" -e "s|__PHP__|$PHP|g" \
    "$APP_DIR/deploy/nginx.conf" > /etc/nginx/sites-available/cfdi
ln -sf /etc/nginx/sites-available/cfdi /etc/nginx/sites-enabled/cfdi
nginx -t
systemctl reload nginx

paso "Worker de la cola (Supervisor)"
sed -e "s|__APP_DIR__|$APP_DIR|g" "$APP_DIR/deploy/supervisor.conf" > /etc/supervisor/conf.d/cfdi-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl restart cfdi-worker || true

paso "Respaldo diario de la base (2:30 a. m.)"
cat > /etc/cron.d/cfdi-backup <<CRON
30 2 * * * $DEPLOY_USER BACKUP_DIR=$BACKUP_DIR /bin/bash $APP_DIR/deploy/backup-db.sh >> $BACKUP_DIR/respaldos.log 2>&1
CRON
chmod 644 /etc/cron.d/cfdi-backup

cat <<FIN

============================================================
Servidor listo.

Falta (una sola vez):
  1. Apunta el DNS de $DOMAIN a este servidor y activa HTTPS:
       sudo certbot --nginx -d $DOMAIN
     (hasta entonces el inicio de sesión no funciona: la cookie de sesión exige HTTPS)
  2. Pon las claves de producción en $APP_DIR/.env:
       FACTURAPI_SECRET_KEY, PAGOS_API_BASE_URL, PAGOS_API_TOKEN
     y luego:  sudo -u $DEPLOY_USER bash -c 'cd $APP_DIR && php artisan config:cache'
FIN
if [[ -n "${SEMBRADO:-}" ]]; then
    echo "  3. Entra con el admin del seeder (database/seeders/DatabaseSeeder.php) y CAMBIA su contraseña."
fi
echo "============================================================"
