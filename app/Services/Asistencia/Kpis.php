<?php

namespace App\Services\Asistencia;

/**
 * Las tarjetas de indicadores. Cada una lleva su valor, su desviacion frente a la
 * meta y frente al periodo anterior, y la linea de valores absolutos que la explica:
 * un porcentaje sin su numerador no sirve para decidir nada.
 */
final class Kpis
{
    public function __construct(private Parametros $par) {}

    public function construir(array $claves, array $A, array $m, ?array $mPrev): array
    {
        $out = [];

        foreach ($claves as $clave) {
            $k = $this->tarjeta($clave, $A, $m, $mPrev);
            if ($k) {
                $out[] = $k + ['clave' => $clave];
            }
        }

        return $out;
    }

    private function tarjeta(string $clave, array $A, array $m, ?array $mPrev): ?array
    {
        return match ($clave) {
            'indice'      => $this->indice($A, $m, $mPrev),
            'capacidad'   => $this->capacidad($A, $m),
            'asistencia'  => $this->porcentaje('Asistencia', $m['asis'], 'asis', true, $m, $mPrev, 'asis',
                Hallazgos::n($A['presProg']) . ' de ' . Hallazgos::n($A['prog']) . ' dias-persona programados'),
            'ausentismo'  => $this->porcentaje('Ausentismo', $m['aus'], 'aus', false, $m, $mPrev, 'aus',
                Hallazgos::n($A['falta']) . ' dias de falta (' . Hallazgos::n($A['faltaI']) . ' con codigo I, '
                . Hallazgos::n($A['faltaSR']) . ' sin registro) · equivale a ' . Hallazgos::n($m['fte'], 1) . ' personas ausentes todo el periodo'),
            'puntualidad' => $this->porcentaje('Puntualidad', $m['punt'], 'punt', true, $m, $mPrev, 'punt',
                Hallazgos::n($A['puntual']) . ' ingresos puntuales · ' . Hallazgos::n($A['atrasos'])
                . ' con atraso · ' . Hallazgos::n($m['atrProm']) . ' min promedio'),
            'jornada'     => $this->porcentaje('Permanencia', $m['jorOk'], 'jor', true, $m, $mPrev, 'jorOk',
                Hallazgos::n($A['jor'][2] + $A['jor'][3]) . ' de ' . Hallazgos::n($A['jorVal'])
                . ' dias evaluables alcanzan la jornada de su perfil'),
            'incompleta'  => $this->porcentaje('Marcacion completa', $m['compPct'], 'comp', true, $m, $mPrev, 'compPct',
                Hallazgos::n($A['inc']) . ' dias con marcacion impar · ' . Hallazgos::n($A['sinEntrada']) . ' salidas sin ingreso'),
            'salidas'     => $this->porcentaje('Salida anticipada', $m['salAnt'], 'sal', false, $m, $mPrev, 'salAnt',
                Hallazgos::n($A['salAnt']) . ' dias · ' . Hallazgos::n($m['salAntProm']) . ' min antes en promedio'),
            'atrasos'     => $this->valor('Atraso promedio', Hallazgos::n($m['atrProm']), 'min',
                Hallazgos::n($A['atrasos']) . ' atrasos acumulan ' . Hallazgos::n($A['minAtr'] / 60) . ' h de ingreso tardio',
                Metricas::tono($m['punt'], $this->par->meta('punt'), true)),
            'extra'       => $this->valor('Horas extra', Hallazgos::n($m['hExtra']), 'h',
                Hallazgos::n($m['hFds']) . ' h en fin de semana y feriados + ' . Hallazgos::n($m['hSobre'])
                . ' h de exceso en dia habil · ' . Hallazgos::pct(Metricas::pct($m['hExtra'], $m['hTrab'])) . ' de las horas trabajadas'),
            'finde'       => $this->valor('Fin de semana y feriados', Hallazgos::n($m['hFds']), 'h',
                Hallazgos::n($A['fdsDias'] + $A['ferDias']) . ' jornadas fuera de dia laborable · '
                . Hallazgos::n($m['hFinde']) . ' h de fin de semana y ' . Hallazgos::n($m['hFer']) . ' h de feriado'),
            'vaclic'      => $this->valor('Vacaciones y licencias', Hallazgos::n($m['vacDias'] + $m['licDias']), 'dias',
                Hallazgos::n($m['vacDias']) . ' de vacacion (' . Hallazgos::n($A['vacTipos']['VDN'] ?? 0)
                . ' VDN) en ' . Hallazgos::n($m['vacPers']) . ' personas · ' . Hallazgos::n($m['licDias'])
                . ' de licencia (' . Hallazgos::n($A['licTipos']['BM'] ?? 0) . ' baja medica) · '
                . Hallazgos::pct(Metricas::pct($m['vacDias'] + $m['licDias'], $A['prog'] + $A['vac'] + $A['lic']))
                . ' del calendario'),
            'feriados'    => $this->valor('Feriados trabajados', Hallazgos::n($m['ferDias']), 'jornadas',
                count($A['feriados']) . ' feriados con marcacion · ' . Hallazgos::n($m['hFer'])
                . ' h, todas hora extra por definicion'),
            'faltas'      => $this->valor('Dias de falta', Hallazgos::n($A['falta']), 'dias',
                Hallazgos::n($A['faltaI']) . ' con codigo I y ' . Hallazgos::n($A['faltaSR'])
                . ' sin registro · ' . Hallazgos::pct($m['aus']) . ' de los dias programados',
                Metricas::tono($m['aus'], $this->par->meta('aus'), false)),
            'faltones'    => $this->valor('Reincidentes', Hallazgos::n($m['faltones']), 'personas',
                'Faltan a mas del 10 % de sus dias programados, sobre ' . Hallazgos::n($m['conProg'])
                . ' personas con al menos ' . Metricas::MIN_DIAS_PROG . ' dias en el periodo',
                $m['conProg'] && $m['faltones'] / max(1, $m['conProg']) > 0.15 ? 'crit' : 'warn'),
            default       => null,
        };
    }

