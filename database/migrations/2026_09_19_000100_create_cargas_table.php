<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada importacion del export del Control de Asistencia es una carga.
        // Se conserva el historico: cambiar de carga activa no borra las anteriores.
        Schema::create('cargas', function (Blueprint $table) {
            $table->id();
            $table->string('archivo');
            $table->string('origen')->default('CSV');
            $table->date('desde');
            $table->date('hasta');
            $table->unsignedSmallInteger('dias');
            $table->unsignedInteger('empleados');
            $table->unsignedInteger('registros');
            $table->string('hash', 64)->nullable()->index();
            // fechas del periodo, en orden: evita recalcular el calendario en cada consulta
            $table->json('fechas');
            $table->json('qa')->nullable();
            // perfiles de jornada, secciones, metas, feriados y lluvia vigentes para esta carga
            $table->json('parametros');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('activa')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas');
    }
};
