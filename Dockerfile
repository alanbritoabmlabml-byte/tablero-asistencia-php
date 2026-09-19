# Imagen para desplegar el tablero en cualquier hosting que corra contenedores.
FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-libs libzip icu-libs oniguruma \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS libzip-dev icu-dev oniguruma-dev \
 && docker-php-ext-install pdo_sqlite zip intl opcache \
 && apk del .build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Las dependencias primero: si no cambian, esta capa se reutiliza entre despliegues.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache database \
 && chmod -R 775 storage bootstrap/cache database

ENV APP_ENV=production APP_DEBUG=false PORT=8000

# Al arrancar: base lista, usuario inicial y cache de configuracion.
CMD sh -c '\
  [ -f database/database.sqlite ] || touch database/database.sqlite; \
  php artisan migrate --force --no-interaction; \
  php artisan db:seed --force --no-interaction; \
  php artisan config:cache; \
  php artisan route:cache; \
  php artisan view:cache; \
  php artisan serve --host=0.0.0.0 --port=${PORT}'

EXPOSE 8000
