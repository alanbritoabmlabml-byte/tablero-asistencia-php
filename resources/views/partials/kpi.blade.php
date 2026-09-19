@php($k = $kpi)

@if ($k['tipo'] === 'indice')
    <article class="tile heroe">
        <div class="izq">
            <div>
                <p class="ttl">{{ $k['titulo'] }}</p>
                <p class="cifra">
                    <span class="val">{{ $k['texto'] }}</span>
                    <span class="un">{{ $k['unidad'] }}</span>
                </p>
                <p><span class="estado">{{ $k['etiqueta'] }}</span>
                @if ($k['delta'] !== null)
                    <span class="delta">{{ $k['delta'] >= 0 ? '+' : '' }}{{ number_format($k['delta'], 1, ',', '.') }} pts vs periodo anterior</span>
                @endif
                </p>
            </div>
            {!! $k['aro'] !!}
        </div>

        <div class="dims">
            @foreach ($k['partes'] as $p)
                <div class="d">
                    <span class="n">{{ $p['nombre'] }} <span class="p">peso {{ (int) $p['peso'] }} %</span></span>
                    <span class="v">{{ $p['valor'] === null ? '—' : number_format($p['valor'], 0, ',', '.') . ' %' }}</span>
                    <span class="barra"><i style="width: {{ max(0, min(100, $p['valor'] ?? 0)) }}%"></i></span>
                </div>
            @endforeach
            <p class="nota">{{ $k['nota'] }}</p>
        </div>
    </article>
@else
    <article class="tile {{ $k['tono'] }}">
        <p class="ttl">{{ $k['titulo'] }}</p>

        <p class="cifra">
            <span class="val">{{ $k['texto'] }}</span>
            <span class="un">{{ $k['unidad'] }}</span>
        </p>

        <p>
            @if ($k['etiqueta'])
                <span class="estado {{ $k['tono'] }}">{{ $k['etiqueta'] }}</span>
            @endif

            @if ($k['delta'] !== null)
                @php($bueno = ($k['deltaBueno'] ?? true) ? $k['delta'] >= 0 : $k['delta'] <= 0)
                <span class="delta {{ $bueno ? 'sube' : 'baja' }}">
                    {{ $k['delta'] >= 0 ? '+' : '' }}{{ number_format($k['delta'], 1, ',', '.') }} pts
                    @isset($k['metaTexto'])<span class="meta">vs {{ $k['metaTexto'] }}</span>@endisset
                </span>
            @elseif (! empty($k['metaTexto']))
                <span class="delta"><span class="meta">{{ $k['metaTexto'] }}</span></span>
            @endif
        </p>

        <p class="detalle">{{ $k['detalle'] ?? '' }}</p>
    </article>
@endif
