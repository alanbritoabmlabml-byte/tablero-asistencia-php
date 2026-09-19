<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un registro por persona y dia vigente. Guarda el dato crudo (marcas, codigos)
        // y el resultado del motor, para que un cambio de perfil solo obligue a
        // recalcular las columnas derivadas y no a releer el CSV.
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('empleado_idx');
            $table->unsignedSmallInteger('dep_idx');
            $table->unsignedSmallInteger('dia');

            // crudo
            $table->string('marcas', 40)->nullable();   // "0730,1920"
            $table->string('codigos', 24)->nullable();  // "SR" | "V" | "BM+LR"

            // derivado
            $table->unsignedTinyInteger('programado');
            $table->unsignedTinyInteger('feriado');
            $table->unsignedTinyInteger('estado');      // 0 presente 1 falta 2 vacacion 3 licencia 4 no laborable
            $table->unsignedSmallInteger('trabajado');  // minutos
            $table->smallInteger('atraso');             // minutos, -1 si no aplica
            $table->unsignedTinyInteger('incompleta');
            $table->unsignedTinyInteger('jornada');     // 0 no evaluable 1 menos 2 en rango 3 mas
            $table->smallInteger('entrada');            // minutos desde 00:00, -1 si no hay
            $table->smallInteger('salida');
            $table->unsignedSmallInteger('extra');
            $table->unsignedSmallInteger('salida_anticipada');
            $table->unsignedSmallInteger('objetivo');   // jornada objetivo del perfil ese dia

            $table->index(['carga_id', 'dia']);
            $table->index(['carga_id', 'dep_idx']);
            $table->index(['carga_id', 'empleado_idx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
