<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departamento extends Model
{
    public $timestamps = false;

    protected $fillable = ['carga_id', 'idx', 'nombre', 'perfil', 'seccion'];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }
}
