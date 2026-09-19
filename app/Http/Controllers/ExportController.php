<?php

namespace App\Http\Controllers;

use App\Models\Carga;
use App\Services\Asistencia\Agregador;
use App\Services\Asistencia\Codigos;
use App\Services\Asistencia\Hallazgos;
use App\Services\Asistencia\Metricas;
use App\Services\Asistencia\Repositorio;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descargas del alcance activo. Se emiten como flujo, no como archivo en memoria:
 * el detalle puede pasar las cien mil filas y aun asi empieza a bajar de inmediato.
 *
 * Todas salen con BOM y separador ";" para que Excel en espanol las abra bien.
 */
class ExportController extends Controller
{
    public function __construct(
        private Repositorio $repo,
        private TableroController $tableroCtrl,
    ) {}

    public function areas(Request $request): StreamedResponse
    {
        [$carga, $A, $m, $paquete, $par] = $this->contexto($request);

        $filas = [];

        foreach ($A['byDep'] as $i => $d) {
            if ($d['prog'] < 1) {
                continue;
            }

            $filas[] = [
                $paquete->departamentos[$i],
                $par->seccionDe($paquete->departamentos[$i]),
                $d['emps'],
                $this->num(Metricas::pct($d['pres'], $d['prog'])),
                $this->num(Metricas::pct($d['falta'], $d['prog'])),
                $this->num(Metricas::pct($d['punt'], $d['punt'] + $d['atr'])),
                $this->num(Metricas::pct($d['jorOk'], $d['jorVal'])),
                $this->num($d['presT'] ? 100 - 100 * $d['inc'] / $d['presT'] : null),
                $d['falta'], $d['vac'], $d['lic'],
                $this->num($d['minFalta'] / 60), $this->num($d['extraFds'] / 60),
                $this->num($d['extraFer'] / 60), $this->num($d['sobre'] / 60), $d['ferDias'],
            ];
        }

        return $this->csv('asistencia_areas', [
            'Area', 'Seccion', 'Personas', '% asistencia', '% ausentismo', '% puntualidad',
            '% permanencia', '% marcacion completa', 'Dias de falta', 'Dias de vacacion',
            'Dias de licencia', 'Horas perdidas', 'Horas fin de semana', 'Horas feriado',
            'Horas exceso dia habil', 'Jornadas en feriado',
        ], $filas);
    }

    public function meses(Request $request): StreamedResponse
    {
        [$carga, $A] = $this->contexto($request);

        $filas = [];

        foreach ($A['byMes'] as $mes => $b) {
            $filas[] = [
                $mes,
                $b['prog'], $b['pres'], $b['falta'],
                $this->num(Metricas::pct($b['pres'], $b['prog'])),
                $b['punt'], $b['atr'],
                $this->num(Metricas::pct($b['punt'], $b['punt'] + $b['atr'])),
                $b['vac'], $b['vacN'], $b['licBM'], $b['licOt'],
                $this->num($b['min'] / 60), $this->num($b['extraFds'] / 60),
                $this->num($b['extraFer'] / 60), $this->num($b['sobre'] / 60),
                $b['ferDias'], $this->num($b['minFalta'] / 60),
            ];
        }

        return $this->csv('asistencia_meses', [
            'Mes', 'Dias programados', 'Dias presentes', 'Dias de falta', '% asistencia',
            'Ingresos puntuales', 'Ingresos con atraso', '% puntualidad',
            'Vacacion', 'Vacacion de Navidad', 'Baja medica', 'Otras licencias',
            'Horas trabajadas', 'Horas fin de semana', 'Horas feriado', 'Horas exceso dia habil',
            'Jornadas en feriado', 'Horas perdidas',
        ], $filas);
    }

    public function feriados(Request $request): StreamedResponse
    {
        [$carga, $A, $m, $paquete, $par] = $this->contexto($request);

        $filas = [];

        foreach ($A['feriados'] as $fecha => $f) {
            foreach ($f['deps'] as $dep => $n) {
                $filas[] = [
                    $fecha,
                    Hallazgos::fechaLarga($fecha),
                    $paquete->departamentos[$dep] ?? '',
                    $par->seccionDe($paquete->departamentos[$dep] ?? ''),
                    $n,
                ];
            }
        }

        return $this->csv('asistencia_feriados', [
            'Fecha', 'Dia', 'Area', 'Seccion', 'Personas que marcaron',
        ], $filas);
    }

