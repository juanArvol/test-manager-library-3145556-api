FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    postgresql-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de dependencias
COPY composer.json composer.lock* ./

# Instalar dependencias de PHP
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copiar el resto de la aplicación
COPY . .

# Optimizar autoloader y ejecutar scripts post-install
RUN composer dump-autoload --optimize \
    && php artisan config:cache || true \
    && php artisan route:cache || true

# Crear directorios necesarios
RUN mkdir -p storage/app/covers storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Exponer puerto
EXPOSE 9000

CMD ["php-fpm"]
