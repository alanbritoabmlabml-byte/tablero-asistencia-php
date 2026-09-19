<?php

namespace App\Services\Asistencia;

/**
 * Datos de cada grafico y tabla, listos para dibujar. El servidor entrega numeros,
 * no imagenes: asi el navegador puede resaltar, ordenar y filtrar sin otra consulta,
 * y los mismos datos sirven para las descargas.
 */
final class Graficos
{
    public function __construct(
        private Paquete $paquete,
        private Parametros $par,
    ) {}

    public function construir(array $secciones, array $A, array $m): array
    {
        $out = [];

        foreach ($secciones as $s) {
            $g = match ($s) {
                'tendencia'      => $this->tendencia($A),
                'semaforo'       => $this->semaforo($A, $m),
                'secciones'      => $this->secciones($A, $m),
                'heat'           => $this->heat($A),
                'jornada'        => $this->jornada($A),
                'extraMes'       => $this->extraMes($A),
                'vacMes'         => $this->vacMes($A),
                'vacSec'         => $this->vacSec($A),
                'feriadosTrab'   => $this->feriadosTrabajados($A),
                'flujo'          => $this->flujo($A),
                'rankingFaltas'  => $this->rankingFaltas($A),
                'rankingAtrasos' => $this->rankingAtrasos($A),
                'detalle'        => $this->detalle($A),
                default          => null,
            };

            if ($g) {
                $out[] = $g + ['id' => $s];
            }
        }

        return $out;
    }

    /* --------------------------------------------------------- evolucion */

    private function tendencia(array $A): ?array
    {
        if (! $A['bySemana']) {
            return null;
        }

        $labels = $asis = $punt = $perm = [];

        foreach ($A['bySemana'] as $k => $w) {
            if ($w['prog'] <= 0) {
                continue;
            }
            $labels[] = substr($k, 8, 2) . '/' . substr($k, 5, 2);
            $asis[]   = Metricas::pct($w['pres'], $w['prog']);
            $punt[]   = Metricas::pct($w['punt'], $w['punt'] + $w['atr']);
            $perm[]   = Metricas::pct($w['jorOk'], $w['jorVal']);
        }

        return [
            'tipo'   => 'lineas',
            'titulo' => 'Evolucion semanal',
            'sub'    => 'Asistencia, puntualidad y permanencia semana a semana, contra sus metas. Solo semanas con dias laborables dentro del alcance.',
            'formato' => 'pct',
            'labels' => $labels,
            'series' => [
                ['nombre' => 'Asistencia', 'datos' => $asis, 'ref' => $this->par->meta('asis')[0]],
                ['nombre' => 'Puntualidad', 'datos' => $punt, 'ref' => $this->par->meta('punt')[0]],
                ['nombre' => 'Permanencia', 'datos' => $perm, 'ref' => $this->par->meta('jor')[0]],
            ],
        ];
    }

    /* ------------------------------------------------------------- areas */

    private function semaforo(array $A, array $m): ?array
    {
        $filas = [];

        foreach ($A['byDep'] as $i => $d) {
            if ($d['prog'] < 1) {
                continue;
            }

            $asis = Metricas::pct($d['pres'], $d['prog']);
            $punt = Metricas::pct($d['punt'], $d['punt'] + $d['atr']);
            $jor  = Metricas::pct($d['jorOk'], $d['jorVal']);
            $comp = $d['presT'] ? 100 - 100 * $d['inc'] / $d['presT'] : null;

            $filas[] = [
                'idx' => $i,
                'nombre' => $this->paquete->departamentos[$i],
                'seccion' => $this->par->seccionDe($this->paquete->departamentos[$i]),
                'personas' => $d['emps'],
                'asistencia' => $asis,
                'asistenciaTono' => Metricas::tono($asis, $this->par->meta('asis'), true),
                'puntualidad' => $punt,
                'puntualidadTono' => Metricas::tono($punt, $this->par->meta('punt'), true),
                'permanencia' => $jor,
                'permanenciaTono' => Metricas::tono($jor, $this->par->meta('jor'), true),
                'completa' => $comp,
                'completaTono' => Metricas::tono($comp, $this->par->meta('comp'), true),
                'faltas' => $d['falta'],
                'horasExtra' => round(($d['extraNoLab'] + $d['sobre']) / 60),
                'indice' => Metricas::indice([
                    'asis' => $asis, 'punt' => $punt, 'jorOk' => $jor, 'compPct' => $comp,
                ]),
            ];
        }

        usort($filas, fn ($x, $y) => ($x['indice'] ?? 0) <=> ($y['indice'] ?? 0));

        return [
            'tipo'   => 'semaforo',
            'titulo' => 'Semaforo por area',
            'sub'    => 'Cada area contra las metas de Configuracion, ordenada por indice de cumplimiento: arriba la que mas atencion necesita.',
            'filas'  => $filas,
            'conjunto' => [
                'asistencia' => $m['asis'], 'puntualidad' => $m['punt'],
                'permanencia' => $m['jorOk'], 'completa' => $m['compPct'], 'indice' => $m['indice'],
            ],
        ];
    }

