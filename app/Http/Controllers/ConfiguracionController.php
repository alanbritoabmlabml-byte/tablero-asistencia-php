<?php

namespace App\Http\Controllers;

use App\Models\Carga;
use App\Services\Asistencia\Importador;
use App\Services\Asistencia\Parametros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfiles de jornada, secciones de planta, metas del semaforo, feriados y dias de
 * lluvia. Cada cambio recalcula la carga activa: no hay numeros viejos conviviendo
 * con parametros nuevos.
 */
class ConfiguracionController extends Controller
{
    public function __construct(private Importador $importador) {}

    public function edit(): View
    {
        $carga = Carga::activa();

        abort_unless($carga, 404, 'No hay ninguna carga activa que configurar.');

        return view('config.parametros', [
            'carga' => $carga,
            'par' => $carga->parametrosObj(),
            'departamentos' => $carga->departamentos,
            'secciones' => Parametros::SECCIONES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $carga = Carga::activa();

        abort_unless($carga, 404);

        $datos = $request->validate([
            'perfiles' => ['array'],
            'perfiles.*.entrada' => ['required', 'string'],
            'perfiles.*.salida' => ['required', 'string'],
            'perfiles.*.tol' => ['required', 'integer', 'min:0', 'max:120'],
            'perfiles.*.tolS' => ['required', 'integer', 'min:0', 'max:120'],
            'perfiles.*.obj' => ['required', 'numeric', 'min:1', 'max:16'],
            'perfiles.*.tolJ' => ['required', 'integer', 'min:0', 'max:180'],
            'perfiles.*.refri' => ['required', 'integer', 'min:0', 'max:180'],
            'grupo' => ['array'],
            'seccion' => ['array'],
            'metas' => ['array'],
            'feriados' => ['nullable', 'string'],
            'capacidadLector' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $par = $carga->parametrosObj();

        foreach ($datos['perfiles'] ?? [] as $clave => $p) {
            if (! isset($par->perfiles[$clave])) {
                continue;
            }

            $par->perfiles[$clave]['entrada'] = $this->minutos($p['entrada']);
            $par->perfiles[$clave]['salida']  = $this->minutos($p['salida']);
            $par->perfiles[$clave]['tol']     = (int) $p['tol'];
            $par->perfiles[$clave]['tolS']    = (int) $p['tolS'];
            $par->perfiles[$clave]['obj']     = (int) round(((float) $p['obj']) * 60);
            $par->perfiles[$clave]['tolJ']    = (int) $p['tolJ'];
            $par->perfiles[$clave]['refri']   = (int) $p['refri'];
            $par->perfiles[$clave]['sab']['on'] = ! empty($p['sabado']);
        }

        foreach ($datos['grupo'] ?? [] as $dep => $g) {
            if (isset($par->perfiles[$g])) {
                $par->grupo[$dep] = $g;
            }
        }

        foreach ($datos['seccion'] ?? [] as $dep => $s) {
            if (in_array($s, Parametros::SECCIONES, true)) {
                $par->seccion[$dep] = $s;
            }
        }

        foreach ($datos['metas'] ?? [] as $clave => $v) {
            if (isset($par->metas[$clave]) && isset($v['meta'], $v['critico'])) {
                $par->metas[$clave] = [(float) $v['meta'], (float) $v['critico']];
            }
        }

        // Los feriados llegan como una fecha por linea.
        $par->feriados = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,;]+/', (string) ($datos['feriados'] ?? ''))
        ), fn ($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)));

        $par->capacidadLector = (int) $datos['capacidadLector'];

        $n = $this->importador->recalcular($carga, $par);

        return redirect()->route('configuracion.edit')
            ->with('ok', 'Parametros guardados. Se recalcularon ' . number_format($n, 0, ',', '.') . ' registros.');
    }

    /** Acepta "07:30" y tambien "7:30". */
    private function minutos(string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', trim($hhmm)), 2, '0');

        return max(0, min(1439, ((int) $h) * 60 + (int) $m));
    }
}
