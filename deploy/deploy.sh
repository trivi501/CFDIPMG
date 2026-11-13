#!/usr/bin/env bash
# Actualiza la app en el servidor a la última versión de main (o a un commit de main en particular).
#
#   sudo bash deploy/deploy.sh            # último commit de origin/main
#   sudo bash deploy/deploy.sh <sha>      # un commit específico (debe estar en main)
#
# Con sudo, el script se cambia solo al usuario dueño de la carpeta de la app (p. ej. deploy) para
# no dejar archivos de root que luego la app no pueda escribir. También se puede correr directo
# como ese usuario, sin sudo.
#
# NUNCA BORRA DATOS DE LA BASE:
#   - Respalda la base antes de empezar; si el respaldo falla, no hace nada.
#   - Solo corre `php artisan migrate`. Nunca migrate:fresh/refresh/reset/rollback, db:wipe
#     ni seeders (el seeder regresaría los permisos de los roles a los de fábrica). Los permisos
#     nuevos del código llegan con `permisos:sincronizar`, que solo agrega.
#   - Si una migración pendiente trae DROP, TRUNCATE, DELETE o RENAME, se detiene y deja la versión
#     anterior funcionando. Para aplicarla a propósito (ya revisada y con respaldo):
#         sudo PERMITIR_MIGRACION_DESTRUCTIVA=1 bash deploy/deploy.sh
#   - No limpia la caché guardada en la base (no usa optimize:clear ni cache:clear).
#   - No toca el .env ni los archivos subidos: storage/ no está en git y nunca se hace `git clean`.

set -euo pipefail
umask 002

