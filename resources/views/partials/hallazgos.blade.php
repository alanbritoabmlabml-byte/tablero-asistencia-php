<section class="card ancha">
    <h3>Hallazgos del periodo</h3>
    <p class="sub">
        Lectura automática de los datos filtrados: qué está bien, qué preocupa y dónde actuar.
        Cada hallazgo indica la regla que lo dispara y muestra su evidencia.
    </p>

    <ul class="hallazgos">
        @foreach ($hallazgos as $h)
            <li class="{{ $h['sev'] }}">
                <svg class="ic" viewBox="0 0 16 16" aria-hidden="true">
                    @if ($h['sev'] === 'crit')
                        <circle cx="8" cy="8" r="6.4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M7.3 4.4h1.4v5H7.3zm0 6h1.4v1.4H7.3z" fill="currentColor"/>
                    @elseif ($h['sev'] === 'warn')
                        <path d="M8 2 15 14H1z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M7.3 6h1.4v4H7.3zm0 5h1.4v1.4H7.3z" fill="currentColor"/>
                    @elseif ($h['sev'] === 'good')
                        <path d="M6.5 11.4 3.6 8.5l1.1-1.1 1.8 1.8 4.8-4.8 1.1 1.1z" fill="currentColor"/>
                    @else
                        <circle cx="8" cy="8" r="6.4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M7.3 7h1.4v4.6H7.3zm0-2.6h1.4v1.4H7.3z" fill="currentColor"/>
                    @endif
                </svg>

                <span class="tx">
                    {{-- El texto trae solo <b> generado por el servidor, nunca datos sin filtrar. --}}
                    {!! $h['texto'] !!}
                    @if ($h['accion'])<span class="act">{{ $h['accion'] }}</span>@endif
                    <span class="why"><b>Por qué:</b> {{ $h['regla'] }}</span>
                </span>

                @if ($h['evidencia'])
                    <div class="ev" data-grafico="{{ json_encode($h['evidencia'], JSON_UNESCAPED_UNICODE) }}"></div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
