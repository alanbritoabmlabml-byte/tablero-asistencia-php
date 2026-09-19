@extends('layouts.app')
@section('titulo', 'Datos cargados')

@section('cuerpo')
@include('partials.cabecera')

<main>
    <div class="secttl">
        <h2>Datos cargados</h2>
        <p class="hint">
            Cada importación queda guardada como una carga. La carga activa es la que ve todo el mundo;
            las anteriores se conservan y se pueden volver a activar.
        </p>
    </div>

    @if (session('ok'))<p class="aviso ok">{{ session('ok') }}</p>@endif
    @if ($errors->any())<p class="aviso error">{{ $errors->first() }}</p>@endif

    <section class="card ancha">
        <h3>Importar el export del Control de Asistencia</h3>
        <p class="sub">
            CSV ancho separado por «;» con CI, Nombre, Departamento y una columna por fecha
            (aaaa-mm-dd o dd/mm/aaaa). Las marcaciones del día van en la misma celda, separadas por
            espacios; los códigos aceptados son V, VDN, LDM, LPM, LR, BM, I y SR.
        </p>

        <form method="post" action="{{ route('cargas.store') }}" enctype="multipart/form-data"
              style="display: grid; gap: 12px; max-width: 520px">
            @csrf
            <div class="campo">
                <label for="archivo">Archivo CSV</label>
                <input type="file" id="archivo" name="archivo" accept=".csv,text/csv" required>
            </div>
            <button type="submit" class="btn principal">Importar y activar</button>
        </form>
    </section>

    <section class="card ancha">
        <h3>Historial</h3>

        <div class="tabla-wrap">
            <table class="datos">
                <thead>
                    <tr>
                        <th scope="col">Archivo</th>
                        <th scope="col">Periodo</th>
                        <th scope="col" class="n">Días</th>
                        <th scope="col" class="n">Personas</th>
                        <th scope="col" class="n">Registros</th>
                        <th scope="col">Importó</th>
                        <th scope="col">Estado</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cargas as $c)
                        <tr>
                            <th scope="row">{{ $c->archivo }}</th>
                            <td>{{ $c->desde->format('d/m/Y') }} → {{ $c->hasta->format('d/m/Y') }}</td>
                            <td class="n">{{ number_format($c->dias, 0, ',', '.') }}</td>
                            <td class="n">{{ number_format($c->empleados, 0, ',', '.') }}</td>
                            <td class="n">{{ number_format($c->registros, 0, ',', '.') }}</td>
                            <td>{{ $c->usuario?->name ?? '—' }}<br><small>{{ $c->created_at->format('d/m/Y H:i') }}</small></td>
                            <td>
                                @if ($c->activa)
                                    <span class="st good">activa</span>
                                @else
                                    <span class="st x">histórico</span>
                                @endif
                            </td>
                            <td style="display: flex; gap: 6px">
                                @unless ($c->activa)
                                    <form method="post" action="{{ route('cargas.activar', $c) }}">
                                        @csrf
                                        <button class="btn" type="submit">Activar</button>
                                    </form>
                                    <form method="post" action="{{ route('cargas.destroy', $c) }}"
                                          onsubmit="return confirm('Se borrarán los {{ number_format($c->registros, 0, ',', '.') }} registros de esta carga. ¿Seguir?')">
                                        @csrf @method('DELETE')
                                        <button class="btn peligro" type="submit">Borrar</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Todavía no hay ninguna carga.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
