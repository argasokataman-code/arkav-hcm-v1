FROM tyomboreinz/php-npm

WORKDIR /app

	@@ -17,10 +8,7 @@ RUN cd /app/backend && mv env.txt .env \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php \
    && npm install \
    && php artisan key:generate \
    && npm run build

RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 8007

CMD ["bash", "run.sh"]
