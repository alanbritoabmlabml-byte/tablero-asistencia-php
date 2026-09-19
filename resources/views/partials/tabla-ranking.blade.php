<div class="tabla-wrap">
    <table class="datos">
        <caption class="solo-lectores">{{ $g['titulo'] }}</caption>
        <thead>
            <tr>
                @foreach ($g['columnas'] as $i => $c)
                    <th scope="col" class="{{ $i >= 3 ? 'n' : '' }}">{{ $c }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($g['filas'] as $f)
                <tr>
                    @foreach (array_values($f) as $i => $v)
                        @if ($i === 0)
                            <th scope="row">{{ $v }}</th>
                        @else
                            <td class="{{ $i >= 3 ? 'n' : '' }}">
                                {{ is_float($v) ? number_format($v, 1, ',', '.') : (is_int($v) ? number_format($v, 0, ',', '.') : $v) }}
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($g['total'] > count($g['filas']))
    <p class="sub">
        Se muestran {{ count($g['filas']) }} de {{ number_format($g['total'], 0, ',', '.') }} personas.
        La descarga del detalle trae todas.
    </p>
@endif
