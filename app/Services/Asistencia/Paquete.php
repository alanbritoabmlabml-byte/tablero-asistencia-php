<?php

namespace App\Services\Asistencia;

/**
 * Datos crudos de una carga, ya normalizados: el calendario del periodo, la lista
 * de departamentos, y por cada empleado el rango de dias en que estuvo vigente y
 * sus celdas diarias codificadas ("HHMM,HHMM;COD").
 */
final class Paquete
{
    /** @var int[] dia de la semana (0=domingo) por indice de fecha */
    public array $dow = [];

    /** @var string[] feriados propuestos por el detector */
    public array $feriadosDetectados = [];

    public function __construct(
        /** @var string[] fechas aaaa-mm-dd, en orden */
        public array $fechas,
        /** @var string[] departamentos en mayusculas, ordenados */
        public array $departamentos,
        /** @var array<int,array{ci:string,nombre:string,dep:int,desde:int,hasta:int,celdas:string[]}> */
        public array $empleados,
        public array $qa = [],
        public string $origen = 'CSV',
    ) {
        foreach ($this->fechas as $f) {
            $this->dow[] = (int) date('w', strtotime($f . ' 00:00:00'));
        }
    }

    public function dias(): int
    {
        return count($this->fechas);
    }

    public function indiceDeFecha(string $fecha): int
    {
        $i = array_search($fecha, $this->fechas, true);

        return $i === false ? -1 : (int) $i;
    }

    public function departamentoDe(int $idx): string
    {
        return $this->departamentos[$idx] ?? '';
    }

    public function meta(): array
    {
        return [
            'origen'     => $this->origen,
            'desde'      => $this->fechas[0] ?? null,
            'hasta'      => $this->fechas[count($this->fechas) - 1] ?? null,
            'dias'       => $this->dias(),
            'empleados'  => count($this->empleados),
            'generado'   => date('Y-m-d\TH:i:s'),
            'dedupeMin'  => CsvParser::DEDUPE_MIN,
            'qa'         => $this->qa,
        ];
    }
}
