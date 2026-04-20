FROM tyomboreinz/php-npm

WORKDIR /app

# Copy file dependency dulu untuk cache layer
COPY backend/composer.json backend/composer.lock backend/
COPY backend/package.json backend/package-lock.json backend/

# Install tanpa scripts (artisan belum ada)
RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts \
    && npm install

# Copy seluruh source code
COPY . .

# Sekarang jalankan scripts & build
RUN cd /app/backend \
    && mv env.txt .env \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan key:generate \
    && npm run build

RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 8007
CMD ["bash", "run.sh"]
