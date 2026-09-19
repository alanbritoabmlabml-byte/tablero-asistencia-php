# Imagen para desplegar el tablero en cualquier hosting que corra contenedores.
FROM php:8.3-cli-alpine

# sqlite-dev trae los encabezados y el archivo .pc que pdo_sqlite necesita para
# COMPILARSE; sqlite-libs es lo unico que hace falta despues, en ejecucion.
# Las dependencias de compilacion se borran en la misma capa para no cargarlas.
RUN apk add --no-cache sqlite-libs libzip icu-libs oniguruma \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS sqlite-dev libzip-dev icu-dev oniguruma-dev \
 && docker-php-ext-install pdo_sqlite zip intl opcache \
 && apk del .build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Las dependencias primero: si composer.json no cambia, esta capa se reutiliza.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

# --no-scripts: package:discover necesita la configuracion del entorno, que en
# el build todavia no existe. Se ejecuta al arrancar, en docker/arranque.sh.
RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts \
 && chmod +x docker/arranque.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8000

EXPOSE 8000

CMD ["sh", "/app/docker/arranque.sh"]
