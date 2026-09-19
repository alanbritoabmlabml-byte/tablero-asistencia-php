@php($u = auth()->user())
<header class="hdr">
    <div class="marca">
        <span class="mark" aria-hidden="true">PC</span>
        <div>
            <h1>Control de Asistencia · Tablero de Gestión</h1>
            <p class="sub">
                Plásticos Carmen S.R.L.
                @isset($carga)
                    · {{ number_format($carga->empleados, 0, ',', '.') }} empleados
                    · {{ $carga->desde->format('d/m/Y') }} al {{ $carga->hasta->format('d/m/Y') }}
                    · {{ $carga->dias }} días
                @endisset
            </p>
        </div>
    </div>

    <div class="acciones">
        @isset($datos)
            <div class="menu">
                <button type="button" class="btn" id="btnDescargar" aria-expanded="false" aria-haspopup="true">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M8 2v8m0 0 3-3m-3 3L5 7M2.5 12.5h11"/>
                    </svg>
                    Descargar
                </button>
                <div id="menuDescargar" hidden>
                    <a href="{{ route('descargar.areas', request()->query()) }}">
                        Resumen por áreas <small>Un renglón por área con sus tasas y horas</small>
                    </a>
                    <a href="{{ route('descargar.meses', request()->query()) }}">
                        Serie mensual <small>Asistencia, novedades y horas extra mes a mes</small>
                    </a>
                    <a href="{{ route('descargar.feriados', request()->query()) }}">
                        Feriados trabajados <small>Quién marcó en cada feriado, por área</small>
                    </a>
                    <a href="{{ route('descargar.detalle', request()->query()) }}">
                        Detalle completo <small>Una fila por persona y día del alcance</small>
                    </a>
                </div>
            </div>
        @endisset

        <button type="button" class="btn" id="btnTema">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <circle cx="8" cy="8" r="3.2"/>
                <path d="M8 .8v1.8M8 13.4v1.8M.8 8h1.8M13.4 8h1.8M2.9 2.9l1.3 1.3M11.8 11.8l1.3 1.3M13.1 2.9l-1.3 1.3M4.2 11.8l-1.3 1.3" stroke-linecap="round"/>
            </svg>
            <span>Tema oscuro</span>
        </button>

        <button type="button" class="btn" id="btnImprimir">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="M4.5 6V2.5h7V6M4.5 12H3V6h10v6h-1.5M4.5 9.5h7v4h-7z"/>
            </svg>
            Imprimir
        </button>

        @if ($u?->esAdministrador())
            <a class="btn" href="{{ route('cargas.index') }}">Datos</a>
            <a class="btn" href="{{ route('configuracion.edit') }}">Configuración</a>
        @endif

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn">Salir</button>
        </form>
    </div>
</header>
