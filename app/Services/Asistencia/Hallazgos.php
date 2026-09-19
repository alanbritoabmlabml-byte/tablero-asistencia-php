<?php

namespace App\Services\Asistencia;

/**
 * Lectura automatica del periodo filtrado: que esta bien, que preocupa y donde actuar.
 *
 * Cada hallazgo declara la regla que lo dispara, para que nadie tenga que confiar en
 * el texto a ciegas, y una evidencia que el navegador dibuja como grafico resumen.
 */
final class Hallazgos
{
    /** Un area necesita este minimo de dias programados para entrar en las comparaciones. */
    public const MIN_DIAS_AREA = 40;

    /** Cuantos hallazgos muestra cada vista, de mayor a menor severidad. */
    public const LIMITES = ['gerencia' => 4, 'rrhh' => 6, 'planta' => 5, 'explorar' => 9];

    private array $out = [];

    public function __construct(
        private Paquete $paquete,
        private Parametros $par,
    ) {}

    public function generar(array $A, array $m, ?array $mPrev, string $vista): array
    {
        $this->out = [];

        $deps = [];
        foreach ($A['byDep'] as $i => $d) {
            if ($d['prog'] >= self::MIN_DIAS_AREA) {
                $deps[] = $i;
            }
        }

        $this->asistencia($A, $m, $mPrev);
        $this->concentracionAusentismo($A, $m, $deps);
        $this->decilFaltas($A);
        $this->puntualidad($A, $m, $mPrev, $deps);
        $this->calidadMarcacion($A, $m, $deps);
        $this->salidasAnticipadas($A, $m, $deps);
        $this->brechaSecciones($A);
        $this->permanencia($A, $m);
        $this->horasFueraJornada($A, $m, $deps);
        $this->feriadosTrabajados($A);
        $this->vacacionesLicencias($A);

        $orden = ['crit' => 0, 'warn' => 1, 'info' => 2, 'good' => 3];
        usort($this->out, fn ($x, $y) => $orden[$x['sev']] <=> $orden[$y['sev']]);

        return array_slice($this->out, 0, self::LIMITES[$vista] ?? 9);
    }

    private function add(string $sev, string $texto, ?string $accion, string $regla, ?array $evidencia = null): void
    {
        $this->out[] = compact('sev', 'texto', 'accion', 'regla', 'evidencia');
    }

    /* ------------------------------------------------------------- reglas */

    private function asistencia(array $A, array $m, ?array $mPrev): void
    {
        if ($m['asis'] === null) {
            return;
        }

        $meta = $this->par->meta('asis');
        $d    = ($mPrev && $mPrev['asis'] !== null) ? $m['asis'] - $mPrev['asis'] : null;

        $texto = 'Asistencia efectiva de ' . self::b(self::pct($m['asis'])) . ($m['asis'] >= $meta[0]
            ? ', sobre la meta de ' . self::n($meta[0]) . ' %.'
            : ', ' . self::n($meta[0] - $m['asis'], 1) . ' puntos bajo la meta de ' . self::n($meta[0]) . ' %.');

        if ($d !== null) {
            $texto .= ' ' . (abs($d) < 0.05 ? 'Sin cambio'
                : ($d > 0 ? 'Mejora ' : 'Cae ') . self::n(abs($d), 1) . ' pts') . ' frente al periodo anterior.';
        }

        $this->add(
            Metricas::tono($m['asis'], $meta, true),
            $texto,
            'Equivale a ' . self::n($m['fte'] ?? 0, 1) . ' personas ausentes durante todo el periodo.',
            'Semaforo verde desde ' . self::n($meta[0]) . ' %, critico bajo ' . self::n($meta[1]) . ' %. Base: '
                . self::n($A['presProg']) . ' dias-persona cubiertos de ' . self::n($A['prog']) . ' programados.',
            $this->serieSemanal($A, fn ($w) => Metricas::pct($w['pres'], $w['prog']),
                'Asistencia por semana vs meta', $meta[0], 'pct'),
        );
    }

