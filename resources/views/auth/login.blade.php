@extends('layouts.app')
@section('titulo', 'Entrar')

@section('cuerpo')
<div class="login">
    <form method="post" action="{{ route('login') }}">
        @csrf

        <h1>Control de Asistencia</h1>
        <p class="sub">Plásticos Carmen S.R.L. · tablero de gestión</p>

        @if ($errors->any())
            <p class="aviso error">{{ $errors->first() }}</p>
        @endif

        <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <label class="chip">
            <input type="checkbox" name="recordar" value="1">
            Mantener la sesión abierta
        </label>

        <button type="submit" class="btn principal">Entrar</button>
    </form>
</div>
@endsection
