<?php

namespace App\Services\Asistencia;

/**
 * Convierte los acumulados en los indicadores que leen Gerencia, RRHH y Planta.
 *
 * Todas las tasas se calculan sobre el universo del alcance (fecha, area, empleado)
 * y devuelven null cuando no hay denominador, para que la tarjeta muestre "—" en vez
 * de un cero que se lee como un dato real.
 */
final class Metricas
{
    /** Un empleado con al menos estos dias programados entra en el conteo de reincidentes. */
    public const MIN_DIAS_PROG = 10;

    /** Porcentaje de faltas sobre dias programados a partir del cual es reincidente. */
    public const UMBRAL_FALTON = 0.10;

    public static function de(array $A): array
    {
        $m = [];

        $m['asis']     = self::pct($A['presProg'], $A['prog']);
        $m['aus']      = self::pct($A['falta'], $A['prog']);
        $m['punt']     = self::pct($A['puntual'], $A['puntual'] + $A['atrasos']);
        $m['atrProm']  = $A['atrasos'] ? $A['minAtr'] / $A['atrasos'] : null;
        $m['jorOk']    = self::pct($A['jor'][2] + $A['jor'][3], $A['jorVal']);
        $m['jorMenos'] = self::pct($A['jor'][1], $A['jorVal']);
        $m['incPct']   = self::pct($A['inc'], $A['pres']);
        $m['compPct']  = $m['incPct'] === null ? null : 100 - $m['incPct'];

        // Horas
        $m['hTrab']  = $A['minTrab'] / 60;
        $m['hFds']   = $A['minExtraNoLab'] / 60;
        $m['hSobre'] = $A['minSobre'] / 60;
        $m['hFer']   = $A['minExtraFer'] / 60;
        $m['hFinde'] = $A['minExtraFds'] / 60;
        $m['hPerd']  = $A['minFalta'] / 60;
        $m['hExtra'] = $m['hFds'] + $m['hSobre'];
        $m['hDia']   = $A['pres'] ? ($A['minTrab'] / 60) / $A['pres'] : null;

        $m['ferDias'] = $A['ferDias'];
        $m['fdsDias'] = $A['fdsDias'];
        $m['vacDias'] = $A['vac'];
        $m['licDias'] = $A['lic'];
        $m['vacPers'] = count($A['vacEmps']);
        $m['licPers'] = count($A['licEmps']);

        // Ausencia total: incluye vacaciones y licencias, que tambien restan capacidad.
        $m['ausTot'] = self::pct(
            $A['falta'] + $A['vac'] + $A['lic'],
            $A['prog'] + $A['vac'] + $A['lic']
        );

        $m['salAnt']     = self::pct($A['salAnt'], $A['jorVal']);
        $m['salAntProm'] = $A['salAnt'] ? $A['minSalAnt'] / $A['salAnt'] : null;

        $m['presDia']   = $A['diasProg'] ? $A['presProg'] / $A['diasProg'] : null;
        $m['cobertura'] = ($m['presDia'] !== null && $A['empleadosFin'])
            ? 100 * $m['presDia'] / $A['empleadosFin'] : null;

        // Equivalente a personas ausentes todo el periodo.
        $m['fte'] = ($A['prog'] && $A['empleados'])
            ? $A['falta'] * $A['empleados'] / $A['prog'] : null;

        $m['faltones'] = 0;
        $m['conProg']  = 0;

        foreach ($A['empProg'] as $e => $dias) {
            if ($dias >= self::MIN_DIAS_PROG) {
                $m['conProg']++;
                if (($A['empFalta'][$e] ?? 0) / $dias > self::UMBRAL_FALTON) {
                    $m['faltones']++;
                }
            }
        }

        $m['indice'] = self::indice($m);

        return $m;
    }

    /**
     * Indice de cumplimiento 0-100: promedio ponderado de asistencia, puntualidad,
     * permanencia y marcacion completa. Los pesos son explicitos y se muestran en la
     * propia tarjeta para que nadie tenga que adivinar de donde sale el numero.
     */
    public static function indice(array $m): ?float
    {
        $partes = [
            [$m['asis'], Parametros::PESOS_INDICE['asis']],
            [$m['punt'], Parametros::PESOS_INDICE['punt']],
            [$m['jorOk'], Parametros::PESOS_INDICE['jor']],
            [$m['compPct'], Parametros::PESOS_INDICE['comp']],
        ];

        $suma = $peso = 0.0;

        foreach ($partes as [$valor, $w]) {
            if ($valor !== null) {
                $suma += $valor * $w;
                $peso += $w;
            }
        }

        return $peso ? $suma / $peso : null;
    }

    public static function pct(int|float $a, int|float $b): ?float
    {
        return $b > 0 ? 100 * $a / $b : null;
    }

    /** Estado del semaforo: hiGood = un valor mas alto es mejor. */
    public static function tono(?float $v, array $meta, bool $hiGood): string
    {
        if ($v === null) {
            return 'warn';
        }

        [$bueno, $malo] = $meta;

        if ($hiGood) {
            return $v >= $bueno ? 'good' : ($v <= $malo ? 'crit' : 'warn');
        }

        return $v <= $bueno ? 'good' : ($v >= $malo ? 'crit' : 'warn');
    }
}
