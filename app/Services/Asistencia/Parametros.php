<?php

namespace App\Services\Asistencia;

/**
 * Parametros de calculo del tablero: perfiles de jornada, secciones de planta,
 * metas del semaforo, feriados y tolerancia extendida por lluvia.
 *
 * Los horarios reales de Plasticos Carmen difieren por area, por eso hay varios
 * perfiles en vez de una hora de ingreso unica:
 *   - fabrica  : turno de planta, 07:30 a 19:30, 11 h netas (12 h con 1 h de almuerzo)
 *   - tanques  : Tanques marca ~06:30 a 18:40, turno propio
 *   - admin    : jornada administrativa 08:00 a 16:30, y sabados de 08:00 a 12:30
 *   - almacen  : Almacen Bolsa, dos turnos continuos de lunes a sabado
 */
final class Parametros
{
    public const SECCION_ADMIN = 'Administracion';

    public const SECCIONES = [
        'Bolsas', 'Termoformado', 'Expandido', 'Inyeccion',
        'Reciclado', 'Rafia', 'Tanques', self::SECCION_ADMIN,
    ];

    public const PERFILES_DEF = [
        'fabrica' => [
            'nombre' => 'Fabrica', 'entrada' => 450, 'salida' => 1170, 'tol' => 10, 'tolS' => 15,
            'obj' => 660, 'tolJ' => 30, 'refri' => 60, 'umbralRefri' => 360, 'vent' => 240, 'lab' => '12345',
            'sab' => ['on' => false, 'entrada' => 450, 'salida' => 750, 'obj' => 300],
        ],
        'tanques' => [
            'nombre' => 'Fabrica · Tanques', 'entrada' => 390, 'salida' => 1120, 'tol' => 10, 'tolS' => 15,
            'obj' => 660, 'tolJ' => 30, 'refri' => 60, 'umbralRefri' => 360, 'vent' => 240, 'lab' => '12345',
            'sab' => ['on' => false, 'entrada' => 390, 'salida' => 750, 'obj' => 360],
        ],
        'admin' => [
            'nombre' => 'Administracion', 'entrada' => 480, 'salida' => 990, 'tol' => 10, 'tolS' => 15,
            'obj' => 480, 'tolJ' => 30, 'refri' => 30, 'umbralRefri' => 300, 'vent' => 240, 'lab' => '12345',
            'sab' => ['on' => true, 'entrada' => 480, 'salida' => 750, 'obj' => 270],
        ],
        'almacen' => [
            'nombre' => 'Almacen Bolsa · 2 turnos', 'entrada' => 450, 'salida' => 900, 'tol' => 10, 'tolS' => 15,
            'obj' => 450, 'tolJ' => 30, 'refri' => 0, 'umbralRefri' => 600, 'vent' => 240, 'lab' => '12345',
            'turnos' => [
                ['nombre' => 'Turno 1', 'entrada' => 450, 'salida' => 900],
                ['nombre' => 'Turno 2', 'entrada' => 930, 'salida' => 1380],
            ],
            'sab' => [
                'on' => true, 'entrada' => 480, 'salida' => 750, 'obj' => 270,
                'turnos' => [
                    ['nombre' => 'Turno 1', 'entrada' => 480, 'salida' => 750],
                    ['nombre' => 'Turno 2', 'entrada' => 750, 'salida' => 1020],
                ],
            ],
        ],
    ];

    /** Departamentos que por sus marcaciones reales usan cada perfil. */
    public const ADMIN_DEF   = ['ADM', 'CONTABILIDAD', 'COMERCIAL', 'MATERIA PRIMA', 'RRHH', 'SISTEMAS', 'GERENCIA'];
    public const TANQUES_DEF = ['TANQUE', 'TANQUES'];
    public const ALMACEN_DEF = ['ALMACEN BOLSA', 'ALMACEN BOLSAS', 'ALMACEN'];

    public const SECCION_DEF = [
        'BOLSA' => 'Bolsas', 'ALMACEN BOLSA' => 'Bolsas', 'TERMOFORMADO' => 'Termoformado',
        'EXPANDIDO' => 'Expandido', 'INYECCION' => 'Inyeccion', 'RECICLADO' => 'Reciclado',
        'RAFIA' => 'Rafia', 'TANQUE' => 'Tanques', 'TANQUES' => 'Tanques',
        'ADM' => self::SECCION_ADMIN, 'CONTABILIDAD' => self::SECCION_ADMIN,
        'COMERCIAL' => self::SECCION_ADMIN, 'MATERIA PRIMA' => self::SECCION_ADMIN,
        'RRHH' => self::SECCION_ADMIN, 'SISTEMAS' => self::SECCION_ADMIN, 'GERENCIA' => self::SECCION_ADMIN,
    ];

    /** Metas [meta, critico]; entre ambos valores la tarjeta queda en "atencion". */
    public const METAS_DEF = [
        'asis' => [95, 88], 'punt' => [90, 80], 'aus' => [3, 6],
        'jor' => [90, 75], 'comp' => [95, 88], 'sal' => [5, 12],
    ];

    /** Pesos del indice de cumplimiento (suman 1). */
    public const PESOS_INDICE = ['asis' => 0.40, 'punt' => 0.30, 'jor' => 0.20, 'comp' => 0.10];

