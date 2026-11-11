<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function oficialia(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'oficialia')->firstOrFail());

        return $user;
    }

    private function soloConsulta(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'area')->firstOrFail());

        return $user;
    }

    private function payloadDocumento(array $overrides = []): array
    {
        return array_merge([
            'sentido' => 'recibida',
            'tipo_correspondencia' => 'oficio',
            'fecha_documento' => now()->subDay()->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
            'oficio_original' => 'OF-123',
            'remitente' => 'Juan Pérez',
            'dependencia' => 'Secretaría',
            'cargo_remitente' => 'Coordinador',
            'asunto' => 'Solicitud de informe anual',
            'canal_recepcion' => 'presencial',
            'prioridad' => 'urgente',
        ], $overrides);
    }

    public function test_oficialia_registra_documento_y_obtiene_folio(): void
    {
        Sanctum::actingAs($this->oficialia());

        $response = $this->postJson('/api/documents', $this->payloadDocumento());

        $response
            ->assertCreated()
            ->assertJsonPath('data.folio', sprintf('%04d-%d', 1, now()->year))
            ->assertJsonPath('data.asunto', 'Solicitud de informe anual');

        $this->assertDatabaseHas('documents', [
            'folio' => sprintf('%04d-%d', 1, now()->year),
            'sentido' => 'recibida',
            'estado' => 'registrado',
        ]);
    }

    public function test_los_folios_son_consecutivos(): void
    {
        Sanctum::actingAs($this->oficialia());

        $this->postJson('/api/documents', $this->payloadDocumento())->assertCreated();
        $this->postJson('/api/documents', $this->payloadDocumento())->assertCreated();

        $folios = Document::query()->orderBy('id')->pluck('folio')->all();

        $this->assertSame([
            sprintf('%04d-%d', 1, now()->year),
            sprintf('%04d-%d', 2, now()->year),
        ], $folios);
    }

    public function test_el_folio_es_independiente_del_oficio_original(): void
    {
        Sanctum::actingAs($this->oficialia());

        $response = $this->postJson('/api/documents', $this->payloadDocumento([
            'oficio_original' => 'DO-999',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.oficio_original', 'DO-999');

        $this->assertNotSame('DO-999', $response->json('data.folio'));
    }

    public function test_usuario_sin_permiso_no_puede_registrar(): void
    {
        Sanctum::actingAs($this->soloConsulta());

        $this->postJson('/api/documents', $this->payloadDocumento())->assertForbidden();
    }

    public function test_valida_campos_obligatorios(): void
    {
        Sanctum::actingAs($this->oficialia());

        $this->postJson('/api/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentido', 'tipo_correspondencia', 'fecha_documento', 'asunto']);
    }

    public function test_busqueda_multi_criterio(): void
    {
        Sanctum::actingAs($this->oficialia());

        $area = Area::factory()->create();

        $this->postJson('/api/documents', $this->payloadDocumento([
            'asunto' => 'Obra hidraulica urgente',
            'remitente' => 'Rosa Martínez',
            'area_id' => $area->id,
        ]))->assertCreated();

        $this->postJson('/api/documents', $this->payloadDocumento([
            'asunto' => 'Informe contable',
            'remitente' => 'Pedro López',
        ]))->assertCreated();

        $this->getJson('/api/documents?asunto=hidraulica')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.asunto', 'Obra hidraulica urgente');

        $this->getJson('/api/documents?remitente=Pedro')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/documents?area_id='.$area->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/documents?folio='.sprintf('%04d-%d', 2, now()->year))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_carga_pdf_original(): void
    {
        Storage::fake('local');

        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', $this->payloadDocumento())
            ->assertCreated()
            ->json('data.folio');

        $pdf = UploadedFile::fake()->createWithContent(
            'expediente.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF",
        );

        $response = $this->post('/api/documents/'.$folio.'/archivos', [
            'archivo' => $pdf,
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'expediente.pdf')
            ->assertJsonPath('data.tipo', 'original');

        $this->assertSame(64, strlen($response->json('data.sha256')));

        Storage::disk('local')->assertExists(
            Document::query()->where('folio', $folio)->firstOrFail()->archivos()->first()->path
        );
    }

    public function test_rechaza_archivos_que_no_son_pdf(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', $this->payloadDocumento())
            ->assertCreated()
            ->json('data.folio');

        $this->post('/api/documents/'.$folio.'/archivos', [
            'archivo' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_actualiza_documento_y_registra_bitacora(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', $this->payloadDocumento())
            ->assertCreated()
            ->json('data.folio');

        $this->putJson('/api/documents/'.$folio, [
            'asunto' => 'Asunto corregido',
            'prioridad' => 'normal',
        ])->assertOk()
            ->assertJsonPath('data.asunto', 'Asunto corregido');

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'documento.actualizado',
        ]);

        $log = AuditLog::query()->where('accion', 'documento.actualizado')->first();

        $this->assertSame('Solicitud de informe anual', $log->old_values['asunto']);
        $this->assertSame('Asunto corregido', $log->new_values['asunto']);
    }

    public function test_detalle_incluye_archivos(): void
    {
        Storage::fake('local');

        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', $this->payloadDocumento())
            ->json('data.folio');

        $this->post('/api/documents/'.$folio.'/archivos', [
            'archivo' => UploadedFile::fake()->createWithContent('original.pdf', "%PDF-1.4\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson('/api/documents/'.$folio)
            ->assertOk()
            ->assertJsonPath('data.folio', $folio)
            ->assertJsonCount(1, 'data.archivos');
    }
}