# Todo va dentro de main(): bash lee la función completa antes de ejecutarla, así que el
# `git reset` que cambia este mismo archivo a media ejecución no le afecta.
main() {
    local app_dir branch
    app_dir="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
    branch="${BRANCH:-main}"
    cd "$app_dir"

    if [[ $EUID -eq 0 ]]; then
        como_dueno "$app_dir" "$@"
    fi
    local yo
    yo="$(id -un)"

    if [[ $# -gt 0 && ! "$1" =~ ^[0-9a-f]{7,40}$ ]]; then
        echo "\"$1\" no es un SHA de commit válido." >&2
        exit 1
    fi

    paso "Trayendo cambios de origin/$branch"
    git fetch --prune origin "$branch"

    local destino anterior
    destino="$(git rev-parse --verify "${1:-origin/$branch}^{commit}")"
    anterior="$(git rev-parse HEAD)"

    if ! git merge-base --is-ancestor "$destino" "origin/$branch"; then
        echo "El commit $destino no está en origin/$branch; solo se despliega lo que ya está en $branch." >&2
        exit 1
    fi

    if [[ "$destino" == "$anterior" && -z "${FORZAR:-}" ]]; then
        echo "Ya está desplegado $destino. Nada que hacer (FORZAR=1 para reconstruir de todos modos)."
        exit 0
    fi

    paso "Respaldando la base de datos"
    ETIQUETA="antes-deploy-${destino:0:7}" APP_DIR="$app_dir" bash "$app_dir/deploy/backup-db.sh"

    paso "Modo mantenimiento"
    php artisan down --retry=15 || true

    paso "Cambiando el código a ${destino:0:7} (antes ${anterior:0:7})"
    git reset --hard "$destino"

    if ! construir; then
        revertir "$anterior" "falló la instalación o el build"
    fi

    paso "Revisando las migraciones pendientes"
    local sql
    if ! sql="$(php artisan migrate --pretend --force --no-ansi 2>&1)"; then
        echo "$sql" >&2
        revertir "$anterior" "no se pudieron revisar las migraciones"
    fi

    local destructivas
    destructivas="$(grep -Ei '\b(drop|truncate|rename)\b|delete[[:space:]]+from' <<<"$sql" || true)"
    if [[ -n "$destructivas" && -z "${PERMITIR_MIGRACION_DESTRUCTIVA:-}" ]]; then
        echo "Las migraciones pendientes borrarían o renombrarían datos:" >&2
        echo "$destructivas" >&2
        revertir "$anterior" "migración destructiva sin autorizar (si ya la revisaste: sudo PERMITIR_MIGRACION_DESTRUCTIVA=1 bash deploy/deploy.sh)"
    fi

    paso "Migrando la base de datos"
    if ! php artisan migrate --force --no-ansi; then
        echo >&2
        echo "La migración falló. La app sigue en mantenimiento." >&2
        echo "Revisa el error; el respaldo de antes del deploy está en ${BACKUP_DIR:-/var/backups/cfdi}." >&2
        echo "Cuando esté resuelto: cd $app_dir && sudo -u $yo php artisan up" >&2
        exit 1
    fi

    paso "Sincronizando permisos (solo agrega los nuevos)"
    if ! php artisan permisos:sincronizar --no-ansi; then
        echo "No se pudieron sincronizar los permisos. La app sigue en mantenimiento." >&2
        echo "Cuando esté resuelto: cd $app_dir && sudo -u $yo php artisan up" >&2
        exit 1
    fi

    paso "Cacheando configuración, rutas y vistas"
    php artisan optimize --no-ansi
    php artisan queue:restart --no-ansi

    php artisan up
    paso "Listo: desplegado ${destino:0:7}"
}

construir() {
    paso "Dependencias PHP"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress || return 1

    # Cachés de la versión anterior (en archivos; la caché de la base no se toca).
    php artisan config:clear --no-ansi || return 1
    php artisan route:clear --no-ansi || return 1
    php artisan view:clear --no-ansi || return 1
    php artisan event:clear --no-ansi || return 1

    paso "Dependencias JS y build del frontend"
    npm ci --no-audit --no-fund || return 1
    npm run build || return 1
}

# Regresa al commit anterior, lo vuelve a construir y saca la app de mantenimiento.
# La base no se tocó en ningún punto antes de llegar aquí.
revertir() {
    echo >&2
    echo "Deploy cancelado: $2. Regresando a ${1:0:7}…" >&2
    git reset --hard "$1"
    if construir && php artisan optimize --no-ansi; then
        php artisan up
        echo "La versión anterior (${1:0:7}) sigue funcionando. La base de datos no se modificó." >&2
    else
        echo "No se pudo reconstruir la versión anterior; la app sigue en mantenimiento." >&2
    fi
    exit 1
}

paso() {
    printf '\n==> %s\n' "$1"
}

# Corrido con sudo: prepara la carpeta de respaldos y vuelve a lanzar el script como el dueño de la
# carpeta de la app, con las mismas opciones. Nunca sigue como root.
como_dueno() {
    local app_dir="$1"
    shift

    local dueno
    dueno="$(stat -c %U "$app_dir")"
    if [[ "$dueno" == "root" ]]; then
        echo "La carpeta $app_dir es de root; la app (www-data) no podría escribir en ella." >&2
        echo "Dásela a un usuario normal, p. ej.: sudo chown -R deploy:www-data $app_dir" >&2
        exit 1
    fi

    local backup_dir="${BACKUP_DIR:-/var/backups/cfdi}"
    if [[ ! -d "$backup_dir" ]]; then
        install -d -o "$dueno" -g "$dueno" -m 700 "$backup_dir"
    fi

    # sudo limpia el entorno: se pasan solo las opciones de este script que vengan definidas.
    local opciones=() var
    for var in APP_DIR BRANCH BACKUP_DIR FORZAR PERMITIR_MIGRACION_DESTRUCTIVA; do
        if [[ -n "${!var:-}" ]]; then
            opciones+=("$var=${!var}")
        fi
    done

    echo "Corriendo como $dueno (dueño de $app_dir)…"
    exec sudo -u "$dueno" -H env "${opciones[@]}" bash "$app_dir/deploy/deploy.sh" "$@"
}

main "$@"
