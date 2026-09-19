<?php

namespace App\Services\Asistencia;

use App\Models\Carga;
use App\Models\Departamento;
use App\Models\Empleado;
use Illuminate\Support\Facades\DB;

/**
 * Importa un export del Control de Asistencia: lo lee, lo calcula con el motor y lo
 * guarda. Todo ocurre dentro de una transaccion, asi que un archivo mal formado no
 * deja una carga a medias.
 */
final class Importador
{
    /** Filas por INSERT. SQLite admite hasta 999 parametros por sentencia. */
    public const LOTE = 800;

    public function __construct(private CsvParser $parser) {}

    public function importar(
        string $contenido,
        string $nombreArchivo,
        ?int $userId = null,
        bool $activar = true,
        ?Parametros $parametros = null,
    ): Carga {
        $paquete = $this->parser->parse($contenido, $nombreArchivo);

        $par = $parametros ?? Parametros::paraDepartamentos($paquete->departamentos);

        // Los feriados detectados quedan propuestos; en Configuracion se editan.
        if (! $par->feriados) {
            $par->feriados = $paquete->feriadosDetectados;
        }

        $motor = new Motor($par);

        return DB::transaction(function () use ($paquete, $par, $motor, $nombreArchivo, $userId, $activar) {
            $carga = Carga::create([
                'archivo'    => $nombreArchivo,
                'origen'     => $paquete->origen,
                'desde'      => $paquete->fechas[0],
                'hasta'      => $paquete->fechas[count($paquete->fechas) - 1],
                'dias'       => $paquete->dias(),
                'empleados'  => count($paquete->empleados),
                'registros'  => 0,
                'hash'       => hash('sha256', $nombreArchivo . '|' . count($paquete->empleados) . '|' . $paquete->dias()),
                'fechas'     => $paquete->fechas,
                'qa'         => $paquete->qa,
                'parametros' => $par->toArray(),
                'user_id'    => $userId,
                'activa'     => false,
            ]);

            $this->guardarDepartamentos($carga, $paquete, $par);
            $this->guardarEmpleados($carga, $paquete);
            $total = $this->guardarRegistros($carga, $motor->calcular($paquete));

            $carga->forceFill(['registros' => $total])->save();

            if ($activar) {
                $carga->activar();
            }

            return $carga->fresh();
        });
    }

    private function guardarDepartamentos(Carga $carga, Paquete $p, Parametros $par): void
    {
        $filas = [];

        foreach ($p->departamentos as $i => $nombre) {
            $filas[] = [
                'carga_id' => $carga->id,
                'idx'      => $i,
                'nombre'   => $nombre,
                'perfil'   => $par->grupoDe($nombre),
                'seccion'  => $par->seccionDe($nombre),
            ];
        }

        Departamento::insert($filas);
    }

    private function guardarEmpleados(Carga $carga, Paquete $p): void
    {
        foreach (array_chunk($p->empleados, 200, true) as $lote) {
            $filas = [];

            foreach ($lote as $i => $e) {
                $filas[] = [
                    'carga_id' => $carga->id,
                    'idx'      => $i,
                    'ci'       => $e['ci'],
                    'nombre'   => $e['nombre'],
                    'dep_idx'  => $e['dep'],
                    'desde'    => $e['desde'],
                    'hasta'    => $e['hasta'],
                ];
            }

            Empleado::insert($filas);
        }
    }

    /** @param  iterable<array<string,mixed>>  $registros */
    private function guardarRegistros(Carga $carga, iterable $registros): int
    {
        $lote  = [];
        $total = 0;

        foreach ($registros as $r) {
            $lote[] = [
                'carga_id'          => $carga->id,
                'empleado_idx'      => $r['empleado_idx'],
                'dep_idx'           => $r['dep'],
                'dia'               => $r['dia'],
                'marcas'            => $r['marcas'] ? implode(',', $r['marcas']) : null,
                'codigos'           => $r['codigos'] ? implode('+', $r['codigos']) : null,
                'programado'        => $r['programado'],
                'feriado'           => $r['feriado'],
                'estado'            => $r['estado'],
                'trabajado'         => $r['trabajado'],
                'atraso'            => $r['atraso'],
                'incompleta'        => $r['incompleta'],
                'jornada'           => $r['jornada'],
                'entrada'           => $r['entrada'],
                'salida'            => $r['salida'],
                'extra'             => $r['extra'],
                'salida_anticipada' => $r['salida_anticipada'],
                'objetivo'          => $r['objetivo'],
            ];

            $total++;

            if (count($lote) >= self::LOTE) {
                DB::table('registros')->insert($lote);
                $lote = [];
            }
        }

        if ($lote) {
            DB::table('registros')->insert($lote);
        }

        return $total;
    }

    /**
     * Vuelve a calcular las columnas derivadas de una carga sin releer el CSV.
     * Se usa cuando cambian perfiles, feriados, dias de lluvia o secciones.
     *
     * Reescribe los registros en bloque en vez de actualizarlos uno a uno: con cien
     * mil dias, un UPDATE por fila tarda minutos y un borrado mas insercion, segundos.
     */
    public function recalcular(Carga $carga, Parametros $par): int
    {
        $paquete = (new Repositorio)->paquete($carga);

        return DB::transaction(function () use ($carga, $paquete, $par) {
            DB::table('registros')->where('carga_id', $carga->id)->delete();

            $total = $this->guardarRegistros($carga, (new Motor($par))->calcular($paquete));

            DB::table('departamentos')->where('carga_id', $carga->id)->delete();
            $this->guardarDepartamentos($carga, $paquete, $par);

            $carga->forceFill(['registros' => $total, 'parametros' => $par->toArray()])->save();

            return $total;
        });
    }
}
