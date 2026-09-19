# ---------------------------------------------------------------------------
# FrankenPHP: servidor concurrente en un solo binario.
# Se usa en vez de `php artisan serve` porque ese atiende UNA peticion por vez:
# mientras el tablero arma una vista, el chequeo de salud del hosting queda en
# cola, se pasa de los 5 segundos y el servicio se reinicia solo.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.3-alpine

# install-php-extensions viene en la imagen y resuelve solo las dependencias
# de compilacion (incluidos los encabezados de sqlite) y las borra despues.
RUN install-php-extensions pdo_sqlite zip intl opcache

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

# Agregar el CSV del periodo son ~110.000 filas en memoria por un instante;
# 256 MB deja margen de sobra y hace que un error de memoria se vea como error
# de PHP y no como un contenedor que muere sin explicacion.
RUN printf 'memory_limit = 256M\nopcache.enable = 1\nopcache.validate_timestamps = 0\n' \
    > "$PHP_INI_DIR/conf.d/tablero.ini"

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8000

EXPOSE 8000

CMD ["sh", "/app/docker/arranque.sh"]
