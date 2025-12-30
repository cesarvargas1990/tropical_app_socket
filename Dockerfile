# ---------- Node build (Vite) ----------
FROM node:20 AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- PHP deps (Composer) ----------
FROM composer:2.7 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# ---------- Runtime ----------
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libonig-dev libxml2-dev zip unzip curl git libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# Copiar código
COPY . .

# Copiar vendor ya instalado
COPY --from=composer-builder /app/vendor /var/www/vendor

# Copiar build de Vite (manifest.json incluido)
COPY --from=node-builder /app/public/build /var/www/public/build

# Asegurar directorios + permisos
RUN mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