    private function concentracionAusentismo(array $A, array $m, array $deps): void
    {
        if (count($deps) <= 1) {
            return;
        }

        usort($deps, fn ($x, $y) => ($A['byDep'][$y]['falta'] / max(1, $A['byDep'][$y]['prog']))
            <=> ($A['byDep'][$x]['falta'] / max(1, $A['byDep'][$x]['prog'])));

        $peor = $deps[0];
        $tasa = 100 * $A['byDep'][$peor]['falta'] / max(1, $A['byDep'][$peor]['prog']);

        if ($tasa <= ($m['aus'] ?? 0) * 1.3 || $A['byDep'][$peor]['falta'] < 10) {
            return;
        }

        $this->add(
            $tasa > 8 ? 'crit' : 'warn',
            self::b($this->dep($peor)) . ' concentra el mayor ausentismo: ' . self::b(self::pct($tasa))
                . ' frente a ' . self::pct($m['aus']) . ' del conjunto (' . self::n($A['byDep'][$peor]['falta']) . ' dias de falta).',
            'Revisar con la jefatura del area las causas de los dias sin registro.',
            'Se destaca el area cuyo ausentismo supera en mas de 30 % al del conjunto y suma al menos 10 faltas.',
            $this->topAreas($A, $deps, fn ($d) => Metricas::pct($d['falta'], $d['prog']), 6, $peor,
                '% ausentismo por area (top 6)', $m['aus'], 'conjunto ' . self::pct($m['aus'])),
        );
    }

    private function decilFaltas(array $A): void
    {
        $lista = array_values($A['empFalta']);

        if (count($lista) < 20 || $A['falta'] <= 0) {
            return;
        }

        rsort($lista);
        $top  = max(1, (int) round(count($lista) * 0.1));
        $suma = array_sum(array_slice($lista, 0, $top));
        $share = 100 * $suma / $A['falta'];

        if ($share < 25) {
            return;
        }

        $this->add(
            'info',
            'El ' . self::b('10 % del personal con mas faltas') . ' (' . $top . ' personas) acumula el '
                . self::b(self::n($share) . ' %') . ' de los dias de falta.',
            'Un seguimiento individual a ese grupo mueve el indicador mas que una campana general.',
            'Se informa cuando el decil superior concentra 25 % o mas de las faltas ('
                . self::n($suma) . ' de ' . self::n($A['falta']) . ').',
            ['tipo' => 'share', 'titulo' => 'Reparto de los dias de falta', 'datos' => [
                ['label' => '10 % con mas faltas', 'v' => $suma, 'serie' => 2],
                ['label' => 'resto del personal', 'v' => $A['falta'] - $suma, 'serie' => 1],
            ]],
        );
    }

    private function puntualidad(array $A, array $m, ?array $mPrev, array $deps): void
    {
        if ($m['punt'] === null || $A['atrasos'] <= 0) {
            return;
        }

        $meta = $this->par->meta('punt');
        $conIngresos = array_values(array_filter($deps,
            fn ($i) => $A['byDep'][$i]['atr'] + $A['byDep'][$i]['punt'] > 20));

        usort($conIngresos, fn ($x, $y) => ($A['byDep'][$y]['atr'] / max(1, $A['byDep'][$y]['atr'] + $A['byDep'][$y]['punt']))
            <=> ($A['byDep'][$x]['atr'] / max(1, $A['byDep'][$x]['atr'] + $A['byDep'][$x]['punt'])));

        $peor = $conIngresos[0] ?? null;
        $dp   = ($mPrev && $mPrev['punt'] !== null) ? $m['punt'] - $mPrev['punt'] : null;

        $texto = 'Puntualidad de ' . self::b(self::pct($m['punt'])) . ' con un atraso promedio de '
            . self::b(self::n($m['atrProm']) . ' min')
            . ($dp !== null ? ($dp >= 0 ? ' (+' : ' (') . self::n($dp, 1) . ' pts vs anterior)' : '') . '.';

        if ($peor !== null) {
            $d = $A['byDep'][$peor];
            $texto .= ' El area con mas atrasos es ' . self::b($this->dep($peor)) . ' ('
                . self::pct(100 * $d['atr'] / max(1, $d['atr'] + $d['punt']), 0) . ' de sus ingresos).';
        }

        $horarios = [];
        foreach ($this->par->perfiles as $pf) {
            $horarios[] = $pf['nombre'] . ' ' . self::hhmm($pf['entrada']);
        }

        $this->add(
            Metricas::tono($m['punt'], $meta, true),
            $texto,
            'Medido contra el horario del perfil de cada area (' . implode(', ', $horarios) . ').',
            'Meta ' . self::n($meta[0]) . ' %, critico bajo ' . self::n($meta[1]) . ' %. Atraso = primera marcacion despues de ingreso + tolerancia. '
                . self::n($A['atrasos']) . ' atrasos sobre ' . self::n($A['atrasos'] + $A['puntual']) . ' ingresos validos.',
            $this->topAreas($A, $conIngresos, fn ($d) => Metricas::pct($d['atr'], $d['atr'] + $d['punt']), 6, $peor,
                '% de ingresos con atraso por area (top 6)', 100 - $m['punt'], 'conjunto ' . self::pct(100 - $m['punt'])),
        );
    }