    private function secciones(array $A, array $m): ?array
    {
        if (! $A['bySec']) {
            return null;
        }

        $filas = [];

        foreach ($A['bySec'] as $sec => $S) {
            if ($S['prog'] < 1) {
                continue;
            }

            $filas[] = [
                'nombre' => $sec,
                'planta' => $sec !== Parametros::SECCION_ADMIN,
                'personas' => $S['emps'],
                'asistencia' => Metricas::pct($S['pres'], $S['prog']),
                'puntualidad' => Metricas::pct($S['punt'], $S['punt'] + $S['atr']),
                'permanencia' => Metricas::pct($S['jorOk'], $S['jorVal']),
                'faltas' => $S['falta'],
                'horasExtra' => round(($S['extraNoLab'] + $S['sobre']) / 60),
                'horasPerdidas' => round($S['minFalta'] / 60),
                'feriados' => $S['ferDias'],
                'vacaciones' => $S['vac'],
                'licencias' => $S['lic'],
            ];
        }

        usort($filas, fn ($x, $y) => ($y['asistencia'] ?? 0) <=> ($x['asistencia'] ?? 0));

        return [
            'tipo'   => 'secciones',
            'titulo' => 'Capacidad por seccion de planta',
            'sub'    => 'Las siete secciones de planta mas Administracion: asistencia, capacidad perdida y horas extra de cada una.',
            'formato' => 'pct',
            'ref'    => $m['asis'],
            'refLabel' => 'conjunto ' . Hallazgos::pct($m['asis']),
            'meta'   => $this->par->meta('asis')[0],
            'filas'  => $filas,
        ];
    }

    private function heat(array $A): ?array
    {
        $meses = array_keys($A['byMes']);

        if (! $meses) {
            return null;
        }

        // Se agrupa por seccion de planta, que es como Gerencia lee la fabrica.
        $porSeccion = [];

        foreach ($A['heat'] as $dep => $porMes) {
            $sec = $this->par->seccionDe($this->paquete->departamentos[$dep]);

            foreach ($porMes as $mes => $v) {
                $porSeccion[$sec][$mes]['prog'] = ($porSeccion[$sec][$mes]['prog'] ?? 0) + $v['prog'];
                $porSeccion[$sec][$mes]['pres'] = ($porSeccion[$sec][$mes]['pres'] ?? 0) + $v['pres'];
            }
        }

        $filas = [];
        $min = 100.0;
        $max = 0.0;

        foreach ($porSeccion as $sec => $porMes) {
            $celdas = [];

            foreach ($meses as $mes) {
                $v = $porMes[$mes] ?? null;
                $p = ($v && $v['prog'] > 0) ? 100 * $v['pres'] / $v['prog'] : null;

                if ($p !== null) {
                    $min = min($min, $p);
                    $max = max($max, $p);
                }

                $celdas[] = ['mes' => $mes, 'valor' => $p, 'prog' => $v['prog'] ?? 0];
            }

            $filas[] = ['nombre' => $sec, 'celdas' => $celdas];
        }

        usort($filas, fn ($x, $y) => strcmp($x['nombre'], $y['nombre']));

        return [
            'tipo'   => 'heat',
            'titulo' => 'Asistencia por seccion y mes',
            'sub'    => 'El color se reparte entre el minimo y el maximo observados, no sobre 0-100: asi se ven las diferencias reales del periodo.',
            'labels' => array_map(fn ($k) => Hallazgos::mes($k), $meses),
            'filas'  => $filas,
            'min'    => $min > $max ? 0 : $min,
            'max'    => $max,
        ];
    }

    /* --------------------------------------------------------- jornada */

    private function jornada(array $A): ?array
    {
        if ($A['jorVal'] < 1) {
            return null;
        }

        return [
            'tipo'   => 'reparto',
            'titulo' => 'Dias frente a la jornada del perfil',
            'sub'    => 'Solo dias con marcacion par. La holgura de cada perfil se configura por separado; fuera de ella el dia queda por debajo o por encima.',
            'datos'  => [
                ['label' => 'Por debajo', 'v' => $A['jor'][1], 'serie' => 2],
                ['label' => 'En la jornada', 'v' => $A['jor'][2], 'serie' => 1],
                ['label' => 'Por encima', 'v' => $A['jor'][3], 'serie' => 3],
            ],
            'nota' => Hallazgos::n($A['inc']) . ' dias quedan fuera del calculo por tener marcacion impar.',
        ];
    }

