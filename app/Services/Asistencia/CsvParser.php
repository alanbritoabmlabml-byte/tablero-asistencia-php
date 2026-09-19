<?php

namespace App\Services\Asistencia;

use RuntimeException;

/**
 * Lee el export "ancho" del Control de Asistencia.
 *
 * Formato: separador ";", encabezado CI; Nombre; Departamento y despues una
 * columna por fecha. Cada celda trae las marcaciones del dia en HH:MM separadas
 * por espacios, o uno o varios codigos (SR, I, V, VDN, LDM, LPM, LR, BM).
 */
final class CsvParser
{
    /** Marcaciones separadas por 3 minutos o menos se consideran la misma (rebote del lector). */
    public const DEDUPE_MIN = 3;

    /** Un dia habil con mas de este porcentaje de SR se considera feriado. */
    public const UMBRAL_FERIADO = 0.55;

    public function parse(string $texto, string $origen = 'CSV'): Paquete
    {
        // BOM de Excel
        if (str_starts_with($texto, "\xEF\xBB\xBF")) {
            $texto = substr($texto, 3);
        }

        $lineas = array_values(array_filter(
            explode("\n", str_replace("\r", '', $texto)),
            static fn ($l) => trim($l) !== ''
        ));

        if (! $lineas) {
            throw new RuntimeException('El archivo esta vacio.');
        }

        $encabezado = explode(';', $lineas[0]);

        if (count($encabezado) < 4) {
            throw new RuntimeException('No se reconoce el formato: se esperan al menos 4 columnas separadas por «;».');
        }

        $fechas = [];
        $nd     = count($encabezado) - 3;

        for ($j = 0; $j < $nd; $j++) {
            $fechas[] = $this->normalizarFecha($encabezado[3 + $j]);
        }

        $deps = $listaDeps = $empleados = [];
        $qa   = ['marcaciones_duplicadas' => 0, 'registros_descartados' => 0];

        for ($r = 1, $nl = count($lineas); $r < $nl; $r++) {
            $f = array_map(
                static fn ($x) => preg_replace('/^\s*"|"\s*$/', '', $x),
                explode(';', $lineas[$r])
            );

            $ci     = trim($f[0] ?? '');
            $nombre = trim(preg_replace('/\s+/', ' ', $f[1] ?? ''));
            $dep    = strtoupper(trim($f[2] ?? ''));

            if ($ci === '' || $dep === '' || $dep === 'DEPARTAMENTO') {
                $qa['registros_descartados']++;
                continue;
            }

            if (! isset($deps[$dep])) {
                $deps[$dep]  = count($listaDeps);
                $listaDeps[] = $dep;
            }

            $celdas  = [];
            $primera = -1;
            $ultima  = -1;

            for ($j = 0; $j < $nd; $j++) {
                $raw = trim($f[3 + $j] ?? '');

                if ($raw === '') {
                    $celdas[] = '';
                    continue;
                }

                if ($primera < 0) {
                    $primera = $j;
                }
                $ultima = $j;

                $celdas[] = $this->celda($raw, $qa);
            }

            // Un empleado sin ninguna celda en todo el periodo no existe para el tablero.
            if ($primera < 0) {
                continue;
            }

            $empleados[] = [
                'ci' => $ci, 'nombre' => $nombre, 'dep' => $deps[$dep],
                'desde' => $primera, 'hasta' => $ultima, 'celdas' => $celdas,
            ];
        }

        // Los departamentos se ordenan alfabeticamente y los indices se remapean.
        $orden = $listaDeps;
        sort($orden);
        $remap = [];
        foreach ($orden as $i => $d) {
            $remap[$deps[$d]] = $i;
        }
        foreach ($empleados as &$e) {
            $e['dep'] = $remap[$e['dep']];
        }
        unset($e);

        $paquete = new Paquete(
            fechas: $fechas,
            departamentos: $orden,
            empleados: $empleados,
            qa: $qa,
            origen: $origen,
        );

        $paquete->feriadosDetectados = $this->detectarFeriados($paquete);

        return $paquete;
    }

    /**
     * Normaliza una marcacion de dia: ordena las horas, descarta rebotes del lector
     * y devuelve "HHMM,HHMM;COD+COD".
     */
    private function celda(string $raw, array &$qa): string
    {
        $marcas = $codigos = [];

        foreach (preg_split('/\s+/', $raw) as $tok) {
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $tok, $m)) {
                $marcas[] = ((int) $m[1]) * 60 + (int) $m[2];
            } elseif (Codigos::bit($tok)) {
                $codigos[] = strtoupper($tok);
            }
        }

        sort($marcas);

        $limpias = [];
        foreach ($marcas as $v) {
            if ($limpias && $v - $limpias[count($limpias) - 1] <= self::DEDUPE_MIN) {
                $qa['marcaciones_duplicadas']++;
                continue;
            }
            $limpias[] = $v;
        }

        $s = implode(',', array_map(
            static fn ($v) => sprintf('%02d%02d', intdiv($v, 60), $v % 60),
            $limpias
        ));

        if ($codigos) {
            $s .= ';' . implode('+', $codigos);
        }

        return $s;
    }

    private function normalizarFecha(string $celda): string
    {
        $p = preg_split('/[\/\-]/', trim(str_replace('"', '', $celda)));

        if (count($p) !== 3) {
            throw new RuntimeException('Encabezado de fecha no reconocido: ' . $celda);
        }

        // aaaa-mm-dd
        if (strlen($p[0]) === 4) {
            return sprintf('%s-%02d-%02d', $p[0], (int) $p[1], (int) $p[2]);
        }

        // dd/mm/aaaa
        return sprintf('%s-%02d-%02d', $p[2], (int) $p[1], (int) $p[0]);
    }

    /**
     * Feriados inferidos de los propios datos: un dia habil en el que mas del 55 %
     * del personal vigente aparece como "sin registro" es un feriado, no una falta
     * masiva. Quedan propuestos y son editables en Configuracion.
     */
    public function detectarFeriados(Paquete $p): array
    {
        $nd     = count($p->fechas);
        $activos = array_fill(0, $nd, 0);
        $sr      = array_fill(0, $nd, 0);

        foreach ($p->empleados as $e) {
            for ($j = $e['desde']; $j <= $e['hasta']; $j++) {
                $activos[$j]++;
                if (($e['celdas'][$j] ?? '') === ';SR') {
                    $sr[$j]++;
                }
            }
        }

        $out = [];
        foreach ($p->fechas as $j => $fecha) {
            $dow = (int) date('w', strtotime($fecha . ' 00:00:00'));
            if ($dow >= 1 && $dow <= 5 && $activos[$j] > 0 && $sr[$j] / $activos[$j] > self::UMBRAL_FERIADO) {
                $out[] = $fecha;
            }
        }

        return $out;
    }
}
