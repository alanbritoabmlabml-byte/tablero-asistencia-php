<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>@yield('titulo', 'Control de Asistencia') · Plásticos Carmen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Carlito:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/marca.css') }}">
<link rel="stylesheet" href="{{ asset('css/tablero.css') }}">
{{-- El tema se aplica antes de pintar para que no haya destello blanco en modo oscuro. --}}
<script>
try {
    var t = localStorage.getItem('pc.asistencia.tema');
    if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
} catch (e) {}
</script>
</head>
<body>
@yield('cuerpo')
<script src="{{ asset('js/tablero.js') }}"></script>
@stack('scripts')
</body>
</html>
