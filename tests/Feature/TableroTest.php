<?php

use App\Models\Carga;
use App\Models\User;
use App\Services\Asistencia\Importador;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioAdmin(): User
{
    return User::create([
        'name' => 'Admin', 'email' => 'admin@test.bo', 'password' => 'secreto123',
        'rol' => 'administrador', 'activo' => true,
    ]);
}

function cargaDePrueba(): Carga
{
    $csv = implode("\n", [
        '"CI";"Nombre";"Departamento";"2026-01-05";"2026-01-06";"2026-01-07"',
        '"1";"Uno";"BOLSA";"07:30 19:30";"07:55 19:30";"I"',
        '"2";"Dos";"ADM";"08:00 16:30";"V";"08:00 16:30"',
    ]) . "\n";

    return app(Importador::class)->importar($csv, 'prueba.csv');
}

it('pide iniciar sesion antes de mostrar el tablero', function () {
    $this->get('/tablero/gerencia')->assertRedirect('/entrar');
});

it('muestra el estado vacio cuando no hay ninguna carga', function () {
    $this->actingAs(usuarioAdmin())
        ->get('/tablero/gerencia')
        ->assertOk()
        ->assertSee('Todavía no hay datos cargados', false);
});

it('importa el CSV y deja la carga activa', function () {
    $carga = cargaDePrueba();

    expect($carga->activa)->toBeTrue()
        ->and($carga->empleados)->toBe(2)
        ->and($carga->dias)->toBe(3)
        ->and($carga->registros)->toBe(6);
});

it('dibuja las cuatro vistas por rol', function () {
    cargaDePrueba();
    $u = usuarioAdmin();

    foreach (['gerencia', 'rrhh', 'planta', 'explorar'] as $vista) {
        $this->actingAs($u)->get('/tablero/' . $vista)->assertOk();
    }
});

it('entrega los mismos numeros por API', function () {
    cargaDePrueba();

    $r = $this->actingAs(usuarioAdmin())->getJson('/api/tablero/gerencia');

    $r->assertOk()
        ->assertJsonStructure(['vista', 'kpis', 'hallazgos', 'graficos', 'metricas']);
});

it('niega la configuracion a quien no es administrador', function () {
    cargaDePrueba();

    $usuario = User::create([
        'name' => 'Consulta', 'email' => 'consulta@test.bo', 'password' => 'secreto123',
        'rol' => 'usuario', 'activo' => true,
    ]);

    $this->actingAs($usuario)->get('/configuracion')->assertForbidden();
    $this->actingAs($usuario)->get('/tablero/gerencia')->assertOk();
});

it('descarga el resumen por areas como CSV', function () {
    cargaDePrueba();

    $this->actingAs(usuarioAdmin())
        ->get('/descargar/areas')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('recalcula al cambiar los parametros', function () {
    $carga = cargaDePrueba();
    $antes = $carga->parametrosObj()->perfiles['fabrica']['entrada'];

    $this->actingAs(usuarioAdmin())->put('/configuracion', [
        'perfiles' => ['fabrica' => [
            'entrada' => '08:00', 'salida' => '19:30', 'tol' => 10, 'tolS' => 15,
            'obj' => 11, 'tolJ' => 30, 'refri' => 60,
        ]],
        'capacidadLector' => 12,
    ])->assertRedirect();

    expect($carga->fresh()->parametrosObj()->perfiles['fabrica']['entrada'])
        ->toBe(480)
        ->not->toBe($antes);
});
