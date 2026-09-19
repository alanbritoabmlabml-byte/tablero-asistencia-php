<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registro extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'carga_id', 'empleado_idx', 'dep_idx', 'dia', 'marcas', 'codigos',
        'programado', 'feriado', 'estado', 'trabajado', 'atraso', 'incompleta',
        'jornada', 'entrada', 'salida', 'extra', 'salida_anticipada', 'objetivo',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }
}