    private function calidadMarcacion(array $A, array $m, array $deps): void
    {
        if ($m['incPct'] === null) {
            return;
        }

        $meta = $this->par->meta('comp');

        if ($m['incPct'] <= 5) {
            $this->add(
                'good',
                'Marcacion completa en ' . self::b(self::pct(100 - $m['incPct'])) . ' de los dias presentes.',
                null,
                'Marcacion completa meta ' . self::n($meta[0]) . ' %.',
                ['tipo' => 'share', 'titulo' => 'Dias presentes segun marcacion', 'datos' => [
                    ['label' => 'completa', 'v' => $A['pres'] - $A['inc'], 'serie' => 1],
                    ['label' => 'incompleta', 'v' => $A['inc'], 'serie' => 2],
                ]],
            );

            return;
        }

        $conPresencia = array_values(array_filter($deps, fn ($i) => $A['byDep'][$i]['presT'] > 20));

        $this->add(
            $m['incPct'] > (100 - $meta[1]) ? 'crit' : 'warn',
            self::b(self::pct($m['incPct'])) . ' de los dias presentes tienen marcacion incompleta ('
                . self::n($A['inc']) . ' dias); ' . self::b(self::n($A['sinEntrada'])) . ' son salidas sin ingreso.',
            'Sin ingreso y salida no hay calculo de jornada ni de horas extra: es el primer dato a corregir en el lector.',
            'Marcacion completa meta ' . self::n($meta[0]) . ' %, critico bajo ' . self::n($meta[1])
                . ' %. Incompleta = numero impar de marcaciones en el dia.',
            $this->topAreas($A, $conPresencia, fn ($d) => Metricas::pct($d['inc'], $d['presT']), 6, null,
                '% de dias con marcacion impar por area (top 6)', $m['incPct'], 'conjunto ' . self::pct($m['incPct'])),
        );
    }

    private function salidasAnticipadas(array $A, array $m, array $deps): void
    {
        if ($m['salAnt'] === null || $A['jorVal'] <= 50) {
            return;
        }

        $meta = $this->par->meta('sal');
        $conJornada = array_values(array_filter($deps, fn ($i) => $A['byDep'][$i]['jorVal'] > 20));

        usort($conJornada, fn ($x, $y) => ($A['byDep'][$y]['salAnt'] / max(1, $A['byDep'][$y]['jorVal']))
            <=> ($A['byDep'][$x]['salAnt'] / max(1, $A['byDep'][$x]['jorVal'])));

        $peor  = $conJornada[0] ?? null;
        $texto = self::b(self::pct($m['salAnt'])) . ' de los dias evaluables terminan con salida anticipada ('
            . self::n($A['salAnt']) . ' dias, ' . self::n($m['salAntProm'] ?? 0) . ' min antes en promedio).';

        if ($peor !== null && $A['byDep'][$peor]['salAnt']) {
            $texto .= ' Se concentra en ' . self::b($this->dep($peor)) . ' ('
                . self::pct(100 * $A['byDep'][$peor]['salAnt'] / max(1, $A['byDep'][$peor]['jorVal']), 0) . ').';
        }

        $tolS = array_values(array_unique(array_column($this->par->perfiles, 'tolS')));

        $this->add(
            Metricas::tono($m['salAnt'], $meta, false),
            $texto,
            'Hora de salida y tolerancia son parametros de cada perfil en Configuracion.',
            'Meta maxima ' . self::n($meta[0]) . ' %, critico sobre ' . self::n($meta[1])
                . ' %. Salida anticipada = ultima marcacion antes de la hora de salida del perfil menos '
                . implode('/', $tolS) . ' min, en dia laborable con marcacion par.',
            $this->topAreas($A, $conJornada, fn ($d) => Metricas::pct($d['salAnt'], $d['jorVal']), 6, $peor,
                '% de dias con salida anticipada por area (top 6)', $meta[0], 'meta ' . self::n($meta[0]) . ' %'),
        );
    }

