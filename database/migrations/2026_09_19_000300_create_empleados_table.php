<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('idx');
            $table->string('ci', 32)->index();
            $table->string('nombre');
            $table->unsignedSmallInteger('dep_idx');
            // primer y ultimo dia con dato: fuera de ese rango la persona no estaba vigente
            $table->unsignedSmallInteger('desde');
            $table->unsignedSmallInteger('hasta');
            $table->unique(['carga_id', 'idx']);
            $table->index(['carga_id', 'dep_idx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
