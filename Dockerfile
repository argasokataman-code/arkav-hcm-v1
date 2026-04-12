FROM node:20-alpine AS buildnpm

WORKDIR /app
COPY . /app/
RUN npm install && npm run build


FROM php:8.2-apache

COPY --from=buildnpm /app /var/www/html
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite ssl && \
    apt update && \
    apt install -y nano net-tools libpq-dev libzip-dev unzip libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev libpq-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath zip gd && \
    rm -rf /var/www/html/fileconfig && \
    composer install

COPY fileconfig/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY fileconfig/php.ini /usr/local/etc/php/php.ini
COPY fileconfig/default.conf /etc/apache2/sites-available/000-default.conf
COPY fileconfig/cert.pem /etc/apache2/ssl/
COPY fileconfig/key.pem /etc/apache2/ssl/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache