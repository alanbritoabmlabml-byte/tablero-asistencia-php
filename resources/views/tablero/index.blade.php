@extends('layouts.app')
@section('titulo', $datos['config']['titulo'])

@section('cuerpo')
@include('partials.cabecera')
@include('partials.filtros')

<main>
    <nav class="vistas" aria-label="Vista por rol">
        @foreach ($vistas as $clave => $v)
            <a href="{{ route('tablero', $clave) }}"
               @if ($clave === $datos['vista']) aria-current="page" @endif>
                {{ $v['titulo'] }}
            </a>
        @endforeach
    </nav>

    <p class="vdesc">{{ $datos['config']['descripcion'] }}</p>

    <div class="alcance">
        <span class="tag">Periodo: <b>{{ $datos['filtro']['desde'] }} → {{ $datos['filtro']['hasta'] }}</b></span>
        <span class="tag">Área: <b>{{ $datos['filtro']['departamentos'] ? count($datos['filtro']['departamentos']) . ' seleccionadas' : 'todas' }}</b></span>
        <span class="tag">Registros: <b>{{ number_format($datos['carga']['registros'], 0, ',', '.') }}</b></span>
        <span class="tag">Perfiles: <b>{{ implode(' · ', $datos['carga']['perfiles']) }}</b></span>
    </div>

    <div class="secttl">
        <h2>Lo que decide {{ $datos['config']['titulo'] }}</h2>
        <p class="hint">
            Cada tarjeta muestra su valor, la desviación frente a la meta y frente al periodo anterior,
            y los valores absolutos que la explican.
        </p>
    </div>

    <div class="kpi-band">
        @foreach ($datos['kpis'] as $kpi)
            @include('partials.kpi', ['kpi' => $kpi])
        @endforeach
    </div>

    <div class="secttl">
        <h2>Evidencia</h2>
        <p class="hint">Los gráficos responden al mismo alcance que las tarjetas. Pasá el puntero sobre cualquier barra para ver su valor exacto.</p>
    </div>

    <div class="rejilla">
        @if ($datos['hallazgos'])
            @include('partials.hallazgos', ['hallazgos' => $datos['hallazgos']])
        @endif

        @foreach ($datos['graficos'] as $grafico)
            @include('partials.grafico', ['grafico' => $grafico])
        @endforeach
    </div>

    <p class="sub" style="margin-top: 26px">
        Origen: {{ $carga->archivo }} · {{ number_format($carga->registros, 0, ',', '.') }} registros
        · importado el {{ $carga->created_at->format('d/m/Y H:i') }}
        @if (! empty($datos['carga']['qa']['marcaciones_duplicadas']))
            · {{ number_format($datos['carga']['qa']['marcaciones_duplicadas'], 0, ',', '.') }} marcaciones repetidas descartadas (rebote del lector)
        @endif
    </p>
</main>
@endsection