    /** Una fila por persona y dia del alcance: la base para cualquier revision manual. */
    public function detalle(Request $request): StreamedResponse
    {
        $carga = Carga::activa();
        abort_unless($carga, 404);

        $filtro   = $this->tableroCtrl->filtro($request, $carga);
        $paquete  = $this->repo->paqueteLigero($carga);
        $par      = $carga->parametrosObj();
        $usarNov  = $filtro->hayNovedades();

        $estados = [
            Codigos::PRESENTE => 'Presente', Codigos::FALTA => 'Falta',
            Codigos::VAC => 'Vacacion', Codigos::LIC => 'Licencia', Codigos::NOLAB => 'No laborable',
        ];
        $jornadas = [0 => '', 1 => 'Por debajo', 2 => 'En la jornada', 3 => 'Por encima'];

        $registros = $this->repo->registros($carga, $filtro->desde, $filtro->hasta);

        return $this->csv('asistencia_detalle', [
            'Fecha', 'Dia', 'CI', 'Nombre', 'Area', 'Seccion', 'Estado', 'Codigos',
            'Marcaciones', 'Programado', 'Feriado', 'Entrada', 'Salida', 'Minutos trabajados',
            'Horas trabajadas', 'Atraso (min)', 'Marcacion incompleta', 'Jornada',
            'Salida anticipada (min)', 'Horas extra',
        ], (function () use ($registros, $paquete, $par, $filtro, $usarNov, $estados, $jornadas) {
            $dias = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];

            foreach ($registros as $r) {
                $e = $paquete->empleados[$r['empleado_idx']] ?? null;

                if (! $e || ! $filtro->aceptaEmpleado($e)) {
                    continue;
                }
                if ($usarNov && ! $filtro->aceptaNovedad($r)) {
                    continue;
                }

                $fecha = $paquete->fechas[$r['dia']];
                $dep   = $paquete->departamentos[$r['dep']] ?? '';

                yield [
                    $fecha,
                    $dias[$paquete->dow[$r['dia']]],
                    $e['ci'], $e['nombre'], $dep, $par->seccionDe($dep),
                    $estados[$r['estado']] ?? '',
                    implode('+', $r['codigos']),
                    implode(' ', array_map(fn ($x) => Hallazgos::hhmm($x), $r['marcas'])),
                    $r['programado'] ? 'si' : 'no',
                    $r['feriado'] ? 'si' : 'no',
                    $r['entrada'] >= 0 ? Hallazgos::hhmm($r['entrada']) : '',
                    $r['salida'] >= 0 ? Hallazgos::hhmm($r['salida']) : '',
                    $r['trabajado'],
                    $this->num($r['trabajado'] / 60, 2),
                    $r['atraso'] > 0 ? $r['atraso'] : 0,
                    $r['incompleta'] ? 'si' : 'no',
                    $jornadas[$r['jornada']],
                    $r['salida_anticipada'],
                    $this->num($r['extra'] / 60, 2),
                ];
            }
        })());
    }

    /* ------------------------------------------------------------ helpers */

    private function contexto(Request $request): array
    {
        $carga = Carga::activa();
        abort_unless($carga, 404);

        $filtro  = $this->tableroCtrl->filtro($request, $carga);
        $paquete = $this->repo->paqueteLigero($carga);
        $par     = $carga->parametrosObj();

        $A = (new Agregador($paquete, $par))->agregar(
            $this->repo->registros($carga, $filtro->desde, $filtro->hasta), $filtro
        );

        return [$carga, $A, Metricas::de($A), $paquete, $par];
    }

    private function csv(string $nombre, array $cabecera, iterable $filas): StreamedResponse
    {
        $archivo = $nombre . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($cabecera, $filas) {
            $out = fopen('php://output', 'w');

            // BOM: sin el, Excel en espanol rompe los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $cabecera, ';', '"', '');

            foreach ($filas as $fila) {
                fputcsv($out, $fila, ';', '"', '');
            }

            fclose($out);
        }, $archivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Numeros con coma decimal, como los espera Excel en espanol. */
    private function num(int|float|null $v, int $dec = 1): string
    {
        return $v === null ? '' : number_format($v, $dec, ',', '');
    }
}
