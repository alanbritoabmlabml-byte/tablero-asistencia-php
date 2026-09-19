<?php

namespace App\Http\Controllers;

use App\Models\Carga;
use App\Services\Asistencia\Importador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CargaController extends Controller
{
    public function __construct(private Importador $importador) {}

    public function index(): View
    {
        return view('config.cargas', [
            'cargas' => Carga::with('usuario')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:51200'],
        ], [
            'archivo.required' => 'Elegi el archivo CSV que exporta el Control de Asistencia.',
            'archivo.max' => 'El archivo supera los 50 MB.',
        ], ['archivo' => 'archivo']);

        $file = $request->file('archivo');

        try {
            $carga = $this->importador->importar(
                contenido: file_get_contents($file->getRealPath()),
                nombreArchivo: $file->getClientOriginalName(),
                userId: $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        return redirect()->route('cargas.index')->with('ok', sprintf(
            'Importado: %s personas, %s dias y %s registros del %s al %s.',
            number_format($carga->empleados, 0, ',', '.'),
            number_format($carga->dias, 0, ',', '.'),
            number_format($carga->registros, 0, ',', '.'),
            $carga->desde->format('d/m/Y'),
            $carga->hasta->format('d/m/Y'),
        ));
    }

    public function activar(Carga $carga): RedirectResponse
    {
        $carga->activar();

        return redirect()->route('cargas.index')
            ->with('ok', 'El tablero ahora muestra la carga «' . $carga->archivo . '».');
    }

    public function destroy(Carga $carga): RedirectResponse
    {
        if ($carga->activa) {
            return back()->withErrors(['carga' => 'No se puede borrar la carga activa: activa otra primero.']);
        }

        $nombre = $carga->archivo;
        $carga->delete();

        return redirect()->route('cargas.index')->with('ok', 'Se elimino la carga «' . $nombre . '».');
    }
}