    private function brechaSecciones(array $A): void
    {
        $secs = [];
        foreach ($A['bySec'] as $k => $S) {
            if ($k !== Parametros::SECCION_ADMIN && $S['prog'] >= self::MIN_DIAS_AREA) {
                $secs[] = $k;
            }
        }

        if (count($secs) <= 1) {
            return;
        }

        usort($secs, fn ($x, $y) => ($A['bySec'][$x]['pres'] / max(1, $A['bySec'][$x]['prog']))
            <=> ($A['bySec'][$y]['pres'] / max(1, $A['bySec'][$y]['prog'])));

        $peor  = $A['bySec'][$secs[0]];
        $mejor = $A['bySec'][$secs[count($secs) - 1]];
        $ra    = 100 * $peor['pres'] / max(1, $peor['prog']);
        $rb    = 100 * $mejor['pres'] / max(1, $mejor['prog']);

        if ($rb - $ra < 3) {
            return;
        }

        $meta  = $this->par->meta('asis');
        $datos = [];
        foreach (array_reverse($secs) as $k) {
            $datos[] = ['label' => $k, 'v' => Metricas::pct($A['bySec'][$k]['pres'], $A['bySec'][$k]['prog']),
                        'hl' => $k === $secs[0]];
        }

        $this->add(
            $ra < $meta[1] ? 'crit' : 'warn',
            'Entre secciones de planta hay ' . self::b(self::n($rb - $ra, 1) . ' pts') . ' de brecha: '
                . self::b($secs[0]) . ' ' . self::pct($ra) . ' frente a '
                . self::b($secs[count($secs) - 1]) . ' ' . self::pct($rb) . '.',
            'Usa el filtro de area para aislar la seccion y ver su detalle.',
            'Se informa cuando la brecha entre la mejor y la peor seccion de planta supera 3 puntos de asistencia (secciones con 40 o mas dias programados).',
            ['tipo' => 'bars', 'titulo' => 'Asistencia por seccion de planta', 'formato' => 'pct',
             'ref' => $meta[0], 'refLabel' => 'meta ' . self::n($meta[0]) . ' %', 'datos' => $datos],
        );
    }

    private function permanencia(array $A, array $m): void
    {
        if ($m['jorOk'] === null || $A['jorVal'] <= 50) {
            return;
        }

        $meta = $this->par->meta('jor');

        $jornadas = [];
        foreach ($this->par->perfiles as $pf) {
            $jornadas[] = $pf['nombre'] . ' ' . self::n($pf['obj'] / 60, 1) . ' h';
        }

        $this->add(
            Metricas::tono($m['jorOk'], $meta, true),
            self::b(self::pct($m['jorOk'])) . ' de los dias evaluables alcanzan la jornada de su perfil; '
                . self::b(self::n($A['jor'][1])) . ' dias quedan por debajo y ' . self::n($A['jor'][3]) . ' por encima.',
            'La jornada objetivo es por perfil: ' . implode(', ', $jornadas) . '.',
            'Meta ' . self::n($meta[0]) . ' %, critico bajo ' . self::n($meta[1])
                . ' %. Cuenta como alcanzada la jornada dentro de la holgura (± '
                . ($this->par->perfiles['fabrica']['tolJ'] ?? 30) . ' min) o por encima; solo dias con marcacion par.',
            ['tipo' => 'share', 'titulo' => 'Dias evaluables frente a la jornada', 'datos' => [
                ['label' => 'por debajo', 'v' => $A['jor'][1], 'serie' => 2],
                ['label' => 'en la jornada', 'v' => $A['jor'][2], 'serie' => 1],
                ['label' => 'por encima', 'v' => $A['jor'][3], 'serie' => 3],
            ]],
        );
    }