    public array $perfiles;
    /** @var array<string,string> departamento => clave de perfil */
    public array $grupo;
    /** @var array<string,string> departamento => seccion de planta */
    public array $seccion;
    public array $metas;
    /** @var string[] fechas aaaa-mm-dd */
    public array $feriados;
    /** @var array<string,int> fecha => tolerancia de ingreso en minutos */
    public array $lluvia;
    public int $capacidadLector;

    public function __construct(
        ?array $perfiles = null,
        array $grupo = [],
        array $seccion = [],
        ?array $metas = null,
        array $feriados = [],
        array $lluvia = [],
        int $capacidadLector = 12,
    ) {
        $this->perfiles        = $perfiles ?? self::PERFILES_DEF;
        $this->grupo           = $grupo;
        $this->seccion         = $seccion;
        $this->metas           = $metas ?? self::METAS_DEF;
        $this->feriados        = $feriados;
        $this->lluvia          = $lluvia;
        $this->capacidadLector = $capacidadLector;
    }

    /** Asigna perfil y seccion por defecto a cada departamento encontrado en el CSV. */
    public static function paraDepartamentos(array $departamentos): self
    {
        $grupo = $seccion = [];

        foreach ($departamentos as $dep) {
            $d = strtoupper(trim($dep));

            if (in_array($d, self::ALMACEN_DEF, true)) {
                $grupo[$dep] = 'almacen';
            } elseif (in_array($d, self::TANQUES_DEF, true)) {
                $grupo[$dep] = 'tanques';
            } elseif (in_array($d, self::ADMIN_DEF, true)) {
                $grupo[$dep] = 'admin';
            } else {
                $grupo[$dep] = 'fabrica';
            }

            $seccion[$dep] = self::SECCION_DEF[$d] ?? self::SECCION_ADMIN;
        }

        return new self(grupo: $grupo, seccion: $seccion);
    }

    public function perfilDe(string $departamento): array
    {
        $g = $this->grupo[$departamento] ?? 'fabrica';

        return $this->perfiles[$g] ?? $this->perfiles['fabrica'];
    }

    public function grupoDe(string $departamento): string
    {
        $g = $this->grupo[$departamento] ?? 'fabrica';

        return isset($this->perfiles[$g]) ? $g : 'fabrica';
    }

    public function seccionDe(string $departamento): string
    {
        return $this->seccion[$departamento] ?? self::SECCION_ADMIN;
    }

    public function meta(string $clave): array
    {
        return $this->metas[$clave] ?? self::METAS_DEF[$clave];
    }

    /**
     * Perfil vigente para un dia concreto: resuelve el horario especial de sabado
     * y, en los perfiles por turnos, el turno cuyo ingreso esta mas cerca de la
     * primera marcacion del dia ($primeraMarca en minutos, -1 si no hay).
     */
    public function perfilDia(array $pf, int $diaSemana, int $primeraMarca = -1): array
    {
        $sabado = $diaSemana === 6 && ! empty($pf['sab']['on']);
        $turnos = $sabado ? ($pf['sab']['turnos'] ?? null) : ($pf['turnos'] ?? null);

        if ($turnos) {
            $t    = self::turnoDe($turnos, $primeraMarca);
            $span = $t['salida'] - $t['entrada'];

            return [
                'entrada' => $t['entrada'], 'salida' => $t['salida'],
                'obj'     => $sabado ? ($pf['sab']['obj'] ?? $span) : min($pf['obj'], $span),
                'tol'     => $pf['tol'], 'tolS' => $pf['tolS'], 'tolJ' => $pf['tolJ'],
                'refri'   => $sabado ? 0 : $pf['refri'],
                'umbralRefri' => $sabado ? PHP_INT_MAX : $pf['umbralRefri'],
                'vent'    => min($pf['vent'], 180),
                'nombre'  => $pf['nombre'], 'turno' => $t['nombre'],
            ];
        }

        if ($sabado) {
            return [
                'entrada' => $pf['sab']['entrada'], 'salida' => $pf['sab']['salida'],
                'obj'     => $pf['sab']['obj'], 'tol' => $pf['tol'], 'tolS' => $pf['tolS'],
                'tolJ'    => $pf['tolJ'], 'refri' => 0, 'umbralRefri' => PHP_INT_MAX,
                'vent'    => $pf['vent'], 'nombre' => $pf['nombre'],
            ];
        }

        return $pf;
    }

    /** Turno cuyo ingreso esta mas cerca de la primera marcacion del dia. */
    public static function turnoDe(array $turnos, int $primeraMarca): array
    {
        if ($primeraMarca < 0) {
            return $turnos[0];
        }

        $mejor = $turnos[0];
        $dist  = abs($primeraMarca - $turnos[0]['entrada']);

        foreach ($turnos as $t) {
            $d = abs($primeraMarca - $t['entrada']);
            if ($d < $dist) {
                $dist  = $d;
                $mejor = $t;
            }
        }

        return $mejor;
    }

    public function toArray(): array
    {
        return [
            'perfiles' => $this->perfiles, 'grupo' => $this->grupo, 'seccion' => $this->seccion,
            'metas' => $this->metas, 'feriados' => $this->feriados, 'lluvia' => $this->lluvia,
            'capacidadLector' => $this->capacidadLector,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            perfiles: $a['perfiles'] ?? null,
            grupo: $a['grupo'] ?? [],
            seccion: $a['seccion'] ?? [],
            metas: $a['metas'] ?? null,
            feriados: $a['feriados'] ?? [],
            lluvia: $a['lluvia'] ?? [],
            capacidadLector: $a['capacidadLector'] ?? 12,
        );
    }
}
