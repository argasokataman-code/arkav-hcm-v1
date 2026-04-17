FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl git unzip libpng-dev libjpeg-dev libfreetype6-dev libcurl4-openssl-dev pkg-config \
    libonig-dev libxml2-dev libzip-dev nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip curl fileinfo \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

RUN cd /app/backend && mv env.txt .env \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php \
    && npm install \
    && php artisan key:generate \
    && npm run build \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN cd /app/backend && php artisan migrate --force || true

RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 8007

CMD ["bash", "run.sh"]
