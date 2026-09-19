<?php

namespace App\Services\Asistencia;

use App\Models\Carga;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de una carga desde la base. Devuelve los registros en el mismo formato que
 * produce el motor, para que el agregador no sepa si los datos vienen del CSV recien
 * leido o de la base.
 */
final class Repositorio
{
    /** Reconstruye el paquete (calendario, departamentos, empleados y celdas) de una carga. */
    public function paquete(Carga $carga): Paquete
    {
        $departamentos = DB::table('departamentos')
            ->where('carga_id', $carga->id)
            ->orderBy('idx')
            ->pluck('nombre')
            ->all();

        $empleados = [];
        $nd = $carga->dias;

        foreach (DB::table('empleados')->where('carga_id', $carga->id)->orderBy('idx')->get() as $e) {
            $empleados[$e->idx] = [
                'ci' => $e->ci, 'nombre' => $e->nombre, 'dep' => $e->dep_idx,
                'desde' => $e->desde, 'hasta' => $e->hasta,
                'celdas' => array_fill(0, $nd, ''),
            ];
        }

        // Las celdas se rearman desde los registros: marcas y codigos son el dato crudo.
        DB::table('registros')
            ->where('carga_id', $carga->id)
            ->select('empleado_idx', 'dia', 'marcas', 'codigos')
            ->orderBy('id')
            ->chunk(20000, function ($filas) use (&$empleados) {
                foreach ($filas as $r) {
                    if (! isset($empleados[$r->empleado_idx])) {
                        continue;
                    }

                    $celda = '';

                    if ($r->marcas) {
                        $celda = implode(',', array_map(
                            static fn ($m) => sprintf('%02d%02d', intdiv((int) $m, 60), (int) $m % 60),
                            explode(',', $r->marcas)
                        ));
                    }

                    if ($r->codigos) {
                        $celda .= ';' . $r->codigos;
                    }

                    $empleados[$r->empleado_idx]['celdas'][$r->dia] = $celda;
                }
            });

        return new Paquete(
            fechas: $carga->fechas,
            departamentos: $departamentos,
            empleados: array_values($empleados),
            qa: $carga->qa ?? [],
            origen: $carga->origen,
        );
    }

    /**
     * Recorre los registros de una carga sin cargarlos todos en memoria.
     *
     * @return Generator<array<string,mixed>>
     */
    public function registros(Carga $carga, ?int $desde = null, ?int $hasta = null): Generator
    {
        $q = DB::table('registros')->where('carga_id', $carga->id);

        if ($desde !== null) {
            $q->where('dia', '>=', $desde);
        }
        if ($hasta !== null) {
            $q->where('dia', '<=', $hasta);
        }

        foreach ($q->orderBy('id')->lazy(20000) as $r) {
            yield [
                'empleado_idx'      => (int) $r->empleado_idx,
                'dia'               => (int) $r->dia,
                'dep'               => (int) $r->dep_idx,
                'marcas'            => $r->marcas ? array_map('intval', explode(',', $r->marcas)) : [],
                'codigos'           => $r->codigos ? explode('+', $r->codigos) : [],
                'programado'        => (int) $r->programado,
                'feriado'           => (int) $r->feriado,
                'estado'            => (int) $r->estado,
                'trabajado'         => (int) $r->trabajado,
                'atraso'            => (int) $r->atraso,
                'incompleta'        => (int) $r->incompleta,
                'jornada'           => (int) $r->jornada,
                'entrada'           => (int) $r->entrada,
                'salida'            => (int) $r->salida,
                'extra'             => (int) $r->extra,
                'salida_anticipada' => (int) $r->salida_anticipada,
                'objetivo'          => (int) $r->objetivo,
            ];
        }
    }

    /** Solo los empleados, para armar filtros y tablas sin tocar los registros. */
    public function empleados(Carga $carga): array
    {
        $out = [];

        foreach (DB::table('empleados')->where('carga_id', $carga->id)->orderBy('idx')->get() as $e) {
            $out[] = ['ci' => $e->ci, 'nombre' => $e->nombre, 'dep' => $e->dep_idx,
                      'desde' => $e->desde, 'hasta' => $e->hasta];
        }

        return $out;
    }

    public function departamentos(Carga $carga): array
    {
        return DB::table('departamentos')->where('carga_id', $carga->id)
            ->orderBy('idx')->pluck('nombre')->all();
    }

    /** Paquete sin celdas: suficiente para agregar y mucho mas barato de armar. */
    public function paqueteLigero(Carga $carga): Paquete
    {
        return new Paquete(
            fechas: $carga->fechas,
            departamentos: $this->departamentos($carga),
            empleados: $this->empleados($carga),
            qa: $carga->qa ?? [],
            origen: $carga->origen,
        );
    }
}
