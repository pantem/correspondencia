<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Area;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userConRol(string $rol): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $rol)->firstOrFail());

        return $user;
    }

    private function oficialia(): User
    {
        return $this->userConRol('oficialia');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadTurno(array $overrides = []): array
    {
        return array_merge([
            'area_id' => Area::factory()->create()->id,
            'tipo_asignacion' => 'atencion',
        ], $overrides);
    }

    private function crearTurno(): int
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', [
            'sentido' => 'recibida',
            'tipo_correspondencia' => 'oficio',
            'fecha_documento' => now()->subDay()->toDateString(),
            'asunto' => 'Solicitud de obra',
        ])->assertCreated()->json('data.folio');

        return $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno()],
        ])->assertCreated()->json('data.0.id');
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'acuse-sellado.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF",
        );
    }

    public function test_turnado_queda_con_acuse_pendiente_hasta_digitalizar(): void
    {
        $turnoId = $this->crearTurno();

        $this->assertDatabaseHas('movimientos', [
            'turno_id' => $turnoId,
            'tipo' => 'turnado',
            'acuse_pendiente' => true,
        ]);

        $this->getJson('/api/turnos?acuse_pendiente=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.acuse_pendiente', true);
    }

    public function test_subir_acuse_versiona_y_quita_el_pendiente(): void
    {
        $turnoId = $this->crearTurno();
        $movimientoId = Turno::findOrFail($turnoId)->movimientos()->where('tipo', 'turnado')->firstOrFail()->id;

        Sanctum::actingAs($this->oficialia());

        $this->post('/api/movimientos/'.$movimientoId.'/acuse', ['archivo' => $this->pdf()])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'acuse')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('movimientos', ['id' => $movimientoId, 'acuse_pendiente' => false]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'acuse.cargado']);

        $this->getJson('/api/turnos?acuse_pendiente=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/turnos/'.$turnoId)
            ->assertOk()
            ->assertJsonPath('data.acuse_pendiente', false);
    }

    public function test_cada_acuse_se_versiona_sin_sustituir_el_anterior(): void
    {
        $turnoId = $this->crearTurno();
        $movimientoId = Turno::findOrFail($turnoId)->movimientos()->where('tipo', 'turnado')->firstOrFail()->id;

        Sanctum::actingAs($this->oficialia());

        $this->post('/api/movimientos/'.$movimientoId.'/acuse', ['archivo' => $this->pdf()])->assertCreated();
        $this->post('/api/movimientos/'.$movimientoId.'/acuse', ['archivo' => $this->pdf()])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertSame(2, Archivo::where('movimiento_id', $movimientoId)->count());
        $this->assertDatabaseHas('archivos', ['movimiento_id' => $movimientoId, 'tipo' => 'acuse', 'version' => 1]);
        $this->assertDatabaseHas('archivos', ['movimiento_id' => $movimientoId, 'tipo' => 'acuse', 'version' => 2]);
    }

    public function test_acuse_debe_ser_pdf(): void
    {
        $turnoId = $this->crearTurno();
        $movimientoId = Turno::findOrFail($turnoId)->movimientos()->where('tipo', 'turnado')->firstOrFail()->id;

        Sanctum::actingAs($this->oficialia());

        $this->post('/api/movimientos/'.$movimientoId.'/acuse', [
            'archivo' => UploadedFile::fake()->create('escaneo.png', 100, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['archivo']);
    }

    public function test_subir_acuse_requiere_documents_upload(): void
    {
        $turnoId = $this->crearTurno();
        $movimientoId = Turno::findOrFail($turnoId)->movimientos()->where('tipo', 'turnado')->firstOrFail()->id;

        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/movimientos/'.$movimientoId.'/acuse', ['archivo' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_historial_del_documento_incluye_acuse_pendiente(): void
    {
        $turnoId = $this->crearTurno();
        $folio = Turno::findOrFail($turnoId)->document->folio;

        Sanctum::actingAs($this->oficialia());

        $this->getJson('/api/documents/'.$folio.'/historial')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'turnado')
            ->assertJsonPath('data.0.acuse_pendiente', true)
            ->assertJsonPath('data.0.turno.id', $turnoId);
    }
}
