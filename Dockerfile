# syntax=docker/dockerfile:1
#
# Base runtime image — tidak menyimpan source code di layer (no COPY).
# Source code, vendor, dan build asset disuplai lewat bind mount & named volume
# oleh docker-compose.yml saat container dijalankan di server.
#
# Jika perlu standalone build (misal staging tanpa docker-compose), jalankan:
#   DOCKER_BUILDKIT=1 docker build --build-arg BUILDKIT_INLINE_CACHE=1 .

FROM tyomboreinz/php-npm
LABEL org.opencontainers.image.title="arkav-hcm-runtime"
LABEL org.opencontainers.image.description="Runtime image with composer dependencies prepared at build time"

WORKDIR /app

# Install dependency PHP saat docker build agar deploy tidak menjalankan composer install lagi.
COPY backend/composer.json backend/composer.lock backend/
COPY backend/package.json backend/package-lock.json backend/

RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts \
    && npm install

# Copy seluruh project
COPY . .

# Siapkan semua direktori Laravel, lalu build
# Buat runtime directory Laravel agar container bisa start tanpa error permission
# bahkan sebelum storage bind-mount dari host ter-attach.
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
