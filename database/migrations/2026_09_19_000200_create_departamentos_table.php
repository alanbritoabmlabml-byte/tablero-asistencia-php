<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained()->cascadeOnDelete();
            // indice dentro de la carga: es la clave que usan registros y empleados
            $table->unsignedSmallInteger('idx');
            $table->string('nombre');
            $table->string('perfil');     // fabrica | tanques | admin | almacen
            $table->string('seccion');    // seccion de planta o Administracion
            $table->unique(['carga_id', 'idx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
