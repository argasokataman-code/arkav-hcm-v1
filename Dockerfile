FROM tyomboreinz/php-npm

WORKDIR /app

COPY . .

# Setup environment
RUN cd /app/backend \
    && rm -f .env \
    && cp env.txt .env

# Install PHP dependencies
RUN cd /app/backend \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php

# Install Node dependencies and build
RUN cd /app/backend \
    && npm install \
    && php artisan key:generate \
    && npm run build

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 8007

CMD ["bash", "run.sh"]
