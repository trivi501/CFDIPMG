#!/usr/bin/env bash
# Respaldo comprimido de la base de datos de la app (mysqldump | gzip).
#
# Lo usan deploy.sh (antes de cada deploy) y el cron diario que instala setup-server.sh.
# Solo lee la base: nunca la modifica. Lee las credenciales del .env de la app.
#
# Variables opcionales:
#   APP_DIR     carpeta de la app            (por omisión, la carpeta padre de este script)
#   BACKUP_DIR  dónde guardar los respaldos  (por omisión /var/backups/cfdi)
#   CONSERVAR   cuántos respaldos guardar    (por omisión 60; los más viejos se eliminan)
#   ETIQUETA    sufijo para el nombre del archivo, p. ej. "antes-deploy"
#
# Respaldo a mano:        cd /var/www/cfdi && sudo -u deploy bash deploy/backup-db.sh
# Restaurar un respaldo:  sudo zcat archivo.sql.gz | sudo mysql NOMBRE_BASE

set -euo pipefail
umask 077

main() {
    local app_dir backup_dir conservar
    app_dir="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
    backup_dir="${BACKUP_DIR:-/var/backups/cfdi}"
    conservar="${CONSERVAR:-60}"

    if [[ ! -f "$app_dir/.env" ]]; then
        echo "No existe $app_dir/.env" >&2
        exit 1
    fi

    local db host port user pass
    db="$(env_get "$app_dir/.env" DB_DATABASE)"
    host="$(env_get "$app_dir/.env" DB_HOST)"
    port="$(env_get "$app_dir/.env" DB_PORT)"
    user="$(env_get "$app_dir/.env" DB_USERNAME)"
    pass="$(env_get "$app_dir/.env" DB_PASSWORD)"

    if [[ -z "$db" || -z "$user" ]]; then
        echo "Faltan DB_DATABASE o DB_USERNAME en el .env" >&2
        exit 1
    fi

    mkdir -p "$backup_dir"

    # La contraseña va en un archivo temporal (0600) para que no aparezca en la lista de procesos.
    # Global (no local): el trap de EXIT corre cuando main() ya terminó.
    CNF="$(mktemp)"
    trap 'rm -f "$CNF"' EXIT
    local cnf="$CNF"
    printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\nport="%s"\n' \
        "$user" "$pass" "${host:-127.0.0.1}" "${port:-3306}" > "$cnf"

    local archivo
    archivo="$backup_dir/${db}-$(date +%Y%m%d-%H%M%S)${ETIQUETA:+-$ETIQUETA}.sql.gz"

    mysqldump --defaults-extra-file="$cnf" \
        --single-transaction --quick --routines --triggers --no-tablespaces \
        "$db" | gzip > "$archivo.tmp"

    # Un respaldo solo cuenta si el gzip está sano y mysqldump llegó al final.
    if ! gzip -t "$archivo.tmp" || ! gunzip -c "$archivo.tmp" | tail -n 1 | grep -q 'Dump completed'; then
        rm -f "$archivo.tmp"
        echo "El respaldo quedó incompleto." >&2
        exit 1
    fi

    mv "$archivo.tmp" "$archivo"
    echo "Respaldo listo: $archivo ($(du -h "$archivo" | cut -f1))"

    # Solo se eliminan respaldos viejos de esta misma base, nunca otros archivos.
    find "$backup_dir" -maxdepth 1 -type f -name "${db}-*.sql.gz" -printf '%T@ %p\n' \
        | sort -rn | tail -n +"$((conservar + 1))" | cut -d' ' -f2- | xargs -r rm -f --
}

# Valor de una variable del .env, sin comillas.
env_get() {
    local valor
    valor="$(grep -E "^$2=" "$1" | tail -n 1 | cut -d= -f2-)"
    valor="${valor%$'\r'}"
    valor="${valor#\"}"; valor="${valor%\"}"
    valor="${valor#\'}"; valor="${valor%\'}"
    printf '%s' "$valor"
}

main "$@"
