# Etapa Node para compilar frontend (Vite, npm run build)
FROM node:20 AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .
RUN npm run build


# Etapa PHP con Composer + Laravel
FROM php:8.3-fpm

# Instalar dependencias necesarias para Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    nano \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl intl

# Copiar Composer desde imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiar el proyecto
COPY . .

# Copiar solo los assets compilados por Vite
COPY --from=node-builder /app/public/build /var/www/public/build

# Instalar dependencias de Laravel (ya dentro del contenedor)
RUN composer install  --optimize-autoloader --no-interaction --prefer-dist

# Optimizar caches de Laravel (opcional, mejora tiempos de arranque en prod)
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear

# Ajustar permisos para storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
