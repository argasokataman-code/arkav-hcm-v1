FROM tyomboreinz/php-npm

WORKDIR /app

# Copy hanya file dependency dulu agar layer ini di-cache
COPY backend/composer.json backend/composer.lock backend/
COPY backend/package.json backend/package-lock.json backend/

# Install dependencies (layer ini akan di-cache selama lock file tidak berubah)
RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php \
    && npm install

# Baru copy seluruh source code
COPY . .

RUN cd /app/backend \
    && mv env.txt .env \
    && php artisan key:generate \
    && npm run build

RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 8007
CMD ["bash", "run.sh"]
