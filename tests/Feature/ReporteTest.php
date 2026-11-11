<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporteTest extends TestCase
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

    private function responsable(): User
    {
        return $this->userConRol('responsable');
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

    private function crearDocumentoConTurno(?User $responsable = null): string
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->postJson('/api/documents', [
            'sentido' => 'recibida',
            'tipo_correspondencia' => 'oficio',
            'fecha_documento' => now()->subDay()->toDateString(),
            'asunto' => 'Solicitud de obra',
        ])->assertCreated()->json('data.folio');

        $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable?->id])],
        ])->assertCreated();

        return $folio;
    }

    public function test_dashboard_devuelve_kpis_pendientes_por_area_y_mis_turnos(): void
    {
        $responsable = $this->responsable();
        $this->crearDocumentoConTurno($responsable);

        Sanctum::actingAs($responsable);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response
            ->assertJsonPath('data.kpis.documentos', 1)
            ->assertJsonPath('data.kpis.turnos_pendientes', 1)
            ->assertJsonPath('data.kpis.turnos_en_tramite', 0)
            ->assertJsonPath('data.kpis.turnos_resueltos', 0)
            ->assertJsonPath('data.kpis.acuses_pendientes', 1)
            ->assertJsonPath('data.kpis.turnos_vencidos', 0)
            ->assertJsonPath('data.pendientes_por_area.0.total', 1)
            ->assertJsonCount(1, 'data.mis_turnos')
            ->assertJsonPath('data.mis_turnos.0.acuse_pendiente', true);
    }

    public function test_dashboard_requiere_documents_view(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_export_documentos_csv_respeta_filtros(): void
    {
        $this->crearDocumentoConTurno();

        Sanctum::actingAs($this->oficialia());

        $response = $this->get('/api/documents/export')->assertOk();
        $contenido = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Folio', $contenido);
        $this->assertStringContainsString('0001-2026', $contenido);
        $this->assertStringContainsString('Solicitud de obra', $contenido);
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition'),
        );

        $vacio = $this->get('/api/documents/export?folio=NO-EXISTE')->streamedContent();
        $this->assertStringNotContainsString('0001-2026', $vacio);
        $this->assertStringContainsString('Folio', $vacio);
    }

    public function test_export_turnos_csv_con_filtro_de_acuse_pendiente(): void
    {
        $this->crearDocumentoConTurno();

        Sanctum::actingAs($this->oficialia());

        $conAcuse = $this->get('/api/turnos/export?acuse_pendiente=1')->streamedContent();

        $this->assertStringContainsString('Turno', $conAcuse);
        $this->assertStringContainsString('0001-2026', $conAcuse);
        $this->assertStringContainsString('Si', $conAcuse);

        $sinCoincidencias = $this->get('/api/turnos/export?acuse_pendiente=1&estado=resuelto')->streamedContent();
        $this->assertStringNotContainsString('0001-2026', $sinCoincidencias);
    }

    public function test_exportaciones_requieren_documents_view(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->get('/api/documents/export')->assertForbidden();
        $this->get('/api/turnos/export')->assertForbidden();
    }
}
