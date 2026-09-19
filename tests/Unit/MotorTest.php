<?php

use App\Services\Asistencia\Agregador;
use App\Services\Asistencia\Codigos;
use App\Services\Asistencia\CsvParser;
use App\Services\Asistencia\Filtro;
use App\Services\Asistencia\Metricas;
use App\Services\Asistencia\Motor;
use App\Services\Asistencia\Parametros;

/**
 * Un CSV minimo y controlado: cada fila prueba una regla concreta del motor.
 * Si estas cuentas cambian, cambian todos los tableros.
 */
function csvDePrueba(): string
{
    // 2026-01-05 lunes, 06 martes, 07 miercoles (feriado), 10 sabado, 11 domingo
    $cab = '"CI";"Nombre";"Departamento";"2026-01-05";"2026-01-06";"2026-01-07";"2026-01-10";"2026-01-11"';

    return implode("\n", [
        $cab,
        // puntual, jornada completa de fabrica (07:30-19:30 = 12 h menos 1 h de almuerzo = 11 h)
        '"1";"Puntual Fabrica";"BOLSA";"07:30 19:30";"07:28 19:31";"SR";"";""',
        // atraso de 25 min el primer dia
        '"2";"Atrasado";"BOLSA";"07:55 19:30";"07:30 19:30";"SR";"";""',
        // falta con codigo I, luego sin registro
        '"3";"Ausente";"BOLSA";"I";"SR";"SR";"";""',
        // vacacion y vacacion de Navidad
        '"4";"De vacaciones";"BOLSA";"V";"VDN";"SR";"";""',
        // baja medica
        '"5";"Con licencia";"BOLSA";"BM";"BM";"SR";"";""',
        // marcacion impar: incompleta
        '"6";"Incompleta";"BOLSA";"07:30";"07:30 19:30";"SR";"";""',
        // trabajo en sabado y en feriado: todo es hora extra
        '"7";"Extra";"BOLSA";"07:30 19:30";"07:30 19:30";"08:00 12:00";"08:00 12:00";""',
        // administracion: 08:00-16:30, 8 h netas
        '"8";"Oficina";"ADM";"08:00 16:30";"08:05 16:30";"SR";"";""',
    ]) . "\n";
}

function agregadoDePrueba(): array
{
    $p = (new CsvParser)->parse(csvDePrueba());
    $par = Parametros::paraDepartamentos($p->departamentos);
    $par->feriados = ['2026-01-07'];

    $regs = iterator_to_array((new Motor($par))->calcular($p), false);
    $A = (new Agregador($p, $par))->agregar($regs, new Filtro(0, $p->dias() - 1));

    return [$p, $par, $regs, $A, Metricas::de($A)];
}

it('reconoce el formato del export', function () {
    $p = (new CsvParser)->parse(csvDePrueba());

    expect($p->empleados)->toHaveCount(8)
        ->and($p->departamentos)->toBe(['ADM', 'BOLSA'])
        ->and($p->fechas)->toHaveCount(5)
        ->and($p->fechas[0])->toBe('2026-01-05');
});

it('descarta marcaciones repetidas del lector', function () {
    $csv = "\"CI\";\"Nombre\";\"Departamento\";\"2026-01-05\"\n\"9\";\"Rebote\";\"BOLSA\";\"07:30 07:31 19:30\"\n";
    $p = (new CsvParser)->parse($csv);

    // 07:31 esta a un minuto de 07:30: es el mismo paso por el lector.
    expect($p->qa['marcaciones_duplicadas'])->toBe(1)
        ->and($p->empleados[0]['celdas'][0])->toBe('0730,1930');
});

it('calcula la jornada descontando el almuerzo una sola vez', function () {
    [, , $regs] = agregadoDePrueba();

    $r = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 0 && $x['dia'] === 0);

    // 07:30 a 19:30 son 720 min; menos 60 de almuerzo = 660 = 11 h.
    expect($r['trabajado'])->toBe(660)
        ->and($r['estado'])->toBe(Codigos::PRESENTE)
        ->and($r['atraso'])->toBe(-1)
        ->and($r['jornada'])->toBe(2);
});

it('marca el atraso contra el horario del perfil, no contra una hora unica', function () {
    [, , $regs] = agregadoDePrueba();

    $fabrica = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 1 && $x['dia'] === 0);
    $oficina = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 7 && $x['dia'] === 1);

    // Fabrica entra 07:30 con 10 min de tolerancia: 07:55 son 25 min de atraso.
    expect($fabrica['atraso'])->toBe(25);
    // Administracion entra 08:00; 08:05 esta dentro de la tolerancia.
    expect($oficina['atraso'])->toBe(-1);
});

