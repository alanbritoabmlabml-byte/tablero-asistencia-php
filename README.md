# Tablero de Control de Asistencia — Plásticos Carmen S.R.L.

Aplicación Laravel que reemplaza al tablero HTML autocontenido (v14). El export del
Control de Asistencia se importa una vez, se calcula en el servidor y queda guardado:
desde ese momento todos ven exactamente los mismos números, con histórico de cargas.

## Qué cambia respecto del HTML

| | HTML v14 | Laravel |
|---|---|---|
| Dónde se calcula | en el navegador de cada persona | en el servidor, una sola vez |
| Dónde viven los datos | IndexedDB de cada navegador | base de datos |
| Histórico | no hay | cada importación es una carga |
| Quién ve qué | cualquiera con el archivo | login y roles |
| Parámetros | por navegador | únicos, con recálculo |

## Requisitos

PHP 8.2 o superior con `pdo_sqlite`, `mbstring`, `intl` y `zip`, más Composer.
SQLite alcanza de sobra: una carga son unas 110.000 filas.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

El seeder crea el usuario inicial con `ADMIN_EMAIL` y `ADMIN_PASSWORD` del `.env`
e importa el export incluido en el proyecto, así que al abrir ya hay datos.
**Cambiá esa contraseña antes de publicar el sitio.**

## Datos

El proyecto incluye el export del periodo en `database/seeders/data/asistencia.csv`,
y el seeder lo importa solo la primera vez que arranca: el tablero abre con los datos
puestos, sin pasos manuales. Como el seeder solo importa **cuando no hay ninguna carga**,
puede correr en cada arranque sin duplicar nada, y los datos vuelven solos aunque el
disco del hosting se reinicie.

> Ese CSV tiene nombre y CI de todo el personal. **El repositorio debe seguir siendo
> privado.** Si alguna vez hay que hacerlo público, primero sacar el archivo del
> historial de git, no solo del último commit.

Para cargar otro periodo, desde el navegador en **Datos → Importar**, o desde la terminal:

```bash
php artisan asistencia:importar ruta/al/asistencia.csv
```

Formato esperado: CSV ancho separado por `;`, con `CI`, `Nombre`, `Departamento` y una
columna por fecha (`aaaa-mm-dd` o `dd/mm/aaaa`). Las marcaciones del día van en la misma
celda separadas por espacios (`07:30 19:20`); los códigos aceptados son
`V`, `VDN`, `LDM`, `LPM`, `LR`, `BM`, `I` y `SR`.

## Cómo está organizado

El cálculo vive entero en `app/Services/Asistencia/`, sin depender del framework, así que
se puede probar y reutilizar fuera de Laravel:

| Clase | Responsabilidad |
|---|---|
| `CsvParser` | Lee el export, ordena marcaciones, descarta rebotes del lector y propone feriados |
| `Parametros` | Perfiles de jornada, secciones de planta, metas y feriados |
| `Motor` | Convierte cada día en estado, minutos, atraso, jornada y horas extra |
| `Agregador` | Suma el alcance filtrado por área, sección, mes, semana, día y feriado |
| `Metricas` | Tasas e índice de cumplimiento ponderado |
| `Hallazgos` | Lectura automática del periodo, con la regla que dispara cada hallazgo |
| `Kpis` / `Graficos` | Arman las tarjetas y los datos de cada gráfico |
| `Importador` / `Repositorio` | Persistencia y recálculo |

Los gráficos se dibujan en el navegador (`public/js/tablero.js`, SVG sin librerías) a partir
de los datos que entrega el servidor: se pueden inspeccionar y se imprimen bien.

## Reglas de cálculo

- **Falta** = código `I`, más `SR` en día programado del perfil.
- **Perfiles de jornada**, porque los horarios reales difieren: Fábrica 07:30–19:30 (11 h
  netas), Tanques 06:30–18:40, Administración 08:00–16:30 (y sábados 08:00–12:30),
  Almacén Bolsa en dos turnos continuos.
- **Almuerzo** solo se descuenta si el día tiene exactamente dos marcaciones y el lapso
  supera el umbral del perfil; si hay cuatro marcaciones ya viene descontado.
- **Marcación incompleta** = número impar de marcaciones. Sin ingreso y salida no hay
  jornada ni horas extra: queda fuera de esos cálculos.
- **Hora extra** = todo lo trabajado en día no programado (fin de semana o feriado), más
  el exceso sobre las horas estimadas en día hábil.
- **Capacidad perdida** = las faltas valoradas a la jornada objetivo de cada perfil.
- **Índice de cumplimiento** = asistencia 40 %, puntualidad 30 %, permanencia 20 %,
  marcación completa 10 %.
- **Feriados**: se proponen solos (día hábil con más del 55 % de `SR`) y se editan en
  Configuración. Cambiar cualquier parámetro recalcula la carga activa entera.

## Vistas

`gerencia`, `rrhh`, `planta` y `explorar`. Cada una elige sus tarjetas y sus gráficos:
calidad sobre cantidad, una tarjeta por pregunta de decisión.

## Descargas

Resumen por áreas, serie mensual, feriados trabajados y detalle completo (una fila por
persona y día). Todas salen en CSV con BOM y separador `;`, listas para Excel en español.

## Pruebas

```bash
php artisan test
```

`tests/Unit/MotorTest.php` fija las reglas de cálculo con un CSV mínimo y controlado;
`tests/Feature/TableroTest.php` cubre login, roles, importación, las cuatro vistas,
las descargas y el recálculo.

## Despliegue

Hay un `Dockerfile` y un `render.yaml` listos. En cualquier hosting con PHP:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

La raíz pública del servidor debe apuntar a `public/`, nunca a la carpeta del proyecto.

En el plan gratuito de Render el disco se reinicia con el contenedor: para conservar el
histórico hay que agregar un disco persistente o apuntar `DB_CONNECTION` a una base
administrada.
