@extends('layouts.app')
@section('titulo', 'Configuración')

@section('cuerpo')
@include('partials.cabecera')

@php($hhmm = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60))

<main>
    <div class="secttl">
        <h2>Configuración del cálculo</h2>
        <p class="hint">
            Perfiles de jornada, sección de planta de cada área, metas del semáforo y calendario de feriados.
            Al guardar se recalculan los {{ number_format($carga->registros, 0, ',', '.') }} registros de la carga activa:
            no quedan números viejos con parámetros nuevos.
        </p>
    </div>

    @if (session('ok'))<p class="aviso ok">{{ session('ok') }}</p>@endif
    @if ($errors->any())<p class="aviso error">{{ $errors->first() }}</p>@endif

    <form method="post" action="{{ route('configuracion.update') }}">
        @csrf @method('PUT')

        <section class="card ancha">
            <h3>Perfiles de jornada</h3>
            <p class="sub">
                Los horarios reales difieren por área: planta marca cerca de 07:30–19:30, Tanques 06:30–18:40 y
                Administración 08:00–16:30. Una hora de ingreso única distorsionaría la tasa de atrasos.
            </p>

            <div class="tabla-wrap">
                <table class="datos">
                    <thead>
                        <tr>
                            <th scope="col">Perfil</th>
                            <th scope="col">Ingreso</th>
                            <th scope="col">Salida</th>
                            <th scope="col" class="n">Tol. ingreso</th>
                            <th scope="col" class="n">Tol. salida</th>
                            <th scope="col" class="n">Horas estimadas</th>
                            <th scope="col" class="n">Holgura jornada</th>
                            <th scope="col" class="n">Almuerzo</th>
                            <th scope="col">Sábado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($par->perfiles as $clave => $p)
                            <tr>
                                <th scope="row">{{ $p['nombre'] }}</th>
                                <td><input type="time" name="perfiles[{{ $clave }}][entrada]" value="{{ $hhmm($p['entrada']) }}" required></td>
                                <td><input type="time" name="perfiles[{{ $clave }}][salida]" value="{{ $hhmm($p['salida']) }}" required></td>
                                <td class="n"><input type="number" name="perfiles[{{ $clave }}][tol]" value="{{ $p['tol'] }}" min="0" max="120" style="width: 74px"> min</td>
                                <td class="n"><input type="number" name="perfiles[{{ $clave }}][tolS]" value="{{ $p['tolS'] }}" min="0" max="120" style="width: 74px"> min</td>
                                <td class="n"><input type="number" name="perfiles[{{ $clave }}][obj]" value="{{ round($p['obj'] / 60, 2) }}" step="0.25" min="1" max="16" style="width: 82px"> h</td>
                                <td class="n"><input type="number" name="perfiles[{{ $clave }}][tolJ]" value="{{ $p['tolJ'] }}" min="0" max="180" style="width: 74px"> min</td>
                                <td class="n"><input type="number" name="perfiles[{{ $clave }}][refri]" value="{{ $p['refri'] }}" min="0" max="180" style="width: 74px"> min</td>
                                <td>
                                    <label class="chip">
                                        <input type="checkbox" name="perfiles[{{ $clave }}][sabado]" value="1" @checked(! empty($p['sab']['on']))>
                                        laborable
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card ancha">
            <h3>Áreas: perfil y sección de planta</h3>
            <p class="sub">
                Cada departamento se mide contra su perfil y se agrupa en una de las siete secciones de planta,
                o en Administración si no es planta.
            </p>

            <div class="tabla-wrap">
                <table class="datos">
                    <thead>
                        <tr>
                            <th scope="col">Área</th>
                            <th scope="col">Perfil de jornada</th>
                            <th scope="col">Sección</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departamentos as $d)
                            <tr>
                                <th scope="row">{{ $d->nombre }}</th>
                                <td>
                                    <select name="grupo[{{ $d->nombre }}]">
                                        @foreach ($par->perfiles as $clave => $p)
                                            <option value="{{ $clave }}" @selected($par->grupoDe($d->nombre) === $clave)>{{ $p['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="seccion[{{ $d->nombre }}]">
                                        @foreach ($secciones as $s)
                                            <option value="{{ $s }}" @selected($par->seccionDe($d->nombre) === $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="rejilla">
            <section class="card">
                <h3>Metas del semáforo</h3>
                <p class="sub">Verde hasta la meta, rojo pasado el crítico, ámbar entre ambos.</p>

                <div class="tabla-wrap">
                    <table class="datos">
                        <thead>
                            <tr><th scope="col">Indicador</th><th scope="col" class="n">Meta</th><th scope="col" class="n">Crítico</th></tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                'asis' => 'Asistencia (%)', 'punt' => 'Puntualidad (%)', 'aus' => 'Ausentismo (%)',
                                'jor' => 'Permanencia (%)', 'comp' => 'Marcación completa (%)', 'sal' => 'Salida anticipada (%)',
                            ] as $clave => $texto)
                                <tr>
                                    <th scope="row">{{ $texto }}</th>
                                    <td class="n"><input type="number" step="0.5" name="metas[{{ $clave }}][meta]" value="{{ $par->meta($clave)[0] }}" style="width: 84px"></td>
                                    <td class="n"><input type="number" step="0.5" name="metas[{{ $clave }}][critico]" value="{{ $par->meta($clave)[1] }}" style="width: 84px"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h3>Feriados y lector</h3>
                <p class="sub">
                    Una fecha por línea, en formato aaaa-mm-dd. Cualquier marcación en esos días entra como
                    hora extra. La lista viene propuesta por el detector: un día hábil con más del 55 % del
                    personal sin registro es un feriado, no una falta masiva.
                </p>

                <div class="campo">
                    <label for="feriados">Feriados del periodo</label>
                    <textarea id="feriados" name="feriados" rows="10">{{ implode("\n", $par->feriados) }}</textarea>
                </div>

                <div class="campo">
                    <label for="capacidadLector">Capacidad del lector biométrico (marcaciones por minuto)</label>
                    <input type="number" id="capacidadLector" name="capacidadLector" value="{{ $par->capacidadLector }}" min="1" max="200">
                </div>
            </section>
        </div>

        <p style="margin-top: 18px">
            <button type="submit" class="btn principal">Guardar y recalcular</button>
            <a class="btn" href="{{ route('tablero', 'gerencia') }}">Volver al tablero</a>
        </p>
    </form>
</main>
@endsection