    /* ----------------------------------------------- horas extra y novedades */

    private function extraMes(array $A): ?array
    {
        if (! $A['byMes']) {
            return null;
        }

        $labels = $fds = $fer = $sobre = [];

        foreach ($A['byMes'] as $k => $b) {
            $labels[] = Hallazgos::mes($k);
            $fds[]    = round($b['extraFds'] / 60, 1);
            $fer[]    = round($b['extraFer'] / 60, 1);
            $sobre[]  = round($b['sobre'] / 60, 1);
        }

        return [
            'tipo'   => 'apiladas',
            'titulo' => 'Horas extra por mes',
            'sub'    => 'Separadas por origen: lo trabajado en fin de semana, lo trabajado en feriado y el exceso sobre la jornada en dia habil. Las dos primeras son hora extra por definicion.',
            'formato' => 'horas',
            'labels' => $labels,
            'series' => [
                ['nombre' => 'Fin de semana', 'datos' => $fds, 'serie' => 1],
                ['nombre' => 'Feriado', 'datos' => $fer, 'serie' => 2],
                ['nombre' => 'Exceso en dia habil', 'datos' => $sobre, 'serie' => 3],
            ],
        ];
    }

    private function vacMes(array $A): ?array
    {
        if (! $A['byMes']) {
            return null;
        }

        $labels = $v = $vdn = $bm = $otras = [];

        foreach ($A['byMes'] as $k => $b) {
            $labels[] = Hallazgos::mes($k);
            $v[]      = $b['vac'];
            $vdn[]    = $b['vacN'];
            $bm[]     = $b['licBM'];
            $otras[]  = $b['licOt'];
        }

        return [
            'tipo'   => 'apiladas',
            'titulo' => 'Vacaciones y licencias por mes',
            'sub'    => 'Dias-persona fuera por vacacion o licencia. No cuentan como ausentismo, pero si restan capacidad y conviene verlos junto a la carga del mes.',
            'formato' => 'entero',
            'labels' => $labels,
            'series' => [
                ['nombre' => 'Vacacion', 'datos' => $v, 'serie' => 1],
                ['nombre' => 'Vacacion de Navidad', 'datos' => $vdn, 'serie' => 3],
                ['nombre' => 'Baja medica', 'datos' => $bm, 'serie' => 2],
                ['nombre' => 'Otras licencias', 'datos' => $otras, 'serie' => 4],
            ],
        ];
    }

    private function vacSec(array $A): ?array
    {
        if (! $A['bySec']) {
            return null;
        }

        $datos = [];

        foreach ($A['bySec'] as $sec => $S) {
            if ($S['vac'] + $S['lic'] <= 0) {
                continue;
            }

            $datos[] = ['label' => $sec, 'v' => $S['vac'] + $S['lic'],
                        'detalle' => Hallazgos::n($S['vac']) . ' vacacion · ' . Hallazgos::n($S['lic']) . ' licencia'];
        }

        if (! $datos) {
            return null;
        }

        usort($datos, fn ($x, $y) => $y['v'] <=> $x['v']);

        return [
            'tipo'   => 'barras',
            'titulo' => 'Vacaciones y licencias por seccion',
            'sub'    => 'Donde se concentran los dias fuera. Util para planificar coberturas antes del pico de cada seccion.',
            'formato' => 'entero',
            'datos'  => $datos,
        ];
    }

    private function feriadosTrabajados(array $A): ?array
    {
        if (! $A['feriados']) {
            return null;
        }

        $datos = [];

        foreach ($A['feriados'] as $fecha => $f) {
            $secs = $f['secs'];
            arsort($secs);
            $principal = array_key_first($secs);

            $datos[] = [
                'label'  => substr($fecha, 8, 2) . '/' . substr($fecha, 5, 2),
                'fecha'  => $fecha,
                'v'      => $f['pres'],
                'horas'  => round($f['min'] / 60),
                'detalle' => Hallazgos::fechaLarga($fecha) . ' · ' . Hallazgos::n(round($f['min'] / 60))
                    . ' h · sobre todo ' . $principal,
            ];
        }

        usort($datos, fn ($x, $y) => $y['v'] <=> $x['v']);

        return [
            'tipo'   => 'barras',
            'titulo' => 'Feriados trabajados',
            'sub'    => 'Personas que marcaron en cada feriado del calendario. Toda esa carga es hora extra; el calendario se edita en Configuracion.',
            'formato' => 'entero',
            'datos'  => $datos,
        ];
    }