it('cuenta como falta el codigo I y el sin registro en dia programado', function () {
    [, , , $A] = agregadoDePrueba();

    // Ausente falta el lunes (I) y el martes (SR). El miercoles es feriado: no cuenta.
    expect($A['faltaI'])->toBe(1)
        ->and($A['faltaSR'])->toBeGreaterThanOrEqual(1);
});

it('no cuenta el feriado como dia programado', function () {
    [$p, , $regs] = agregadoDePrueba();

    $feriado = collect($regs)->where('dia', 2);

    expect($feriado->every(fn ($r) => $r['programado'] === 0))->toBeTrue()
        ->and($feriado->every(fn ($r) => $r['feriado'] === 1))->toBeTrue();
});

it('trata como hora extra todo lo trabajado fuera de dia programado', function () {
    [, , $regs, $A] = agregadoDePrueba();

    $feriado = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 6 && $x['dia'] === 2);
    $sabado  = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 6 && $x['dia'] === 3);

    expect($feriado['extra'])->toBe(240)   // 08:00 a 12:00
        ->and($sabado['extra'])->toBe(240)
        ->and($A['minExtraFer'])->toBe(240)
        ->and($A['minExtraFds'])->toBe(240)
        ->and($A['ferDias'])->toBe(1);
});

it('separa vacaciones de licencias', function () {
    [, , , $A] = agregadoDePrueba();

    expect($A['vac'])->toBe(2)
        ->and($A['vacTipos']['V'])->toBe(1)
        ->and($A['vacTipos']['VDN'])->toBe(1)
        ->and($A['lic'])->toBe(2)
        ->and($A['licTipos']['BM'])->toBe(2);
});

it('detecta la marcacion incompleta y la deja fuera del calculo de jornada', function () {
    [, , $regs, $A] = agregadoDePrueba();

    $r = collect($regs)->firstWhere(fn ($x) => $x['empleado_idx'] === 5 && $x['dia'] === 0);

    expect($r['incompleta'])->toBe(1)
        ->and($r['jornada'])->toBe(0)
        ->and($A['inc'])->toBe(1);
});

it('valora la capacidad perdida a la jornada objetivo del perfil', function () {
    [, , , $A] = agregadoDePrueba();

    // Cada falta de BOLSA vale 660 min; las faltas del periodo son las del ausente.
    expect($A['minFalta'])->toBeGreaterThan(0)
        ->and($A['minFalta'] % 60)->toBe(0);
});

it('pondera el indice de cumplimiento con los pesos declarados', function () {
    $m = Metricas::indice(['asis' => 100.0, 'punt' => 100.0, 'jorOk' => 0.0, 'compPct' => 0.0]);

    // 0,4 + 0,3 de las dos primeras dimensiones. Con coma flotante hay que
    // comparar con tolerancia: la suma exacta da 70,00000000000001.
    expect($m)->toEqualWithDelta(70.0, 0.0001);
});

it('devuelve null en vez de cero cuando no hay denominador', function () {
    expect(Metricas::pct(0, 0))->toBeNull()
        ->and(Metricas::pct(3, 10))->toEqualWithDelta(30.0, 0.0001);
});

it('detecta feriados por el porcentaje de sin registro', function () {
    $p = (new CsvParser)->parse(csvDePrueba());

    // El miercoles 07 todos figuran SR: el detector lo propone como feriado.
    expect($p->feriadosDetectados)->toContain('2026-01-07');
});

it('respeta el horario de sabado de Administracion', function () {
    $par = Parametros::paraDepartamentos(['ADM']);
    $perfil = $par->perfilDe('ADM');
    $sabado = $par->perfilDia($perfil, 6);

    // Administracion trabaja los sabados de 08:00 a 12:30, sin descuento de almuerzo.
    expect($sabado['entrada'])->toBe(480)
        ->and($sabado['salida'])->toBe(750)
        ->and($sabado['obj'])->toBe(270)
        ->and($sabado['refri'])->toBe(0);
});

it('elige el turno de Almacen Bolsa segun la primera marcacion', function () {
    $par = Parametros::paraDepartamentos(['ALMACEN BOLSA']);
    $perfil = $par->perfilDe('ALMACEN BOLSA');

    $manana = $par->perfilDia($perfil, 1, 7 * 60 + 28);
    $tarde  = $par->perfilDia($perfil, 1, 15 * 60 + 35);

    expect($manana['entrada'])->toBe(450)
        ->and($tarde['entrada'])->toBe(930);
});
