# Use official PHP image with Node.js
FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

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
