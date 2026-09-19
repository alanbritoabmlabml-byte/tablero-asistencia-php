<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Empleado extends Model
{
    public $timestamps = false;

    protected $fillable = ['carga_id', 'idx', 'ci', 'nombre', 'dep_idx', 'desde', 'hasta'];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }
}