    private function horasFueraJornada(array $A, array $m, array $deps): void
    {
        if ($m['hFds'] <= 0) {
            return;
        }

        usort($deps, fn ($x, $y) => $A['byDep'][$y]['extraNoLab'] <=> $A['byDep'][$x]['extraNoLab']);
        $peor = $deps[0] ?? null;

        $texto = self::b(self::n($m['hFds']) . ' h') . ' trabajadas en sabados, domingos y feriados';
        if ($peor !== null && $A['minExtraNoLab'] > 0) {
            $texto .= ', ' . self::n(100 * $A['byDep'][$peor]['extraNoLab'] / $A['minExtraNoLab'])
                . ' % de ellas en ' . self::b($this->dep($peor));
        }

        $this->add(
            'info',
            $texto . '.',
            'Mas ' . self::n($m['hSobre']) . ' h de exceso sobre la jornada en dias habiles.',
            'Informativo. Toda marcacion en dia no laborable del perfil (fin de semana o feriado) se cuenta como hora extra; en dia habil, el exceso sobre las horas estimadas.',
            $this->topAreas($A, $deps, fn ($d) => ($d['extraNoLab'] + $d['sobre']) / 60, 6, $peor,
                'Horas extra por area (top 6)', null, null, 'horas'),
        );
    }

    private function feriadosTrabajados(array $A): void
    {
        if ($A['ferDias'] <= 0 || ! $A['feriados']) {
            return;
        }

        $fer = $A['feriados'];
        uasort($fer, fn ($x, $y) => $y['pres'] <=> $x['pres']);
        $claveMayor = array_key_first($fer);
        $mayor      = $fer[$claveMayor];

        $secs = $mayor['secs'];
        arsort($secs);
        $seccion = array_key_first($secs);

        $datos = [];
        foreach (array_slice($fer, 0, 6, true) as $k => $v) {
            $datos[] = ['label' => substr($k, 8, 2) . '/' . substr($k, 5, 2), 'v' => $v['pres'],
                        'hl' => $k === $claveMayor];
        }

        $this->add(
            'info',
            self::b(self::n($A['ferDias']) . ' jornadas') . ' se trabajaron en feriado ('
                . self::b(self::n($A['minExtraFer'] / 60) . ' h') . '); la mayor fue el '
                . self::fechaLarga($this->paquete->fechas[$mayor['d']]) . ' con '
                . self::b(self::n($mayor['pres']) . ' personas')
                . ($seccion ? ', sobre todo de ' . $seccion : '') . '.',
            'Toda esa carga es hora extra por definicion, mas ' . self::n($A['minExtraFds'] / 60) . ' h de fines de semana.',
            'Informativo. El calendario de feriados se edita en Configuracion; cualquier marcacion en esos dias entra como hora extra.',
            ['tipo' => 'bars', 'titulo' => 'Personas por feriado trabajado (top 6)', 'formato' => 'entero', 'datos' => $datos],
        );
    }

