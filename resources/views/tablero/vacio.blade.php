@extends('layouts.app')
@section('titulo', 'Sin datos cargados')

@section('cuerpo')
@include('partials.cabecera')

<main>
    <div class="vacio">
        <h2>Todavía no hay datos cargados</h2>
        <p>
            El tablero trabaja con el CSV ancho que exporta el Control de Asistencia
            (CI, Nombre, Departamento y una columna por fecha, separado por «;»).
            El archivo se procesa en el servidor y queda guardado como una carga:
            desde ese momento todos ven exactamente los mismos números.
        </p>

        @if (auth()->user()?->esAdministrador())
            <p><a class="btn principal" href="{{ route('cargas.index') }}">Cargar el export</a></p>
        @else
            <p>Pedí a un administrador del tablero que suba el export del periodo.</p>
        @endif
    </div>
</main>
@endsection
