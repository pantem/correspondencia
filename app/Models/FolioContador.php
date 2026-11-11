<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['anio', 'ultimo_folio'])]
class FolioContador extends Model
{
    protected $table = 'folios_contador';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $primaryKey = 'anio';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'ultimo_folio' => 'integer',
        ];
    }
}
