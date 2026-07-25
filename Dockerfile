# TIKRAS IT / Mikhmon Pro Admin - serveur en ligne
# Image PHP 8.3 + Apache prete pour Render, Railway, VPS Docker...
FROM php:8.3-apache

# Extensions requises: zip (sauvegardes), pdo_sqlite (base locale).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# Application
COPY mikhmon/ /var/www/html/
COPY docker/tikras-php.ini /usr/local/etc/php/conf.d/tikras.ini
COPY docker/entrypoint.sh /usr/local/bin/tikras-entrypoint

RUN chmod +x /usr/local/bin/tikras-entrypoint \
    && chown -R www-data:www-data /var/www/html

# Donnees persistantes (config locale, SQLite, sauvegardes) sur /data.
ENV TIKRAS_DATA_DIR=/data
VOLUME ["/data"]

EXPOSE 80
CMD ["tikras-entrypoint"]
