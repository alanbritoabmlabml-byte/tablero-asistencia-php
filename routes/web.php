<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tablero/gerencia')->name('inicio');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'mostrar'])->name('login');
    Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:10,1');
});

Route::post('/salir', [AuthController::class, 'salir'])->name('logout');

Route::middleware('auth')->group(function () {
    // Vistas por rol: gerencia, rrhh, planta y explorar.
    Route::get('/tablero/{vista?}', [TableroController::class, 'index'])
        ->whereIn('vista', ['gerencia', 'rrhh', 'planta', 'explorar'])
        ->name('tablero');

    Route::get('/api/tablero/{vista?}', [TableroController::class, 'datos'])
        ->whereIn('vista', ['gerencia', 'rrhh', 'planta', 'explorar'])
        ->name('tablero.datos');

    Route::prefix('descargar')->name('descargar.')->group(function () {
        Route::get('/areas', [ExportController::class, 'areas'])->name('areas');
        Route::get('/meses', [ExportController::class, 'meses'])->name('meses');
        Route::get('/feriados', [ExportController::class, 'feriados'])->name('feriados');
        Route::get('/detalle', [ExportController::class, 'detalle'])->name('detalle');
    });

    // Cargar datos y tocar parametros cambia lo que ve toda la empresa.
    Route::middleware('admin')->group(function () {
        Route::get('/cargas', [CargaController::class, 'index'])->name('cargas.index');
        Route::post('/cargas', [CargaController::class, 'store'])->name('cargas.store');
        Route::post('/cargas/{carga}/activar', [CargaController::class, 'activar'])->name('cargas.activar');
        Route::delete('/cargas/{carga}', [CargaController::class, 'destroy'])->name('cargas.destroy');

        Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    });
});
