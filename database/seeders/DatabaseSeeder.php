<?php

namespace Database\Seeders;

use App\Models\Carga;
use App\Models\User;
use App\Services\Asistencia\Importador;
use Illuminate\Database\Seeder;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /** Export que viene con el proyecto; el tablero arranca ya con estos datos. */
    public const CSV_INICIAL = __DIR__ . '/data/asistencia.csv';

    public function run(): void
    {
        $this->usuarioInicial();
        $this->cargaInicial();
    }

    /**
     * Usuario administrador. La contrasena se toma del entorno; hay que cambiarla
     * en el primer ingreso y crear el resto de usuarios desde la base.
     */
    private function usuarioInicial(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'sistemas@plasticoscarmen.com')],
            [
                'name' => env('ADMIN_NAME', 'Administrador del tablero'),
                'password' => env('ADMIN_PASSWORD', 'cambiar-esta-clave'),
                'rol' => 'administrador',
                'activo' => true,
            ],
        );
    }

    /**
     * Importa el export incluido en el proyecto, pero solo si todavia no hay ninguna
     * carga: asi el seeder puede correr en cada arranque sin duplicar nada, y el
     * tablero vuelve solo a tener datos aunque el disco del hosting se reinicie.
     *
     * Si el archivo no esta, no pasa nada: el tablero abre con su pantalla de carga.
     */
    private function cargaInicial(): void
    {
        if (Carga::count() > 0) {
            $this->command?->info('Ya hay datos cargados: no se importa el export inicial.');

            return;
        }

        if (! is_readable(self::CSV_INICIAL)) {
            $this->command?->warn('No se encontro el export inicial en database/seeders/data/asistencia.csv.');

            return;
        }

        try {
            $carga = app(Importador::class)->importar(
                contenido: file_get_contents(self::CSV_INICIAL),
                nombreArchivo: 'asistencia.csv',
                userId: User::where('rol', 'administrador')->value('id'),
            );

            $this->command?->info(sprintf(
                'Export inicial importado: %s personas, %s dias, %s registros (%s al %s).',
                number_format($carga->empleados, 0, ',', '.'),
                number_format($carga->dias, 0, ',', '.'),
                number_format($carga->registros, 0, ',', '.'),
                $carga->desde->format('d/m/Y'),
                $carga->hasta->format('d/m/Y'),
            ));
        } catch (Throwable $e) {
            // Un export mal formado no debe impedir que la aplicacion arranque.
            $this->command?->error('No se pudo importar el export inicial: ' . $e->getMessage());
        }
    }
}
