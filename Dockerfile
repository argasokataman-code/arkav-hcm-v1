FROM tyomboreinz/php-npm

WORKDIR /app

COPY . .

RUN chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache && \
    cd /app/backend && mv env.txt .env && \
    composer install --no-dev --optimize-autoloader --ignore-platform-req=php && \
    npm install && \
    php artisan key:generate && \
    npm run build

RUN chown -R www-data:www-data /app

EXPOSE 8007

CMD ["bash", "run.sh"]