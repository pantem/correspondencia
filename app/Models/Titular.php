<?php

namespace App\Models;

use Database\Factories\TitularFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nombre', 'area_id', 'cargo_id', 'desde', 'hasta', 'activo'])]
class Titular extends Model
{
    /** @use HasFactory<TitularFactory> */
    use HasFactory;

    protected $table = 'titulares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
}
