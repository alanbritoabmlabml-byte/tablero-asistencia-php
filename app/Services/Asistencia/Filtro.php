<?php

namespace App\Services\Asistencia;

/** Alcance activo del tablero: rango de fechas, areas, busqueda de persona y novedades. */
final class Filtro
{
    public function __construct(
        public int $desde = 0,
        public int $hasta = 0,
        /** @var int[]|null indices de departamento; null = todos */
        public ?array $departamentos = null,
        public string $empleado = '',
        /** @var array<string,bool> presente, falta, atraso, vacacion, licencia, incompleta, salida, extra */
        public array $novedades = [],
    ) {}

    public function hayNovedades(): bool
    {
        foreach ($this->novedades as $v) {
            if ($v) {
                return true;
            }
        }

        return false;
    }

    public function aceptaDepartamento(int $dep): bool
    {
        return $this->departamentos === null || in_array($dep, $this->departamentos, true);
    }

    /** Compara sin acentos ni mayusculas, contra el nombre y contra el CI. */
    public function aceptaEmpleado(array $e): bool
    {
        if (! $this->aceptaDepartamento($e['dep'])) {
            return false;
        }

        $q = trim($this->empleado);

        if ($q === '') {
            return true;
        }

        return str_contains(self::normalizar($e['nombre']), self::normalizar($q))
            || str_contains($e['ci'], $q);
    }

    public function aceptaNovedad(array $r): bool
    {
        $f = $this->novedades;

        return (! empty($f['presente'])   && $r['estado'] === Codigos::PRESENTE)
            || (! empty($f['falta'])      && $r['estado'] === Codigos::FALTA)
            || (! empty($f['atraso'])     && $r['atraso'] > 0)
            || (! empty($f['vacacion'])   && $r['estado'] === Codigos::VAC)
            || (! empty($f['licencia'])   && $r['estado'] === Codigos::LIC)
            || (! empty($f['incompleta']) && $r['incompleta'] === 1)
            || (! empty($f['salida'])     && $r['salida_anticipada'] > 0)
            || (! empty($f['extra'])      && $r['extra'] > 0);
    }

    public static function normalizar(string $s): string
    {
        $s = strtolower($s);
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);

        return $s;
    }
}
