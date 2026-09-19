<?php

namespace App\Services\Asistencia;

use Generator;

/**
 * Convierte las marcaciones crudas de cada dia en los valores que consumen los
 * tableros: estado del dia, minutos trabajados, atraso, jornada, salida anticipada
 * y horas extra.
 *
 * Reglas acordadas con Gerencia y RRHH:
 *  - Falta = codigo I, mas SR en dia programado del perfil.
 *  - El almuerzo solo se descuenta si el dia tiene exactamente dos marcaciones y el
 *    lapso supera el umbral del perfil (si no, ya viene descontado por marcacion).
 *  - Numero impar de marcaciones = dia con marcacion incompleta: no hay jornada ni
 *    horas extra calculables, es el primer dato a corregir en el lector.
 *  - Marcacion en dia no programado (fin de semana o feriado) = todo es hora extra.
 *  - La tolerancia de ingreso se extiende por fecha en dias de lluvia, para toda la
 *    empresa, no por area.
 */
final class Motor
{
    public function __construct(private Parametros $par) {}

    /**
     * Recorre todos los dias de todos los empleados y devuelve un registro por dia
     * vigente. Es un generador para poder importar cientos de miles de dias sin
     * cargar todo en memoria.
     *
     * @return Generator<array<string,mixed>>
     */
    public function calcular(Paquete $p): Generator
    {
        $feriados = $this->setDeFeriados($p);
        $programado = $this->diasProgramados($p, $feriados);
        $lluvia = $this->toleranciaPorDia($p);

        // Perfil y dias programados resueltos una sola vez por departamento.
        $perfilDep = $progDep = [];
        foreach ($p->departamentos as $i => $dep) {
            $g             = $this->par->grupoDe($dep);
            $perfilDep[$i] = $this->par->perfiles[$g];
            $progDep[$i]   = $programado[$g];
        }

        foreach ($p->empleados as $ei => $e) {
            $dep = $e['dep'];

            for ($d = $e['desde']; $d <= $e['hasta']; $d++) {
                yield $this->registro(
                    $ei, $e, $d, $dep,
                    $e['celdas'][$d] ?? '',
                    $perfilDep[$dep], $progDep[$dep][$d],
                    $p->dow[$d], $feriados[$d], $lluvia[$d],
                );
            }
        }
    }

    /** Calcula un dia suelto. Se usa igual en la importacion y en el recalculo. */
    public function registro(
        int $empleadoIdx, array $empleado, int $dia, int $dep, string $celda,
        array $perfil, int $programado, int $dow, int $feriado, int $tolLluvia,
    ): array {
        [$marcas, $codigos] = $this->partirCelda($celda);

        $q  = count($marcas);
        $pf = $this->par->perfilDia($perfil, $dow, $q > 0 ? $marcas[0] : -1);
        $tol = $tolLluvia >= 0 ? $tolLluvia : $pf['tol'];

        $reg = [
            'empleado_idx' => $empleadoIdx,
            'dia'          => $dia,
            'dep'          => $dep,
            'marcas'       => $marcas,
            'codigos'      => $codigos,
            'programado'   => $programado,
            'feriado'      => $feriado,
            'estado'       => Codigos::NOLAB,
            'trabajado'    => 0,
            'atraso'       => -1,
            'incompleta'   => 0,
            'jornada'      => 0,   // 0 = no evaluable, 1 = menos, 2 = en rango, 3 = mas
            'entrada'      => -1,
            'salida'       => -1,
            'extra'        => 0,
            'salida_anticipada' => 0,
            'objetivo'     => $pf['obj'],
        ];

        if ($q > 0) {
            $reg['estado'] = Codigos::PRESENTE;

            $primera = $marcas[0];
            $ultima  = $marcas[$q - 1];

            // Suma de los pares ingreso/salida.
            $trab = 0;
            for ($i = 0; $i + 1 < $q; $i += 2) {
                $trab += $marcas[$i + 1] - $marcas[$i];
            }

            if ($q % 2) {
                $reg['incompleta'] = 1;
            } elseif ($q === 2 && $trab >= $pf['umbralRefri']) {
                $trab -= $pf['refri'];
            }

            $reg['trabajado'] = max(0, $trab);

            // Una marcacion muy posterior al ingreso previsto no es una entrada: es una salida.
            $entradaValida = $primera <= $pf['entrada'] + $pf['vent'];

            if ($entradaValida) {
                $reg['entrada'] = $primera;
            }

            if ($q >= 2 || ! $entradaValida) {
                $reg['salida'] = $ultima;
            }

            if ($programado && $entradaValida && $primera > $pf['entrada'] + $tol) {
                $reg['atraso'] = $primera - $pf['entrada'];
            }

            if ($q >= 2 && $q % 2 === 0) {
                $reg['jornada'] = $reg['trabajado'] < $pf['obj'] - $pf['tolJ'] ? 1
                    : ($reg['trabajado'] > $pf['obj'] + $pf['tolJ'] ? 3 : 2);

                if ($programado && $pf['salida'] > 0 && $ultima < $pf['salida'] - $pf['tolS']) {
                    $reg['salida_anticipada'] = $pf['salida'] - $ultima;
                }
            }

            // Fuera de dia programado, toda la jornada es hora extra.
            $reg['extra'] = $programado ? max(0, $reg['trabajado'] - $pf['obj']) : $reg['trabajado'];

            return $reg;
        }

        $mascara = 0;
        foreach ($codigos as $c) {
            $mascara |= Codigos::bit($c);
        }

        if ($mascara & Codigos::VACACION) {
            $reg['estado'] = Codigos::VAC;
        } elseif ($mascara & Codigos::LICENCIA) {
            $reg['estado'] = Codigos::LIC;
        } elseif ($mascara & (Codigos::I | Codigos::SR)) {
            $reg['estado'] = $programado ? Codigos::FALTA : Codigos::NOLAB;
        }

        return $reg;
    }

