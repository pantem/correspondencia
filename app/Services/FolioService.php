<?php

namespace App\Services;

use App\Models\FolioContador;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class FolioService
{
    /**
     * Asigna el siguiente folio consecutivo del año (RF-06…RF-08).
     *
     * Formato de referencia 0001-2027 (RF-07). El bloqueo SELECT ... FOR UPDATE
     * dentro de la transacción evita folios duplicados bajo concurrencia en MySQL.
     */
    public function siguiente(int $anio): string
    {
        return DB::transaction(function () use ($anio): string {
            $contador = $this->bloquearContador($anio);

            $contador->ultimo_folio++;
            $contador->save();

            return sprintf('%04d-%d', $contador->ultimo_folio, $anio);
        });
    }

    private function bloquearContador(int $anio): FolioContador
    {
        $contador = FolioContador::query()
            ->where('anio', $anio)
            ->lockForUpdate()
            ->first();

        if ($contador !== null) {
            return $contador;
        }

        try {
            FolioContador::create(['anio' => $anio, 'ultimo_folio' => 0]);
        } catch (UniqueConstraintViolationException) {
            // Otro proceso creó el contador concurrentemente; se bloquea la fila existente.
        }

        return FolioContador::query()
            ->where('anio', $anio)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
