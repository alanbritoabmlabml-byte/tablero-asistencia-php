@php($pct = fn ($v, $d = 1) => $v === null ? '—' : number_format($v, $d, ',', '.') . ' %')

<div class="tabla-wrap">
    <table class="datos">
        <caption class="solo-lectores">Indicadores por área contra las metas configuradas</caption>
        <thead>
            <tr>
                <th scope="col">Área</th>
                <th scope="col">Sección</th>
                <th scope="col" class="n">Personas</th>
                <th scope="col" class="n">Asistencia</th>
                <th scope="col" class="n">Puntualidad</th>
                <th scope="col" class="n">Permanencia</th>
                <th scope="col" class="n">Marcación</th>
                <th scope="col" class="n">Faltas</th>
                <th scope="col" class="n">H. extra</th>
                <th scope="col" class="n">Índice</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($g['filas'] as $f)
                <tr>
                    <th scope="row">{{ $f['nombre'] }}</th>
                    <td>{{ $f['seccion'] }}</td>
                    <td class="n">{{ number_format($f['personas'], 0, ',', '.') }}</td>
                    <td class="n"><span class="st {{ $f['asistenciaTono'] }}">{{ $pct($f['asistencia']) }}</span></td>
                    <td class="n"><span class="st {{ $f['puntualidadTono'] }}">{{ $pct($f['puntualidad']) }}</span></td>
                    <td class="n"><span class="st {{ $f['permanenciaTono'] }}">{{ $pct($f['permanencia']) }}</span></td>
                    <td class="n"><span class="st {{ $f['completaTono'] }}">{{ $pct($f['completa']) }}</span></td>
                    <td class="n">{{ number_format($f['faltas'], 0, ',', '.') }}</td>
                    <td class="n">{{ number_format($f['horasExtra'], 0, ',', '.') }}</td>
                    <td class="n"><b>{{ $f['indice'] === null ? '—' : number_format($f['indice'], 1, ',', '.') }}</b></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Conjunto del alcance</td>
                <td class="n">{{ $pct($g['conjunto']['asistencia']) }}</td>
                <td class="n">{{ $pct($g['conjunto']['puntualidad']) }}</td>
                <td class="n">{{ $pct($g['conjunto']['permanencia']) }}</td>
                <td class="n">{{ $pct($g['conjunto']['completa']) }}</td>
                <td class="n" colspan="2"></td>
                <td class="n">{{ $g['conjunto']['indice'] === null ? '—' : number_format($g['conjunto']['indice'], 1, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
