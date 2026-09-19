#!/bin/sh
# ---------------------------------------------------------------------------
# Arranque del contenedor: deja la base lista, importa el export incluido si
# todavia no hay datos, cachea y levanta el servidor.
# Cualquier paso que falle corta el arranque: es preferible un contenedor que
# no levanta a uno que sirve numeros a medias.
# ---------------------------------------------------------------------------
set -e

cd /app

# Laravel cifra la sesion con APP_KEY y exige exactamente 32 bytes. Render
# genera un valor propio que no tiene ese largo, asi que hay que normalizarlo.
#
# Se DERIVA con sha256 del valor que ya esta en el entorno, en vez de sortear
# uno nuevo: sha256 siempre devuelve 32 bytes y siempre los mismos para la
# misma entrada, asi que la clave sobrevive a los reinicios. Con una clave
# distinta en cada arranque, la sesion de quien estaba adentro queda ilegible
# y el login parece no funcionar.
if [ -z "$APP_KEY" ] || ! php -r '$k = getenv("APP_KEY") ?: ""; $raw = str_starts_with($k, "base64:") ? base64_decode(substr($k, 7), true) : $k; exit(is_string($raw) && strlen($raw) === 32 ? 0 : 1);'; then
    SEMILLA="${APP_KEY}${RENDER_SERVICE_ID}"

    if [ -n "$SEMILLA" ]; then
        APP_KEY="base64:$(SEMILLA="$SEMILLA" php -r 'echo base64_encode(hash("sha256", getenv("SEMILLA"), true));')"
        echo "==> APP_KEY normalizada a 32 bytes (derivada del entorno, estable entre reinicios)."
    else
        # Sin nada estable de donde derivar: clave de un solo uso. Solo deberia
        # pasar corriendo el contenedor a mano, sin variables de entorno.
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        echo "==> ATENCION: no hay valor estable para APP_KEY; se genera una por arranque"
        echo "    y las sesiones se pierden al reiniciar. Defini APP_KEY en el entorno."
    fi

    export APP_KEY
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

# php-server sirve public/ y enruta todo a index.php, atendiendo varias
# peticiones a la vez: el chequeo de salud ya no espera detras del tablero.
exec frankenphp php-server --root /app/public --listen "0.0.0.0:${PORT:-8000}"
