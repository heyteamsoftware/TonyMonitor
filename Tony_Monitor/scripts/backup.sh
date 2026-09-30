#!/bin/bash
# Genera un backup completo del servidor: todas las webs de /var/www/html,
# todas las bases de datos, la configuracion de Apache y los crontabs.
# Pensado para ejecutarse como root (via sudo desde backup.php, restringido
# a este script exacto en /etc/sudoers.d/tonymonitor-backup, o directamente
# desde el crontab de root para el backup automatico programado).

BACKUP_DIR="/var/backups/tonymonitor"
STATUS_FILE="${BACKUP_DIR}/status.json"
NOTIFY_FILE="/var/www/html/Tony_Monitor/config/backup_notify.json"
STAMP=$(date +%Y-%m-%d_%H-%M-%S)
WORKDIR="${BACKUP_DIR}/tmp/backup_${STAMP}"
OUTFILE="${BACKUP_DIR}/backup_TonyMonitor_${STAMP}.tar.gz"

mkdir -p "${BACKUP_DIR}"

write_status() {
    # write_status <state> <message>
    cat > "${STATUS_FILE}" <<EOF
{"state": "$1", "message": "$2", "started_at": "${STAMP}", "updated_at": "$(date '+%Y-%m-%d %H:%M:%S')"}
EOF
}

send_notification() {
    # send_notification <success|failure> <message>
    if [ ! -f "${NOTIFY_FILE}" ]; then return; fi
    local webhook
    webhook=$(grep -o '"webhook_url"\s*:\s*"[^"]*"' "${NOTIFY_FILE}" | sed -E 's/.*"webhook_url"\s*:\s*"([^"]*)"/\1/')
    if [ -z "${webhook}" ]; then return; fi
    local status_word="$1"
    local msg="$2"
    curl -s -m 10 -X POST -H "Content-Type: application/json" \
      -d "{\"text\": \"Backup Tony Monitor [${status_word}]: ${msg}\"}" \
      "${webhook}" > /dev/null 2>&1 || true
}

fail() {
    write_status "error" "$1"
    send_notification "FALLO" "$1"
    rm -rf "${WORKDIR}"
    echo "ERROR: $1" >&2
    exit 1
}

trap 'rm -rf "${WORKDIR}"' EXIT
write_status "running" "Iniciando backup..."

set -o pipefail

# 1) Todas las webs completas, tal cual esten en el momento del backup.
#    Copiar el directorio entero (no listar webs una a una) para que
#    cualquier sitio nuevo que se añada en el futuro se incluya solo.
mkdir -p "${WORKDIR}/www_html"
write_status "running" "Copiando /var/www/html..."
cp -a /var/www/html/. "${WORKDIR}/www_html/" || fail "No se pudo copiar /var/www/html"

# 2) Todas las bases de datos, en un unico dump consistente.
#    debian.cnf trae credenciales de mantenimiento con privilegios completos,
#    solo legibles por root: no hace falta guardar ninguna contraseña nueva.
write_status "running" "Volcando bases de datos..."
mysqldump --defaults-file=/etc/mysql/debian.cnf \
  --all-databases --single-transaction --quick --routines --events --triggers \
  > "${WORKDIR}/all_databases.sql" || fail "mysqldump fallo al volcar las bases de datos"

if [ ! -s "${WORKDIR}/all_databases.sql" ]; then
    fail "El volcado de bases de datos ha quedado vacio, algo fue mal"
fi

# 3) Configuracion de Apache, para poder reconstruir el servidor igual.
write_status "running" "Copiando configuracion de Apache..."
mkdir -p "${WORKDIR}/apache_config"
cp -a /etc/apache2/sites-available "${WORKDIR}/apache_config/"
cp -a /etc/apache2/apache2.conf "${WORKDIR}/apache_config/"
if [ -d /etc/apache2/mods-enabled ]; then
  cp -a /etc/apache2/mods-enabled "${WORKDIR}/apache_config/"
fi

# 4) Crontabs de todos los usuarios que tengan alguno.
write_status "running" "Copiando crontabs..."
mkdir -p "${WORKDIR}/crontabs"
for user in $(cut -f1 -d: /etc/passwd); do
  crontab -u "${user}" -l > "${WORKDIR}/crontabs/${user}.cron" 2>/dev/null || true
done
find "${WORKDIR}/crontabs" -empty -delete

# 5) Manifiesto con toda la info necesaria para restaurar o migrar.
PHP_VERSION=$(php -v 2>/dev/null | head -1 || echo "desconocida")
APACHE_VERSION=$(apache2 -v 2>/dev/null | head -1 || echo "desconocida")
MARIADB_VERSION=$(mysql --version 2>/dev/null || echo "desconocida")

cat > "${WORKDIR}/MANIFEST.txt" <<EOF
BACKUP DEL SERVIDOR - Tony Monitor
===================================
Fecha y hora del backup: ${STAMP} (formato: AAAA-MM-DD_HH-MM-SS)
Servidor origen: $(hostname) ($(hostname -I 2>/dev/null | awk '{print $1}'))

Versiones del servidor origen:
  - ${PHP_VERSION}
  - ${APACHE_VERSION}
  - ${MARIADB_VERSION}

CONTENIDO DE ESTE BACKUP
-------------------------
www_html/          -> Copia completa de /var/www/html (todas las webs y apps)
all_databases.sql  -> Volcado de TODAS las bases de datos MariaDB/MySQL
apache_config/     -> sites-available, apache2.conf y mods-enabled de Apache
crontabs/          -> Un archivo por cada usuario del sistema con crontab configurado

COMO RESTAURAR EN UN SERVIDOR NUEVO (Ubuntu + Apache + PHP + MariaDB)
------------------------------------------------------------------------
1. Instalar Apache, PHP y MariaDB en el servidor nuevo.
2. Copiar el contenido de www_html/ a /var/www/html/
     sudo cp -a www_html/. /var/www/html/
     sudo chown -R www-data:www-data /var/www/html
3. Importar todas las bases de datos:
     sudo mysql < all_databases.sql
4. Copiar la configuracion de Apache:
     sudo cp -a apache_config/sites-available/. /etc/apache2/sites-available/
     sudo cp apache_config/apache2.conf /etc/apache2/apache2.conf
     (revisar los sitios habilitados con: sudo a2ensite <nombre> && sudo systemctl reload apache2)
5. Restaurar los crontabs necesarios:
     crontab -u <usuario> crontabs/<usuario>.cron
6. Reiniciar servicios:
     sudo systemctl restart apache2 mariadb

NOTA: revisa antes de importar la base de datos que no exista ya contenido
en el servidor destino, ya que este dump reemplaza tablas existentes con el
mismo nombre.
EOF

# 6) Empaquetar todo.
write_status "running" "Comprimiendo backup..."
tar -czf "${OUTFILE}" -C "$(dirname "${WORKDIR}")" "$(basename "${WORKDIR}")" || fail "Fallo al comprimir el backup"

# 7) Verificar integridad del archivo generado antes de darlo por bueno.
write_status "running" "Verificando integridad..."
if ! tar -tzf "${OUTFILE}" > /dev/null 2>&1; then
    rm -f "${OUTFILE}"
    fail "El archivo comprimido generado esta corrupto, se ha descartado"
fi

SIZE_MB=$(du -m "${OUTFILE}" | cut -f1)
write_status "done" "Backup completado (${SIZE_MB} MB)"
send_notification "OK" "backup completado correctamente (${SIZE_MB} MB)"

echo "${OUTFILE}"
