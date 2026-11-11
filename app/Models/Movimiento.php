<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'turno_id',
    'tipo',
    'usuario_origen_id',
    'responsable_anterior_id',
    'notas',
    'fecha',
    'acuse_pendiente',
])]
class Movimiento extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'fecha' => 'datetime',
            'acuse_pendiente' => 'boolean',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function usuarioOrigen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_origen_id');
    }

    public function responsableAnterior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_anterior_id');
    }

    /**
     * @return HasMany<Archivo>
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }
}