    /* --------------------------------------------------- lector biometrico */

    private function flujo(array $A): ?array
    {
        if (! $A['flujo']) {
            return null;
        }

        // Se agrupa de a 5 minutos: el export trae HH:MM sin segundos.
        $bins = array_fill(0, 288, 0);

        foreach ($A['flujo'] as $minuto => $n) {
            $bins[min(287, intdiv((int) $minuto, 5))] += $n;
        }

        $pico = max($bins);
        $idx  = array_search($pico, $bins, true);

        return [
            'tipo'   => 'flujo',
            'titulo' => 'Flujo de marcaciones por hora del dia',
            'sub'    => 'Marcaciones agrupadas cada 5 minutos. El pico muestra cuanta cola se forma en el lector y si la capacidad alcanza.',
            'datos'  => $bins,
            'entrada' => $A['histIn'],
            'salida' => $A['histOut'],
            'pico'   => ['valor' => $pico, 'hora' => Hallazgos::hhmm($idx * 5)],
            'capacidad' => $this->par->capacidadLector,
            'nota' => 'Capacidad declarada del lector: ' . $this->par->capacidadLector
                . ' marcaciones por minuto. El pico del periodo fue de ' . Hallazgos::n($pico)
                . ' marcaciones en 5 minutos, a las ' . Hallazgos::hhmm($idx * 5) . '.',
        ];
    }

    /* --------------------------------------------------------- rankings */

    private function rankingFaltas(array $A): ?array
    {
        if (! $A['empFalta']) {
            return null;
        }

        $filas = [];

        foreach ($A['empFalta'] as $ei => $faltas) {
            $e    = $this->paquete->empleados[$ei] ?? null;
            $prog = $A['empProg'][$ei] ?? 0;

            if (! $e || $prog < Metricas::MIN_DIAS_PROG) {
                continue;
            }

            $filas[] = [
                'ci' => $e['ci'], 'nombre' => $e['nombre'],
                'area' => $this->paquete->departamentos[$e['dep']] ?? '',
                'faltas' => $faltas, 'programados' => $prog,
                'tasa' => 100 * $faltas / $prog,
            ];
        }

        usort($filas, fn ($x, $y) => [$y['faltas'], $y['tasa']] <=> [$x['faltas'], $x['tasa']]);

        return [
            'tipo'   => 'ranking',
            'titulo' => 'Personas con mas dias de falta',
            'sub'    => 'Solo personas con al menos ' . Metricas::MIN_DIAS_PROG
                . ' dias programados en el alcance, para que un ingreso reciente no aparezca como reincidente.',
            'columnas' => ['CI', 'Nombre', 'Area', 'Faltas', 'Programados', '% de falta'],
            'filas'  => array_slice($filas, 0, 25),
            'total'  => count($filas),
        ];
    }

    private function rankingAtrasos(array $A): ?array
    {
        if (! $A['empAtr']) {
            return null;
        }

        $filas = [];

        foreach ($A['empAtr'] as $ei => $a) {
            $e = $this->paquete->empleados[$ei] ?? null;

            if (! $e) {
                continue;
            }

            $filas[] = [
                'ci' => $e['ci'], 'nombre' => $e['nombre'],
                'area' => $this->paquete->departamentos[$e['dep']] ?? '',
                'atrasos' => $a['n'], 'minutos' => $a['m'],
                'promedio' => $a['n'] ? $a['m'] / $a['n'] : 0,
            ];
        }

        usort($filas, fn ($x, $y) => $y['minutos'] <=> $x['minutos']);

        return [
            'tipo'   => 'ranking',
            'titulo' => 'Personas con mas minutos de atraso',
            'sub'    => 'Ordenado por minutos acumulados, no por cantidad de atrasos: diez atrasos de cinco minutos pesan menos que dos de una hora.',
            'columnas' => ['CI', 'Nombre', 'Area', 'Atrasos', 'Minutos', 'Promedio'],
            'filas'  => array_slice($filas, 0, 25),
            'total'  => count($filas),
        ];
    }

    private function detalle(array $A): array
    {
        return [
            'tipo'   => 'detalle',
            'titulo' => 'Detalle del alcance',
            'sub'    => 'Dias-persona que cumplen el filtro activo. La descarga trae todas las filas; aqui se muestran las primeras.',
            'total'  => $A['foco']['n'],
            'resumen' => $A['foco'],
        ];
    }
}
