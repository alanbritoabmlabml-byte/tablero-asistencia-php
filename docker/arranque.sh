#!/bin/sh
# ---------------------------------------------------------------------------
# Arranque del contenedor: deja la base lista, importa el export incluido si
# todavia no hay datos, cachea y levanta el servidor.
# Cualquier paso que falle corta el arranque: es preferible un contenedor que
# no levanta a uno que sirve numeros a medias.
# ---------------------------------------------------------------------------
set -e

cd /app

# Laravel cifra la sesion con APP_KEY y exige 32 bytes. Si el hosting no la
# definio, o la definio con otro formato, se genera una valida para este
# arranque (las sesiones abiertas se pierden, nada mas).
if [ -z "$APP_KEY" ] || ! php -r '$k = getenv("APP_KEY") ?: ""; $raw = str_starts_with($k, "base64:") ? base64_decode(substr($k, 7), true) : $k; exit(is_string($raw) && strlen($raw) === 32 ? 0 : 1);'; then
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY
    echo "==> APP_KEY generada para este arranque."
fi

# Las carpetas de trabajo no viajan en el repositorio (van vacias en git).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database
chmod -R 775 storage bootstrap/cache database

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
    echo "==> Base SQLite en $DB_FILE"
fi

echo "==> Descubriendo paquetes"
php artisan package:discover --ansi

echo "==> Migrando"
php artisan migrate --force --no-interaction

# El seeder crea el usuario administrador e importa el export incluido, pero
# solo si no hay ninguna carga: puede correr en cada arranque sin duplicar.
echo "==> Sembrando (usuario inicial + export del periodo)"
php artisan db:seed --force --no-interaction

echo "==> Cacheando configuracion, rutas y vistas"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Listo, sirviendo en el puerto ${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
