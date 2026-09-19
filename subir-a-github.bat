@echo off
REM ---------------------------------------------------------------
REM  Sube el tablero de asistencia (Laravel) a GitHub.
REM  Ejecutar una sola vez, con doble clic, desde esta misma carpeta.
REM ---------------------------------------------------------------
setlocal
cd /d "%~dp0"

echo.
echo  Subiendo el tablero a alanbritoabmlabml-byte/tablero-asistencia-php
echo.

if not exist ".git" (
    git init
    git branch -M main
    git remote add origin https://github.com/alanbritoabmlabml-byte/tablero-asistencia-php.git
)

git add -A
git commit -m "Tablero de Control de Asistencia v14 en Laravel (motor de calculo en PHP, historico de cargas, vistas por rol)"
git push -u origin main

echo.
echo  Listo. Revisa https://github.com/alanbritoabmlabml-byte/tablero-asistencia-php
echo.
pause
