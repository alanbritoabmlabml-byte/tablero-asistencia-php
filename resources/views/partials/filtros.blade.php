@php($f = $datos['filtro'])
@php($c = $datos['carga'])

<div class="barra-filtros no-print">
    <button type="button" class="btn" id="btnFiltros" aria-expanded="false" aria-controls="panelFiltros">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M2 4h12M4 8h8M6.5 12h3"/>
        </svg>
        Filtros
    </button>

    <p class="resumen">
        {{ \Carbon\Carbon::parse($f['desde'])->format('d/m') }}–{{ \Carbon\Carbon::parse($f['hasta'])->format('d/m') }}
        · {{ $f['departamentos'] ? count($f['departamentos']) . ' áreas' : 'todas las áreas' }}
        @if ($f['empleado']) · persona «{{ $f['empleado'] }}» @endif
        @if ($f['novedades']) · {{ count($f['novedades']) }} novedades @endif
    </p>
</div>

<form class="panel-filtros no-print" id="panelFiltros" method="get" hidden>
    <div class="campo">
        <label for="fDesde">Desde</label>
        <input type="date" id="fDesde" name="desde" value="{{ $f['desde'] }}"
               min="{{ $c['desde'] }}" max="{{ $c['hasta'] }}">
    </div>

    <div class="campo">
        <label for="fHasta">Hasta</label>
        <input type="date" id="fHasta" name="hasta" value="{{ $f['hasta'] }}"
               min="{{ $c['desde'] }}" max="{{ $c['hasta'] }}">
    </div>

    <div class="campo">
        <label for="fRango">Rango rápido</label>
        <select id="fRango" name="rango">
            <option value="">— usar las fechas de arriba —</option>
            <option value="30" @selected(request('rango') === '30')>Últimos 30 días</option>
            <option value="90" @selected(request('rango') === '90')>Últimos 90 días</option>
            <option value="mes" @selected(request('rango') === 'mes')>Mes en curso</option>
            <option value="all" @selected(request('rango') === 'all')>Todo el periodo</option>
        </select>
    </div>

    <div class="campo">
        <label for="fPersona">Persona (nombre o CI)</label>
        <input type="search" id="fPersona" name="persona" value="{{ $f['empleado'] }}" placeholder="Ej.: Calsina o 8938960">
    </div>

    <div class="campo" style="grid-column: 1 / -1">
        <label>Áreas</label>
        <div class="chips">
            @foreach ($c['departamentos'] as $i => $dep)
                <label class="chip">
                    <input type="checkbox" name="areas[]" value="{{ $i }}"
                           @checked($f['departamentos'] && in_array($i, $f['departamentos'], true))>
                    {{ $dep }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="campo" style="grid-column: 1 / -1">
        <label>Novedades (acotan gráficos de detalle y descargas, no las tarjetas)</label>
        <div class="chips">
            @foreach ([
                'presente' => 'Presentes', 'falta' => 'Faltas', 'atraso' => 'Atrasos',
                'vacacion' => 'Vacaciones', 'licencia' => 'Licencias',
                'incompleta' => 'Marcación incompleta', 'salida' => 'Salida anticipada', 'extra' => 'Con hora extra',
            ] as $clave => $texto)
                <label class="chip">
                    <input type="checkbox" name="nov_{{ $clave }}" value="1"
                           @checked(in_array($clave, $f['novedades'], true))>
                    {{ $texto }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="campo" style="grid-column: 1 / -1; grid-auto-flow: column; justify-content: start; gap: 8px">
        <button type="submit" class="btn principal">Aplicar</button>
        <a class="btn" href="{{ route('tablero', $datos['vista']) }}">Quitar filtros</a>
    </div>
</form>
