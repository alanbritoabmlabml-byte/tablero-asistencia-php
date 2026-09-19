<?php

namespace App\Console\Commands;

use App\Services\Asistencia\Importador;
use Illuminate\Console\Command;

/**
 * Importa el export desde la linea de comandos. Util para automatizar la carga
 * periodica sin pasar por el navegador.
 */
class ImportarAsistencia extends Command
{
    protected $signature = 'asistencia:importar
                            {archivo : Ruta del CSV ancho que exporta el Control de Asistencia}
                            {--no-activar : Importar sin dejarla como carga activa}';

    protected $description = 'Importa un export del Control de Asistencia y lo deja listo para el tablero';

    public function handle(Importador $importador): int
    {
        $ruta = $this->argument('archivo');

        if (! is_readable($ruta)) {
            $this->error('No se puede leer el archivo: ' . $ruta);

            return self::FAILURE;
        }

        $this->info('Leyendo y calculando...');

        $carga = $importador->importar(
            contenido: file_get_contents($ruta),
            nombreArchivo: basename($ruta),
            activar: ! $this->option('no-activar'),
        );

        $this->newLine();
        $this->table(['Dato', 'Valor'], [
            ['Carga', '#' . $carga->id],
            ['Periodo', $carga->desde->format('d/m/Y') . ' al ' . $carga->hasta->format('d/m/Y')],
            ['Dias', number_format($carga->dias, 0, ',', '.')],
            ['Personas', number_format($carga->empleados, 0, ',', '.')],
            ['Registros', number_format($carga->registros, 0, ',', '.')],
            ['Activa', $carga->activa ? 'si' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
