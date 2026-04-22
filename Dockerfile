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
COPY backend/composer.json backend/composer.lock /app/backend/
RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts

# Buat runtime directory Laravel agar container bisa start tanpa error permission
# bahkan sebelum storage bind-mount dari host ter-attach.
RUN mkdir -p backend/storage/logs \
               backend/storage/framework/sessions \
               backend/storage/framework/views \
               backend/storage/framework/cache/data \
               backend/storage/app/public \
               backend/storage/app/private \
               backend/bootstrap/cache \
    && chmod -R 775 backend/storage backend/bootstrap/cache \
    && chown -R www-data:www-data backend/storage backend/bootstrap/cache

EXPOSE 8007
CMD ["bash", "run.sh"]
