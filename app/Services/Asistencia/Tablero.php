<?php

namespace App\Services\Asistencia;

use App\Models\Carga;

/**
 * Arma todo lo que necesita una vista del tablero: indicadores, graficos, tablas y
 * hallazgos. Los graficos se entregan como datos, no como imagenes, para que el
 * navegador los dibuje en SVG y se puedan filtrar sin volver al servidor.
 */
final class Tablero
{
    /**
     * Que ve cada rol. La regla es calidad sobre cantidad: cada tarjeta responde una
     * pregunta de decision, no rellena espacio.
     */
    public const VISTAS = [
        'gerencia' => [
            'titulo' => 'Gerencia',
            'descripcion' => 'Una pantalla: indice de cumplimiento, el costo de la inasistencia, disciplina, capacidad por seccion y las excepciones que hay que mirar.',
            'kpis' => ['indice', 'capacidad', 'ausentismo', 'puntualidad', 'extra', 'vaclic'],
            'secciones' => ['hallazgos', 'tendencia', 'semaforo', 'secciones', 'extraMes', 'vacMes'],
        ],
        'rrhh' => [
            'titulo' => 'RRHH',
            'descripcion' => 'Disciplina y novedades: faltas, reincidencia, atrasos, calidad de marcacion, vacaciones, licencias y feriados trabajados.',
            'kpis' => ['faltas', 'faltones', 'atrasos', 'incompleta', 'vaclic', 'feriados'],
            'secciones' => ['hallazgos', 'tendencia', 'rankingFaltas', 'rankingAtrasos', 'vacMes', 'vacSec', 'feriadosTrab', 'detalle'],
        ],
        'planta' => [
            'titulo' => 'Planta',
            'descripcion' => 'Las siete secciones de planta: asistencia, puntualidad, permanencia, jornada, horas de fin de semana y feriados trabajados.',
            'kpis' => ['asistencia', 'puntualidad', 'salidas', 'jornada', 'finde', 'feriados'],
            'secciones' => ['hallazgos', 'secciones', 'heat', 'jornada', 'flujo', 'extraMes', 'feriadosTrab'],
        ],
        'explorar' => [
            'titulo' => 'Explorar',
            'descripcion' => 'Todo el tablero sin recortes, para cruzar cualquier indicador con cualquier filtro.',
            'kpis' => ['indice', 'capacidad', 'asistencia', 'ausentismo', 'puntualidad', 'atrasos',
                       'salidas', 'jornada', 'incompleta', 'extra', 'finde', 'vaclic', 'feriados', 'faltones'],
            'secciones' => ['hallazgos', 'tendencia', 'semaforo', 'secciones', 'heat', 'jornada',
                            'extraMes', 'vacMes', 'vacSec', 'feriadosTrab', 'flujo', 'rankingFaltas',
                            'rankingAtrasos', 'detalle'],
        ],
    ];

    public function __construct(
        private Repositorio $repo,
    ) {}

    public function construir(Carga $carga, string $vista, Filtro $filtro): array
    {
        $vista   = isset(self::VISTAS[$vista]) ? $vista : 'gerencia';
        $paquete = $this->repo->paqueteLigero($carga);
        $par     = $carga->parametrosObj();

        $agregador = new Agregador($paquete, $par);

        $A = $agregador->agregar($this->repo->registros($carga, $filtro->desde, $filtro->hasta), $filtro);
        $m = Metricas::de($A);

        // Periodo anterior del mismo largo, para las variaciones de las tarjetas.
        $mPrev = null;
        $largo = $filtro->hasta - $filtro->desde + 1;

        if ($filtro->desde - $largo >= 0) {
            $prev = clone $filtro;
            $prev->desde = $filtro->desde - $largo;
            $prev->hasta = $filtro->desde - 1;

            $AP = $agregador->agregar($this->repo->registros($carga, $prev->desde, $prev->hasta), $prev, true);
            $mPrev = Metricas::de($AP);
        }

        return [
            'vista'      => $vista,
            'config'     => self::VISTAS[$vista],
            'carga'      => $this->resumenCarga($carga, $A, $paquete),
            'filtro'     => $this->resumenFiltro($filtro, $paquete),
            'kpis'       => (new Kpis($par))->construir(self::VISTAS[$vista]['kpis'], $A, $m, $mPrev),
            'hallazgos'  => (new Hallazgos($paquete, $par))->generar($A, $m, $mPrev, $vista),
            'graficos'   => (new Graficos($paquete, $par))->construir(self::VISTAS[$vista]['secciones'], $A, $m),
            'metricas'   => $m,
            'parametros' => $par->toArray(),
        ];
    }

    private function resumenCarga(Carga $carga, array $A, Paquete $p): array
    {
        $perfiles = [];
        foreach ($carga->parametrosObj()->perfiles as $pf) {
            $perfiles[] = $pf['nombre'] . ' ' . Hallazgos::hhmm($pf['entrada']);
        }

        return [
            'id'         => $carga->id,
            'archivo'    => $carga->archivo,
            'desde'      => $carga->desde->format('Y-m-d'),
            'hasta'      => $carga->hasta->format('Y-m-d'),
            'dias'       => $carga->dias,
            'empleados'  => $carga->empleados,
            'registros'  => $carga->registros,
            'perfiles'   => $perfiles,
            'departamentos' => $p->departamentos,
            'secciones'  => array_keys($A['bySec']),
            'qa'         => $carga->qa,
        ];
    }

    private function resumenFiltro(Filtro $f, Paquete $p): array
    {
        return [
            'desde'  => $p->fechas[$f->desde] ?? null,
            'hasta'  => $p->fechas[$f->hasta] ?? null,
            'diasIdx' => [$f->desde, $f->hasta],
            'departamentos' => $f->departamentos,
            'empleado' => $f->empleado,
            'novedades' => array_keys(array_filter($f->novedades)),
        ];
    }
}
