FROM tyomboreinz/php-npm

WORKDIR /app

# 1. Copy dependency files dulu (cache layer)
COPY backend/composer.json backend/composer.lock backend/
COPY backend/package.json backend/package-lock.json backend/

# 2. Install dependencies tanpa scripts
RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts \
    && npm install

# 3. Copy seluruh project
COPY . .

# 4. Siapkan semua direktori Laravel, lalu build
RUN cd /app/backend \
    && mkdir -p storage/logs \
               storage/framework/sessions \
               storage/framework/views \
               storage/framework/cache/data \
               bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && cp env.txt .env \
    && php artisan package:discover --ansi \
    && php artisan key:generate \
    && npm run build

# 5. Fix ownership
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache
EXPOSE 8007
CMD ["bash", "run.sh"]
