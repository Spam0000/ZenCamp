# =====================================================================
#  ZenCamp — Image applicative (Apache + PHP 8.3)
#
#  Deux zones distinctes, comme en production :
#    /var/www/html     racine web  -> vitrine statique uniquement
#    /var/www/zencamp  hors racine -> code PHP, inaccessible par URL
#  Seul src/public est exposé, via l'alias /app (voir docker/apache).
# =====================================================================
FROM php:8.3-apache

# ---------------------------------------------------------------------
# 1. Extensions PHP
#    pdo_mysql : requêtes préparées (Database.php)
#    opcache   : compilation en cache, indispensable en production
#    mbstring et sodium sont déjà compilés dans l'image officielle,
#    de même qu'Argon2id pour password_hash().
# ---------------------------------------------------------------------
RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache

# ---------------------------------------------------------------------
# 2. Modules Apache
#    headers : en-têtes de sécurité posés par src/.htaccess
#    rewrite : réécriture d'URL
# ---------------------------------------------------------------------
RUN a2enmod headers rewrite

COPY docker/apache/zencamp.conf /etc/apache2/conf-available/zencamp.conf
RUN a2enconf zencamp

COPY docker/php/zencamp.ini /usr/local/etc/php/conf.d/zencamp.ini

# ---------------------------------------------------------------------
# 3. Code PHP — hors de la racine web
# ---------------------------------------------------------------------
WORKDIR /var/www/zencamp

COPY src/   /var/www/zencamp/src/
COPY tests/ /var/www/zencamp/tests/

# La configuration réelle n'est jamais versionnée : on part du modèle,
# qui lit toutes ses valeurs dans les variables d'environnement.
RUN cp src/config/config.example.php src/config/config.php

# ---------------------------------------------------------------------
# 4. Vitrine statique — racine web
# ---------------------------------------------------------------------
COPY index.html  /var/www/html/
COPY assets/     /var/www/html/assets/
COPY images/     /var/www/html/images/

RUN chown -R www-data:www-data /var/www

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/") === false ? 1 : 0);'
