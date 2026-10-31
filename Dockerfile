FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli opcache gd exif \
    && a2enmod remoteip headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/app.ini"
COPY docker/start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

# Only what the site needs at runtime. Secrets arrive at start-up, never in the image.
COPY public /var/www/app/public
COPY src /var/www/app/src
COPY templates /var/www/app/templates

CMD ["start-app"]
