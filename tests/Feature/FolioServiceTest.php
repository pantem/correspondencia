<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\FolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_formato_de_folio_es_cuatro_digitos_mas_anio(): void
    {
        $folio = app(FolioService::class)->siguiente(2027);

        $this->assertSame('0001-2027', $folio);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $folio);
    }

    public function test_consecutivo_se_incrementa_por_anio(): void
    {
        $servicio = app(FolioService::class);

        $this->assertSame('0001-2027', $servicio->siguiente(2027));
        $this->assertSame('0002-2027', $servicio->siguiente(2027));
        $this->assertSame('0003-2027', $servicio->siguiente(2027));
    }

    public function test_el_consecutivo_reinicia_en_cada_anio(): void
    {
        $servicio = app(FolioService::class);

        $this->assertSame('0001-2027', $servicio->siguiente(2027));
        $this->assertSame('0001-2028', $servicio->siguiente(2028));
        $this->assertSame('0002-2027', $servicio->siguiente(2027));
    }

    public function test_el_folio_nunca_se_reutiliza_en_el_mismo_anio(): void
    {
        $servicio = app(FolioService::class);

        $folios = collect(range(1, 25))->map(fn () => $servicio->siguiente(2027));

        $this->assertSame(25, $folios->unique()->count());
        $this->assertSame('0025-2027', $folios->last());
    }

    public function test_el_documento_conserva_su_folio_tras_actualizaciones(): void
    {
        $user = User::factory()->create();

        $documento = Document::factory()->create([
            'user_id' => $user->id,
            'folio' => '0007-2027',
            'anio' => 2027,
        ]);

        $documento->update(['asunto' => 'Asunto nuevo']);

        $this->assertSame('0007-2027', $documento->fresh()->folio);
    }
}
