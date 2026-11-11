<?php

namespace App\Models;

use App\Enums\EstadoTurno;
use App\Enums\TipoAsignacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_id',
    'area_destino_id',
    'tipo_asignacion',
    'estado',
    'responsable_id',
    'instrucciones',
    'plazo',
    'es_turnado_inicial',
    'turno_padre_id',
    'turnado_por_id',
    'resuelto_por_id',
    'resuelto_at',
])]
class Turno extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_asignacion' => TipoAsignacion::class,
            'estado' => EstadoTurno::class,
            'es_turnado_inicial' => 'boolean',
            'plazo' => 'date',
            'resuelto_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function areaDestino(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_destino_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function turnadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'turnado_por_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_id');
    }

    public function turnoPadre(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_padre_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * @return HasMany<Volante>
     */
    public function volantes(): HasMany
    {
        return $this->hasMany(Volante::class);
    }

    /**
     * Indicador RF-21: solo los turnos de atención con plazo vencido cuentan
     * como vencidos; los de conocimiento nunca.
     */
    public function vencido(): bool
    {
        if ($this->tipo_asignacion !== TipoAsignacion::Atencion) {
            return false;
        }

        if (in_array($this->estado, [EstadoTurno::Resuelto, EstadoTurno::Cancelado], true)) {
            return false;
        }

        return $this->plazo !== null && $this->plazo->isPast();
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [EstadoTurno::Pendiente, EstadoTurno::EnTramite], true);
    }
}
