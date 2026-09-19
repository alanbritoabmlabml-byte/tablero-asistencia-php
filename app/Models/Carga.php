<?php

namespace App\Models;

use App\Services\Asistencia\Parametros;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una importacion del export del Control de Asistencia. Solo una carga esta activa;
 * las anteriores quedan como historico y se pueden volver a activar.
 */
class Carga extends Model
{
    use HasFactory;

    protected $fillable = [
        'archivo', 'origen', 'desde', 'hasta', 'dias', 'empleados', 'registros',
        'hash', 'fechas', 'qa', 'parametros', 'user_id', 'activa',
    ];

    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'fechas' => 'array',
            'qa' => 'array',
            'parametros' => 'array',
            'activa' => 'boolean',
        ];
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(Departamento::class)->orderBy('idx');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class)->orderBy('idx');
    }

    public function registrosRel(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function activa(): ?self
    {
        return static::where('activa', true)->latest('id')->first();
    }

    /** Deja esta carga como la unica activa. */
    public function activar(): void
    {
        static::where('activa', true)->update(['activa' => false]);
        $this->forceFill(['activa' => true])->save();
    }

    public function parametrosObj(): Parametros
    {
        return Parametros::fromArray($this->parametros ?? []);
    }

    public function guardarParametros(Parametros $par): void
    {
        $this->forceFill(['parametros' => $par->toArray()])->save();
    }
}