    /** @return array{0:int[],1:string[]} */
    public function partirCelda(string $celda): array
    {
        if ($celda === '') {
            return [[], []];
        }

        $sp     = strpos($celda, ';');
        $mParte = $sp === false ? $celda : substr($celda, 0, $sp);
        $cParte = $sp === false ? '' : substr($celda, $sp + 1);

        $marcas = [];
        if ($mParte !== '') {
            foreach (explode(',', $mParte) as $m) {
                $marcas[] = ((int) substr($m, 0, 2)) * 60 + (int) substr($m, 2);
            }
        }

        // El lector puede registrar mas de seis marcaciones en un dia; se conservan las seis primeras.
        if (count($marcas) > 6) {
            $marcas = array_slice($marcas, 0, 6);
        }

        return [$marcas, $cParte === '' ? [] : explode('+', $cParte)];
    }

    /** @return int[] 1 si la fecha es feriado */
    public function setDeFeriados(Paquete $p): array
    {
        $set = array_fill(0, $p->dias(), 0);

        foreach ($this->par->feriados as $f) {
            $i = $p->indiceDeFecha($f);
            if ($i >= 0) {
                $set[$i] = 1;
            }
        }

        return $set;
    }

    /**
     * Dias programados por perfil: los dias laborables del perfil que no sean feriado.
     * Los perfiles con horario de sabado suman el sabado.
     *
     * @return array<string,int[]>
     */
    public function diasProgramados(Paquete $p, array $feriados): array
    {
        $out = [];

        foreach ($this->par->perfiles as $clave => $pf) {
            $laborable = array_fill(0, 8, 0);
            foreach (str_split($pf['lab'] ?? '12345') as $c) {
                $laborable[((int) $c) % 7] = 1;
            }
            if (! empty($pf['sab']['on'])) {
                $laborable[6] = 1;
            }

            $prog = [];
            foreach ($p->dow as $j => $dow) {
                $prog[$j] = ($laborable[$dow] && ! $feriados[$j]) ? 1 : 0;
            }

            $out[$clave] = $prog;
        }

        return $out;
    }

    /** @return int[] tolerancia extendida por dia de lluvia, -1 si no aplica */
    public function toleranciaPorDia(Paquete $p): array
    {
        $out = [];
        foreach ($p->fechas as $j => $f) {
            $out[$j] = $this->par->lluvia[$f] ?? -1;
        }

        return $out;
    }
}
