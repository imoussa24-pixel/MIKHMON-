#!/bin/bash
set -e

# Render (et d'autres PaaS) imposent un port d'ecoute via $PORT.
LISTEN_PORT="${PORT:-80}"
sed -ri "s/^Listen 80$/Listen ${LISTEN_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${LISTEN_PORT}>/" /etc/apache2/sites-available/000-default.conf

# Dossier de donnees persistantes (disque Render monte sur /data).
DATA_DIR="${TIKRAS_DATA_DIR:-/data}"
mkdir -p "${DATA_DIR}/storage" "${DATA_DIR}/storage/backups"
chown -R www-data:www-data "${DATA_DIR}" || true

# L'image ne contient aucun secret: on pose une configuration de demarrage.
# La vraie liste des routeurs est lue depuis ${DATA_DIR}/config.local.php.
if [ ! -f /var/www/html/include/config.php ]; then
  cp /var/www/html/include/config.sample.php /var/www/html/include/config.php
fi

# Journaux applicatifs accessibles en ecriture.
mkdir -p /var/www/html/share/logs
chown -R www-data:www-data /var/www/html/share /var/www/html/img /var/www/html/voucher || true

# Planificateur interne: execute les automatisations 24h/24 sans dependre d'un
# cron externe. Toujours lance en www-data pour que les fichiers crees (SQLite,
# sauvegardes) restent accessibles en ecriture a Apache.
if [ "${TIKRAS_INTERNAL_CRON:-1}" = "1" ]; then
  (
    sleep 20
    while true; do
      su www-data -s /bin/sh -c "php /var/www/html/cron.php" >> "${DATA_DIR}/automations.log" 2>&1 || true
      sleep "${TIKRAS_INTERNAL_CRON_INTERVAL:-300}"
    done
  ) &
  echo "Planificateur interne actif (toutes les ${TIKRAS_INTERNAL_CRON_INTERVAL:-300}s)"
fi

echo "TIKRAS IT en ligne sur le port ${LISTEN_PORT} (donnees: ${DATA_DIR})"
exec apache2-foreground
