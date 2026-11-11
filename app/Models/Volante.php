<?php

namespace App\Models;

use App\Enums\OrigenVolante;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'turno_id',
    'movimiento_id',
    'version',
    'origen',
    'path',
    'sha256',
    'emitido_por',
    'emitido_at',
])]
class Volante extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen' => OrigenVolante::class,
            'version' => 'integer',
            'emitido_at' => 'datetime',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }
}
