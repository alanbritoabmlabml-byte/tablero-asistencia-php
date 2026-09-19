<?php

namespace App\Http\Controllers;

use App\Models\Carga;
use App\Services\Asistencia\Filtro;
use App\Services\Asistencia\Tablero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableroController extends Controller
{
    public function __construct(private Tablero $tablero) {}

    public function index(Request $request, string $vista = 'gerencia'): View
    {
        $carga = Carga::activa();

        if (! $carga) {
            return view('tablero.vacio');
        }

        $datos = $this->tablero->construir($carga, $vista, $this->filtro($request, $carga));

        return view('tablero.index', [
            'datos'  => $datos,
            'carga'  => $carga,
            'vistas' => Tablero::VISTAS,
            'cargas' => Carga::orderByDesc('id')->limit(12)->get(),
        ]);
    }

    /** Mismo calculo, en JSON: lo usan los filtros para no recargar la pagina entera. */
    public function datos(Request $request, string $vista = 'gerencia'): JsonResponse
    {
        $carga = Carga::activa();

        if (! $carga) {
            return response()->json(['error' => 'No hay ninguna carga activa.'], 404);
        }

        return response()->json($this->tablero->construir($carga, $vista, $this->filtro($request, $carga)));
    }

    /** Traduce los parametros de la peticion al alcance interno (indices de dia). */
    public function filtro(Request $request, Carga $carga): Filtro
    {
        $fechas = $carga->fechas;
        $ultimo = count($fechas) - 1;

        $desde = $this->indiceFecha($fechas, $request->query('desde'), 0);
        $hasta = $this->indiceFecha($fechas, $request->query('hasta'), $ultimo);

        // Rangos rapidos: ultimos 30 o 90 dias, mes en curso o todo el periodo.
        switch ($request->query('rango')) {
            case '30': $desde = max(0, $ultimo - 29); $hasta = $ultimo; break;
            case '90': $desde = max(0, $ultimo - 89); $hasta = $ultimo; break;
            case 'mes':
                $mes = substr($fechas[$ultimo], 0, 7);
                foreach ($fechas as $i => $f) {
                    if (str_starts_with($f, $mes)) { $desde = $i; break; }
                }
                $hasta = $ultimo;
                break;
            case 'all': $desde = 0; $hasta = $ultimo; break;
        }

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $deps = $request->query('areas');
        if (is_string($deps)) {
            $deps = array_values(array_filter(array_map('intval', explode(',', $deps)), fn ($v) => $v >= 0));
        }

        $novedades = [];
        foreach (['presente', 'falta', 'atraso', 'vacacion', 'licencia', 'incompleta', 'salida', 'extra'] as $n) {
            if ($request->boolean('nov_' . $n)) {
                $novedades[$n] = true;
            }
        }

        return new Filtro(
            desde: $desde,
            hasta: $hasta,
            departamentos: $deps ?: null,
            empleado: (string) $request->query('persona', ''),
            novedades: $novedades,
        );
    }

    private function indiceFecha(array $fechas, ?string $fecha, int $porDefecto): int
    {
        if (! $fecha) {
            return $porDefecto;
        }

        $i = array_search($fecha, $fechas, true);

        if ($i !== false) {
            return (int) $i;
        }

        // Una fecha fuera del periodo se acerca al extremo mas cercano en vez de fallar.
        return $fecha < $fechas[0] ? 0 : count($fechas) - 1;
    }
}
