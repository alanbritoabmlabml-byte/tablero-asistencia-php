@php($pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.') . ' %')
@php($n = fn ($v) => number_format($v, 0, ',', '.'))

@php($barras = ['tipo' => 'barras', 'formato' => 'pct', 'ref' => $g['ref'], 'refLabel' => $g['refLabel'],
    'datos' => array_map(fn ($f) => ['label' => $f['nombre'], 'v' => $f['asistencia'],
        'hl' => $f['asistencia'] !== null && $f['asistencia'] < $g['meta'],
        'detalle' => $n($f['personas']) . ' personas'], $g['filas'])])

<div data-grafico="{{ json_encode($barras, JSON_UNESCAPED_UNICODE) }}"></div>

<div class="tabla-wrap">
    <table class="datos">
        <caption class="solo-lectores">Capacidad y novedades por sección de planta</caption>
        <thead>
            <tr>
                <th scope="col">Sección</th>
                <th scope="col" class="n">Personas</th>
                <th scope="col" class="n">Asistencia</th>
                <th scope="col" class="n">Puntualidad</th>
                <th scope="col" class="n">Permanencia</th>
                <th scope="col" class="n">Faltas</th>
                <th scope="col" class="n">H. perdidas</th>
                <th scope="col" class="n">H. extra</th>
                <th scope="col" class="n">Feriados</th>
                <th scope="col" class="n">Vac. + lic.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($g['filas'] as $f)
                <tr>
                    <th scope="row">{{ $f['nombre'] }}@unless ($f['planta']) <small>(no planta)</small>@endunless</th>
                    <td class="n">{{ $n($f['personas']) }}</td>
                    <td class="n">{{ $pct($f['asistencia']) }}</td>
                    <td class="n">{{ $pct($f['puntualidad']) }}</td>
                    <td class="n">{{ $pct($f['permanencia']) }}</td>
                    <td class="n">{{ $n($f['faltas']) }}</td>
                    <td class="n">{{ $n($f['horasPerdidas']) }}</td>
                    <td class="n">{{ $n($f['horasExtra']) }}</td>
                    <td class="n">{{ $n($f['feriados']) }}</td>
                    <td class="n">{{ $n($f['vacaciones'] + $f['licencias']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
