<?php

namespace App\Services\Asistencia;

/**
 * Suma los registros diarios del alcance activo y devuelve todos los acumulados que
 * alimentan tarjetas, graficos y tablas: por departamento, por seccion de planta,
 * por mes, por semana, por dia y por feriado.
 *
 * El universo (lo que miden las tarjetas) nunca lleva el filtro de novedad; el filtro
 * de novedad solo acota los graficos de detalle y la tabla, para que una tasa no
 * cambie de significado segun lo que este marcado.
 */
final class Agregador
{
    public function __construct(private Paquete $paquete, private Parametros $par) {}

    /**
     * @param  iterable<array<string,mixed>>  $registros
     */
    public function agregar(iterable $registros, Filtro $f, bool $esPeriodoAnterior = false): array
    {
        $p       = $this->paquete;
        $nDep    = count($p->departamentos);
        $usarNov = ! $esPeriodoAnterior && $f->hayNovedades();

        $A = $this->acumuladorVacio($nDep);

        // Personal vigente dentro del rango.
        $visible = [];
        foreach ($p->empleados as $i => $e) {
            if (! $f->aceptaEmpleado($e)) {
                continue;
            }
            $visible[$i] = true;

            if ($e['desde'] <= $f->hasta && $e['hasta'] >= $f->desde) {
                $A['empleados']++;
                $A['byDep'][$e['dep']]['emps']++;
            }
            if ($e['desde'] <= $f->hasta && $e['hasta'] >= $f->hasta) {
                $A['empleadosFin']++;
                $A['byDep'][$e['dep']]['empsFin']++;
            }
        }

        foreach ($registros as $r) {
            $d = $r['dia'];

            if ($d < $f->desde || $d > $f->hasta || ! isset($visible[$r['empleado_idx']])) {
                continue;
            }

            $this->acumularUniverso($A, $r, $d);

            if ($usarNov && ! $f->aceptaNovedad($r)) {
                continue;
            }

            $this->acumularFoco($A, $r, $d);
        }

        $this->cerrar($A, $f);

        return $A;
    }

    private function acumuladorVacio(int $nDep): array
    {
        $A = [
            'prog' => 0, 'presProg' => 0, 'pres' => 0, 'falta' => 0, 'faltaI' => 0, 'faltaSR' => 0,
            'vac' => 0, 'lic' => 0, 'noLab' => 0, 'atrasos' => 0, 'puntual' => 0, 'minAtr' => 0,
            'inc' => 0, 'sinEntrada' => 0, 'salAnt' => 0, 'minSalAnt' => 0, 'diasProg' => 0,
            'minTrab' => 0, 'minExtraNoLab' => 0, 'minSobre' => 0, 'minExtraFer' => 0,
            'minExtraFds' => 0, 'minFalta' => 0, 'ferDias' => 0, 'fdsDias' => 0,
            'jor' => [0, 0, 0, 0], 'jorVal' => 0,
            'empleados' => 0, 'empleadosFin' => 0,
            'empProg' => [], 'empFalta' => [], 'empAtr' => [],
            'vacTipos' => [], 'licTipos' => [], 'vacEmps' => [], 'licEmps' => [],
            'byDep' => [], 'bySec' => [], 'byMes' => [], 'byDia' => [], 'bySemana' => [],
            'feriados' => [], 'heat' => [], 'jorDep' => [],
            'histIn' => array_fill(0, 288, 0), 'histOut' => array_fill(0, 288, 0),
            'flujo' => [],
            'foco' => ['pres' => 0, 'falta' => 0, 'vac' => 0, 'lic' => 0, 'inc' => 0,
                       'atr' => 0, 'salAnt' => 0, 'minTrab' => 0, 'extra' => 0, 'n' => 0],
            'detalle' => [],
        ];

        for ($i = 0; $i < $nDep; $i++) {
            $A['byDep'][$i] = [
                'dep' => $i, 'emps' => 0, 'empsFin' => 0, 'prog' => 0, 'pres' => 0, 'falta' => 0,
                'atr' => 0, 'punt' => 0, 'min' => 0, 'minAtr' => 0, 'presT' => 0, 'inc' => 0,
                'jorOk' => 0, 'jorVal' => 0, 'extraNoLab' => 0, 'sobre' => 0, 'salAnt' => 0,
                'vac' => 0, 'lic' => 0, 'extraFer' => 0, 'extraFds' => 0, 'ferDias' => 0, 'minFalta' => 0,
            ];
            $A['jorDep'][$i] = [0, 0, 0, 0];
            $A['heat'][$i]   = [];
        }

        return $A;
    }