    private function vacacionesLicencias(array $A): void
    {
        if ($A['vac'] + $A['lic'] <= 0 || ! $A['byMes']) {
            return;
        }

        $meses = array_keys($A['byMes']);
        $pico  = $meses[0];
        foreach ($meses as $k) {
            if ($A['byMes'][$k]['vac'] + $A['byMes'][$k]['vacN']
                > $A['byMes'][$pico]['vac'] + $A['byMes'][$pico]['vacN']) {
                $pico = $k;
            }
        }

        $valores = $etiquetas = [];
        foreach ($meses as $k) {
            $b = $A['byMes'][$k];
            $valores[]   = $b['vac'] + $b['vacN'] + $b['licBM'] + $b['licOt'];
            $etiquetas[] = self::mes($k);
        }

        $this->add(
            'info',
            self::b(self::n($A['vac']) . ' dias de vacacion') . ' en '
                . self::b(self::n(count($A['vacEmps'])) . ' personas') . ' y '
                . self::b(self::n($A['lic']) . ' dias de licencia') . ' ('
                . self::n($A['licTipos']['BM'] ?? 0) . ' de baja medica). El mes de mayor salida fue '
                . self::mes($pico) . '.',
            'Representan ' . self::pct(Metricas::pct($A['vac'] + $A['lic'], $A['prog'] + $A['vac'] + $A['lic']))
                . ' del calendario del periodo y no cuentan como ausentismo.',
            'Informativo. V y VDN son vacacion; BM, LR, LDM y LPM son licencia. Un dia cuenta una vez por persona.',
            ['tipo' => 'cols', 'titulo' => 'Dias de vacacion y licencia por mes', 'formato' => 'entero',
             'labels' => $etiquetas, 'datos' => $valores, 'lo' => 0],
        );
    }

    /* ------------------------------------------------------------ helpers */

    private function topAreas(array $A, array $deps, callable $valor, int $n, ?int $destacado,
        string $titulo, ?float $ref, ?string $refLabel, string $formato = 'pct'): array
    {
        $filas = [];
        foreach ($deps as $i) {
            $v = $valor($A['byDep'][$i]);
            if ($v !== null) {
                $filas[] = ['label' => $this->dep($i), 'v' => $v, 'hl' => $i === $destacado];
            }
        }

        usort($filas, fn ($x, $y) => $y['v'] <=> $x['v']);

        return ['tipo' => 'bars', 'titulo' => $titulo, 'formato' => $formato,
                'ref' => $ref, 'refLabel' => $refLabel, 'datos' => array_slice($filas, 0, $n)];
    }

    private function serieSemanal(array $A, callable $valor, string $titulo, ?float $ref, string $formato): array
    {
        $keys = array_keys($A['bySemana']);
        // Solo semanas con al menos 3 dias laborables: una semana partida distorsiona cualquier tasa.
        $keys = array_values(array_filter($keys, fn ($k) => $A['bySemana'][$k]['prog'] > 0));
        $keys = array_slice($keys, -10);

        $datos = $labels = [];
        foreach ($keys as $k) {
            $datos[]  = $valor($A['bySemana'][$k]);
            $labels[] = substr($k, 8, 2) . '/' . substr($k, 5, 2);
        }

        return ['tipo' => 'cols', 'titulo' => $titulo, 'formato' => $formato,
                'ref' => $ref, 'labels' => $labels, 'datos' => $datos, 'hiGood' => true];
    }

    private function dep(int $i): string
    {
        return $this->paquete->departamentos[$i] ?? '—';
    }

    private static function b(string $s): string
    {
        return '<b>' . $s . '</b>';
    }

    public static function n(int|float|null $v, int $dec = 0): string
    {
        return $v === null ? '—' : number_format($v, $dec, ',', '.');
    }

    public static function pct(?float $v, int $dec = 1): string
    {
        return $v === null ? '—' : number_format($v, $dec, ',', '.') . ' %';
    }

    public static function hhmm(int $min): string
    {
        return sprintf('%02d:%02d', intdiv($min, 60) % 24, $min % 60);
    }

    public static function mes(string $aaaaMm): string
    {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        return $m[(int) substr($aaaaMm, 5, 2) - 1] ?? $aaaaMm;
    }

    public static function fechaLarga(string $fecha): string
    {
        $dias = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];
        $ts   = strtotime($fecha . ' 00:00:00');

        return $dias[(int) date('w', $ts)] . ' ' . substr($fecha, 8, 2) . '/'
            . self::mes($fecha) . '/' . substr($fecha, 0, 4);
    }
}
