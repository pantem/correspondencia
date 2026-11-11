<?php

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\Prioridad;
use App\Enums\SentidoCorrespondencia;
use App\Enums\TipoCorrespondencia;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'folio',
    'anio',
    'sentido',
    'tipo_correspondencia',
    'fecha_documento',
    'fecha_recepcion',
    'oficio_original',
    'remitente',
    'dependencia',
    'cargo_remitente',
    'asunto',
    'resumen',
    'observaciones',
    'anexos',
    'canal_recepcion',
    'prioridad',
    'plazo',
    'estado',
    'area_id',
    'datos_extra',
    'user_id',
])]
#[RouteKey('folio')]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_documento' => 'date',
            'fecha_recepcion' => 'date',
            'plazo' => 'date',
            'datos_extra' => 'array',
            'sentido' => SentidoCorrespondencia::class,
            'tipo_correspondencia' => TipoCorrespondencia::class,
            'prioridad' => Prioridad::class,
            'estado' => EstadoDocumento::class,
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /**
     * Volantes de todos los turnos del documento, localizables por folio (RF-38).
     *
     * @return HasManyThrough<Volante, Turno>
     */
    public function volantes(): HasManyThrough
    {
        return $this->hasManyThrough(Volante::class, Turno::class, 'document_id', 'turno_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