    private function acumularUniverso(array &$A, array $r, int $d): void
    {
        $p   = $this->paquete;
        $dep = $r['dep'];
        $e   = $r['empleado_idx'];
        $st  = $r['estado'];
        $mes = substr($p->fechas[$d], 0, 7);
        $sem = $this->claveSemana($d);

        $A['byDia'][$d]   ??= ['p' => 0, 'f' => 0, 'j' => 0, 'a' => 0, 'prog' => $r['programado']];
        $A['byMes'][$mes] ??= ['prog' => 0, 'pres' => 0, 'atr' => 0, 'punt' => 0, 'falta' => 0,
            'vac' => 0, 'vacN' => 0, 'licBM' => 0, 'licOt' => 0, 'sobre' => 0,
            'extraFds' => 0, 'extraFer' => 0, 'ferDias' => 0, 'min' => 0, 'minFalta' => 0];
        $A['bySemana'][$sem] ??= ['d' => $d, 'prog' => 0, 'pres' => 0, 'falta' => 0, 'atr' => 0,
            'punt' => 0, 'inc' => 0, 'presT' => 0, 'min' => 0, 'extra' => 0,
            'jorOk' => 0, 'jorVal' => 0, 'salAnt' => 0];
        $A['heat'][$dep][$mes] ??= ['prog' => 0, 'pres' => 0];

        $bdep = &$A['byDep'][$dep];
        $bm   = &$A['byMes'][$mes];
        $W    = &$A['bySemana'][$sem];
        $hc   = &$A['heat'][$dep][$mes];

        if ($r['programado']) {
            $A['prog']++; $bdep['prog']++; $bm['prog']++; $hc['prog']++; $W['prog']++;
            $A['empProg'][$e] = ($A['empProg'][$e] ?? 0) + 1;

            if ($st === Codigos::PRESENTE) {
                $A['presProg']++; $bdep['pres']++; $bm['pres']++; $hc['pres']++; $W['pres']++;
            }

            if ($st === Codigos::FALTA) {
                $A['falta']++; $bdep['falta']++; $bm['falta']++; $W['falta']++;

                $mascara = 0;
                foreach ($r['codigos'] as $c) {
                    $mascara |= Codigos::bit($c);
                }
                if ($mascara & Codigos::I) {
                    $A['faltaI']++;
                } else {
                    $A['faltaSR']++;
                }

                // Capacidad perdida: la falta se valora a la jornada objetivo del perfil.
                $A['minFalta'] += $r['objetivo'];
                $bdep['minFalta'] += $r['objetivo'];
                $bm['minFalta']   += $r['objetivo'];
                $A['empFalta'][$e] = ($A['empFalta'][$e] ?? 0) + 1;
            }
        }

        if ($st === Codigos::PRESENTE) {
            $A['pres']++; $bdep['presT']++; $W['presT']++;
            $A['minTrab'] += $r['trabajado'];
            $bdep['min']  += $r['trabajado'];
            $bm['min']    += $r['trabajado'];
            $W['min']     += $r['trabajado'];

            if ($r['programado']) {
                $A['minSobre'] += $r['extra'];
                $bdep['sobre'] += $r['extra'];
                $bm['sobre']   += $r['extra'];
                $W['extra']    += $r['extra'];
            } else {
                $A['minExtraNoLab'] += $r['trabajado'];
                $bdep['extraNoLab'] += $r['trabajado'];
                $W['extra']         += $r['trabajado'];

                if ($r['feriado']) {
                    $A['minExtraFer'] += $r['trabajado'];
                    $bdep['extraFer'] += $r['trabajado'];
                    $bm['extraFer']   += $r['trabajado'];
                    $A['ferDias']++; $bdep['ferDias']++; $bm['ferDias']++;

                    $fk = $p->fechas[$d];
                    $A['feriados'][$fk] ??= ['d' => $d, 'pres' => 0, 'min' => 0, 'deps' => [], 'secs' => []];
                    $A['feriados'][$fk]['pres']++;
                    $A['feriados'][$fk]['min'] += $r['trabajado'];
                    $A['feriados'][$fk]['deps'][$dep] = ($A['feriados'][$fk]['deps'][$dep] ?? 0) + 1;
                    $sc = $this->par->seccionDe($p->departamentos[$dep]);
                    $A['feriados'][$fk]['secs'][$sc] = ($A['feriados'][$fk]['secs'][$sc] ?? 0) + 1;
                } else {
                    $A['minExtraFds'] += $r['trabajado'];
                    $bdep['extraFds'] += $r['trabajado'];
                    $bm['extraFds']   += $r['trabajado'];
                    $A['fdsDias']++;
                }
            }

            if ($r['incompleta']) {
                $A['inc']++; $bdep['inc']++; $W['inc']++;
            }

            if ($r['entrada'] < 0 && $r['salida'] >= 0) {
                $A['sinEntrada']++;
            }

            if ($r['programado'] && $r['entrada'] >= 0) {
                if ($r['atraso'] > 0) {
                    $A['atrasos']++; $A['minAtr'] += $r['atraso'];
                    $bdep['atr']++; $bdep['minAtr'] += $r['atraso'];
                    $bm['atr']++; $W['atr']++;
                    $A['empAtr'][$e] ??= ['n' => 0, 'm' => 0];
                    $A['empAtr'][$e]['n']++;
                    $A['empAtr'][$e]['m'] += $r['atraso'];
                } else {
                    $A['puntual']++; $bdep['punt']++; $bm['punt']++; $W['punt']++;
                }
            }

            if ($r['jornada']) {
                $A['jor'][$r['jornada']]++;
                $A['jorVal']++;
                $A['jorDep'][$dep][$r['jornada']]++;
                $bdep['jorVal']++; $W['jorVal']++;
                if ($r['jornada'] >= 2) {
                    $bdep['jorOk']++; $W['jorOk']++;
                }
            }

            if ($r['salida_anticipada'] > 0) {
                $A['salAnt']++; $A['minSalAnt'] += $r['salida_anticipada'];
                $bdep['salAnt']++; $W['salAnt']++;
            }
        } elseif ($st === Codigos::VAC) {
            $A['vac']++; $bdep['vac']++; $A['vacEmps'][$e] = 1;
            $mascara = 0;
            foreach ($r['codigos'] as $c) {
                $mascara |= Codigos::bit($c);
            }
            if ($mascara & Codigos::VDN) {
                $A['vacTipos']['VDN'] = ($A['vacTipos']['VDN'] ?? 0) + 1;
                $bm['vacN']++;
            } else {
                $A['vacTipos']['V'] = ($A['vacTipos']['V'] ?? 0) + 1;
                $bm['vac']++;
            }
        } elseif ($st === Codigos::LIC) {
            $A['lic']++; $bdep['lic']++; $A['licEmps'][$e] = 1;
            $mascara = 0;
            foreach ($r['codigos'] as $c) {
                $mascara |= Codigos::bit($c);
            }
            if ($mascara & Codigos::BM) {
                $A['licTipos']['BM'] = ($A['licTipos']['BM'] ?? 0) + 1;
                $bm['licBM']++;
            } else {
                $bm['licOt']++;
                foreach (['LR', 'LDM', 'LPM'] as $c) {
                    if ($mascara & Codigos::bit($c)) {
                        $A['licTipos'][$c] = ($A['licTipos'][$c] ?? 0) + 1;
                        break;
                    }
                }
            }
        } else {
            $A['noLab']++;
        }
    }

