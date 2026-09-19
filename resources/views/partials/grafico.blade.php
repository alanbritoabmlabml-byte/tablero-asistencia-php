@php($g = $grafico)

<section class="card {{ in_array($g['tipo'], ['semaforo', 'heat', 'ranking', 'apiladas', 'flujo'], true) ? 'ancha' : '' }}">
    <h3>{{ $g['titulo'] }}</h3>
    @isset($g['sub'])<p class="sub">{{ $g['sub'] }}</p>@endisset

    @if (in_array($g['tipo'], ['semaforo', 'secciones', 'ranking', 'detalle'], true))
        @include('partials.tabla-' . $g['tipo'], ['g' => $g])
    @else
        @if (! empty($g['series']) && count($g['series']) > 1)
            <p class="leyenda">
                @foreach ($g['series'] as $i => $s)
                    <span><i style="background: var(--s{{ ($s['serie'] ?? $i + 1) }})"></i>{{ $s['nombre'] }}</span>
                @endforeach
            </p>
        @endif

        <div data-grafico="{{ json_encode($g, JSON_UNESCAPED_UNICODE) }}"></div>

        @isset($g['nota'])<p class="sub">{{ $g['nota'] }}</p>@endisset
    @endif
</section>
