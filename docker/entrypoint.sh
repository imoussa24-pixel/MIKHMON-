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

# Journaux applicatifs accessibles en ecriture.
mkdir -p /var/www/html/share/logs
chown -R www-data:www-data /var/www/html/share /var/www/html/img /var/www/html/voucher || true

echo "TIKRAS IT en ligne sur le port ${LISTEN_PORT} (donnees: ${DATA_DIR})"
exec apache2-foreground