    private function acumularFoco(array &$A, array $r, int $d): void
    {
        $st = $r['estado'];
        $bd = &$A['byDia'][$d];

        if ($st === Codigos::PRESENTE) {
            // Flujo de marcaciones por minuto: mide la eficiencia del lector biometrico.
            foreach ($r['marcas'] as $m) {
                $A['flujo'][$m] = ($A['flujo'][$m] ?? 0) + 1;
            }

            $bd['p']++;
            $A['foco']['pres']++;
            $A['foco']['minTrab'] += $r['trabajado'];
            $A['foco']['extra']   += $r['programado'] ? $r['extra'] : $r['trabajado'];

            if ($r['incompleta']) {
                $A['foco']['inc']++;
            }
            if ($r['atraso'] > 0) {
                $bd['a']++;
                $A['foco']['atr']++;
            }
            if ($r['salida_anticipada'] > 0) {
                $A['foco']['salAnt']++;
            }
            if ($r['entrada'] >= 0) {
                $A['histIn'][min(287, intdiv($r['entrada'], 5))]++;
            }
            if ($r['salida'] >= 0) {
                $A['histOut'][min(287, intdiv($r['salida'], 5))]++;
            }
        } elseif ($st === Codigos::FALTA) {
            $bd['f']++;
            $A['foco']['falta']++;
        } elseif ($st === Codigos::VAC) {
            $bd['j']++;
            $A['foco']['vac']++;
        } elseif ($st === Codigos::LIC) {
            $bd['j']++;
            $A['foco']['lic']++;
        }

        $A['foco']['n']++;
    }

    /** Cierra el acumulado: dias laborables efectivos y agregado por seccion de planta. */
    private function cerrar(array &$A, Filtro $f): void
    {
        for ($i = $f->desde; $i <= $f->hasta; $i++) {
            if (! empty($A['byDia'][$i]['prog'])) {
                $A['diasProg']++;
            }
        }

        foreach ($A['byDep'] as $i => $bd) {
            $sec = $this->par->seccionDe($this->paquete->departamentos[$i]);

            if (! isset($A['bySec'][$sec])) {
                $A['bySec'][$sec] = array_merge(
                    array_fill_keys(array_keys($bd), 0),
                    ['sec' => $sec, 'deps' => []]
                );
                unset($A['bySec'][$sec]['dep']);
            }

            $A['bySec'][$sec]['deps'][] = $i;

            foreach ($bd as $k => $v) {
                if ($k !== 'dep' && is_numeric($v)) {
                    $A['bySec'][$sec][$k] += $v;
                }
            }
        }

        ksort($A['byMes']);
        ksort($A['bySemana']);
        ksort($A['feriados']);
    }

    /** Lunes de la semana a la que pertenece el dia, como clave ordenable. */
    public function claveSemana(int $dia): string
    {
        $ts   = strtotime($this->paquete->fechas[$dia] . ' 00:00:00');
        $dow  = (int) date('w', $ts);
        $shift = ($dow + 6) % 7;

        return date('Y-m-d', $ts - $shift * 86400);
    }
}
