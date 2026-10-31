FROM php:8.4-apache

RUN docker-php-ext-install mysqli opcache \
    && a2enmod remoteip

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/app.ini"
COPY docker/start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

# Only what the site needs at runtime. Secrets arrive at start-up, never in the image.
COPY public /var/www/app/public
COPY src /var/www/app/src
COPY templates /var/www/app/templates

CMD ["start-app"]