    /** Tarjeta protagonista: el indice de cumplimiento con sus cuatro dimensiones. */
    private function indice(array $A, array $m, ?array $mPrev): array
    {
        $dims = [
            ['Asistencia', $m['asis'], Parametros::PESOS_INDICE['asis']],
            ['Puntualidad', $m['punt'], Parametros::PESOS_INDICE['punt']],
            ['Permanencia', $m['jorOk'], Parametros::PESOS_INDICE['jor']],
            ['Marcacion completa', $m['compPct'], Parametros::PESOS_INDICE['comp']],
        ];

        $partes = [];
        foreach ($dims as [$nombre, $valor, $peso]) {
            $partes[] = ['nombre' => $nombre, 'valor' => $valor, 'peso' => $peso * 100];
        }

        $v = $m['indice'];

        return [
            'tipo'   => 'indice',
            'titulo' => 'Indice de cumplimiento de asistencia',
            'valor'  => $v,
            'texto'  => Hallazgos::n($v, 1),
            'unidad' => '/ 100',
            'tono'   => $v === null ? 'warn' : ($v >= 90 ? 'good' : ($v >= 80 ? 'warn' : 'crit')),
            'etiqueta' => $v === null ? 'sin datos' : ($v >= 90 ? 'bueno' : ($v >= 80 ? 'aceptable' : 'critico')),
            'delta'  => $mPrev && $mPrev['indice'] !== null && $v !== null ? $v - $mPrev['indice'] : null,
            'partes' => $partes,
            'aro'    => self::aro($v),
            'nota'   => 'Promedio ponderado de las cuatro dimensiones; los pesos se muestran en cada barra.',
        ];
    }

