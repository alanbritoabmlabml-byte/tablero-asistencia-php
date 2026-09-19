@php($r = $g['resumen'])
@php($n = fn ($v) => number_format($v, 0, ',', '.'))

<div class="tabla-wrap">
    <table class="datos">
        <caption class="solo-lectores">Resumen del alcance filtrado</caption>
        <thead>
            <tr>
                <th scope="col">Concepto</th>
                <th scope="col" class="n">Días-persona</th>
            </tr>
        </thead>
        <tbody>
            <tr><th scope="row">Presentes</th><td class="n">{{ $n($r['pres']) }}</td></tr>
            <tr><th scope="row">Faltas</th><td class="n">{{ $n($r['falta']) }}</td></tr>
            <tr><th scope="row">Vacaciones</th><td class="n">{{ $n($r['vac']) }}</td></tr>
            <tr><th scope="row">Licencias</th><td class="n">{{ $n($r['lic']) }}</td></tr>
            <tr><th scope="row">Con atraso</th><td class="n">{{ $n($r['atr']) }}</td></tr>
            <tr><th scope="row">Con marcación incompleta</th><td class="n">{{ $n($r['inc']) }}</td></tr>
            <tr><th scope="row">Con salida anticipada</th><td class="n">{{ $n($r['salAnt']) }}</td></tr>
            <tr><th scope="row">Horas trabajadas</th><td class="n">{{ $n($r['minTrab'] / 60) }}</td></tr>
            <tr><th scope="row">Horas extra</th><td class="n">{{ $n($r['extra'] / 60) }}</td></tr>
        </tbody>
        <tfoot>
            <tr><td>Total de días-persona en el alcance</td><td class="n">{{ $n($g['total']) }}</td></tr>
        </tfoot>
    </table>
</div>

<p class="sub">
    Para revisar fila por fila, usá <b>Descargar → Detalle completo</b>: trae las
    {{ $n($g['total']) }} filas con marcaciones, atraso, jornada y horas extra de cada día.
</p>
