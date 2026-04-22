FROM tyomboreinz/php-npm

WORKDIR /app

COPY backend/composer.json backend/composer.lock backend/
COPY backend/package.json backend/package-lock.json backend/

RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts \
    && npm install

COPY . .

RUN cd /app/backend \
    && cp env.txt .env \
    # && php artisan package:discover --ansi \
    && php artisan key:generate \
    && npm run build

EXPOSE 8007
CMD ["bash", "run.sh"]