    /** El costo de la inasistencia, en horas y en personas equivalentes. */
    private function capacidad(array $A, array $m): array
    {
        $meta = $this->par->meta('aus');

        return [
            'tipo'   => 'valor',
            'titulo' => 'Capacidad perdida por inasistencia',
            'valor'  => $m['hPerd'],
            'texto'  => Hallazgos::n($m['hPerd']),
            'unidad' => 'h',
            'tono'   => Metricas::tono($m['aus'], $meta, false),
            'etiqueta' => Metricas::tono($m['aus'], $meta, false) === 'good' ? 'dentro de la meta'
                : (Metricas::tono($m['aus'], $meta, false) === 'warn' ? 'sobre la meta' : 'critico'),
            'detalle' => Hallazgos::n($A['falta']) . ' jornadas sin justificar valoradas a la jornada objetivo de cada perfil · equivale a '
                . Hallazgos::n($m['fte'], 1) . ' personas ausentes todo el periodo',
            'delta' => null,
        ];
    }

    private function porcentaje(string $titulo, ?float $valor, string $metaClave, bool $hiGood,
        array $m, ?array $mPrev, string $claveMetrica, string $detalle): array
    {
        $meta = $this->par->meta($metaClave);
        $tono = Metricas::tono($valor, $meta, $hiGood);

        return [
            'tipo'   => 'pct',
            'titulo' => $titulo,
            'valor'  => $valor,
            'texto'  => Hallazgos::n($valor, 1),
            'unidad' => '%',
            'tono'   => $tono,
            'etiqueta' => $this->etiqueta($tono, $hiGood),
            'meta'   => $meta[0],
            'metaTexto' => 'meta ' . Hallazgos::n($meta[0]) . ' %',
            'delta'  => ($mPrev && $mPrev[$claveMetrica] !== null && $valor !== null)
                ? $valor - $mPrev[$claveMetrica] : null,
            'deltaBueno' => $hiGood,
            'detalle' => $detalle,
        ];
    }

    private function valor(string $titulo, string $texto, string $unidad, string $detalle, string $tono = 'info'): array
    {
        return [
            'tipo' => 'valor', 'titulo' => $titulo, 'texto' => $texto, 'unidad' => $unidad,
            'tono' => $tono, 'etiqueta' => null, 'detalle' => $detalle, 'delta' => null, 'valor' => null,
        ];
    }

    /**
     * Aro de cumplimiento: un arco de 270 grados donde el indice se lee de un vistazo.
     * Se dibuja aqui, y no en el navegador, para que salga igual al imprimir.
     */
    public static function aro(?float $valor, int $lado = 108): string
    {
        $v = max(0, min(100, $valor ?? 0));
        $r = $lado / 2 - 9;
        $c = $lado / 2;

        // Arco de 270 grados que arranca abajo a la izquierda.
        $largo = 2 * M_PI * $r * 0.75;
        $avance = $largo * $v / 100;

        $inicio = 135;
        $p = static function (float $grados) use ($c, $r): string {
            $rad = deg2rad($grados);

            return sprintf('%.2f %.2f', $c + $r * cos($rad), $c + $r * sin($rad));
        };

        $d = 'M' . $p($inicio) . ' A' . sprintf('%.2f %.2f', $r, $r) . ' 0 1 1 ' . $p($inicio + 270);

        return '<svg class="aro" viewBox="0 0 ' . $lado . ' ' . $lado . '" role="img" aria-label="Indice '
            . number_format($v, 1, ',', '.') . ' sobre 100">'
            . '<path d="' . $d . '" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="9" stroke-linecap="round"/>'
            . '<path d="' . $d . '" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round"'
            . ' stroke-dasharray="' . sprintf('%.2f %.2f', $avance, $largo) . '"/>'
            . '</svg>';
    }

    private function etiqueta(string $tono, bool $hiGood): string
    {
        return match ($tono) {
            'good' => $hiGood ? 'en meta' : 'dentro de la meta',
            'warn' => $hiGood ? 'a mejorar' : 'en observacion',
            default => 'critico',
        };
    }
}
