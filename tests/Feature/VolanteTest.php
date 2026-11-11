<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Movimiento;
use App\Models\Role;
use App\Models\User;
use App\Models\Volante;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolanteTest extends TestCase
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

    private function crearDocumento(): string
    {
        Sanctum::actingAs($this->oficialia());

        return $this->postJson('/api/documents', [
            'sentido' => 'recibida',
            'tipo_correspondencia' => 'oficio',
            'fecha_documento' => now()->subDay()->toDateString(),
            'asunto' => 'Solicitud de obra',
        ])->assertCreated()->json('data.folio');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadTurno(array $overrides = []): array
    {
        return array_merge([
            'area_id' => Area::factory()->create()->id,
            'tipo_asignacion' => 'atencion',
            'instrucciones' => 'Revisar expediente',
        ], $overrides);
    }

    private function turnar(string $folio, array $overrides = []): int
    {
        return $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno($overrides)],
        ])->assertCreated()->json('data.0.id');
    }

    public function test_turno_genera_volante_automatico_al_turnar(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);

        $this->assertDatabaseHas('volantes', [
            'turno_id' => $turnoId,
            'version' => 1,
            'origen' => 'recepcion_inicial',
        ]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'volante.emitido']);

        $volante = Volante::where('turno_id', $turnoId)->firstOrFail();

        Storage::disk(config('filesystems.default'))->assertExists($volante->path);
        $this->assertSame(hash('sha256', Storage::disk(config('filesystems.default'))->get($volante->path)), $volante->sha256);
    }

    public function test_listado_de_volantes_del_turno_y_del_documento(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);

        $this->getJson('/api/turnos/'.$turnoId.'/volantes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.0.origen', 'recepcion_inicial')
            ->assertJsonPath('data.0.emitido_por.id', auth()->id());

        $this->getJson('/api/documents/'.$folio.'/volantes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.turno.folio', $folio);
    }

    public function test_consulta_y_pdf_del_volante_con_qr(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);
        $volante = Volante::where('turno_id', $turnoId)->firstOrFail();

        $this->getJson('/api/volantes/'.$volante->id)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.turno.folio', $folio)
            ->assertJsonStructure(['data' => ['url_pdf', 'turno', 'emitido_por', 'emitido_at']]);

        $this->get('/api/volantes/'.$volante->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith(
            '%PDF',
            Storage::disk(config('filesystems.default'))->get($volante->path),
        );
    }

    public function test_nueva_version_del_volante_versiona_sin_sustituir(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);

        $this->postJson('/api/turnos/'.$turnoId.'/volantes')
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertSame(2, Volante::where('turno_id', $turnoId)->count());
        $this->assertDatabaseHas('volantes', ['turno_id' => $turnoId, 'version' => 1]);
    }

    public function test_no_se_puede_generar_volante_de_turno_cerrado(): void
    {
        $responsable = $this->userConRol('responsable');

        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio, ['responsable_id' => $responsable->id]);

        Sanctum::actingAs($responsable);
        $this->postJson('/api/turnos/'.$turnoId.'/resolver')->assertOk();

        Sanctum::actingAs($this->oficialia());
        $this->postJson('/api/turnos/'.$turnoId.'/volantes')->assertStatus(409);
    }

    public function test_correccion_de_destinatario_registra_movimiento_y_nueva_version(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);
        $nuevaArea = Area::factory()->create();

        $this->putJson('/api/turnos/'.$turnoId, [
            'area_destino_id' => $nuevaArea->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.area_destino.id', $nuevaArea->id);

        $this->assertDatabaseHas('movimientos', ['turno_id' => $turnoId, 'tipo' => 'correccion']);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.corregido']);
        $this->assertSame(2, Volante::where('turno_id', $turnoId)->count());
        $this->assertDatabaseHas('volantes', ['turno_id' => $turnoId, 'version' => 2]);
    }

    public function test_solo_editar_instrucciones_no_solicita_correccion(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);

        $this->putJson('/api/turnos/'.$turnoId, ['instrucciones' => 'Cambio menor'])->assertOk();

        $this->assertSame(0, Movimiento::where('turno_id', $turnoId)->where('tipo', 'correccion')->count());
        $this->assertSame(1, Volante::where('turno_id', $turnoId)->count());
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.actualizado']);
    }

    public function test_generar_volante_requiere_turns_update(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);

        Sanctum::actingAs($this->userConRol('responsable'));

        $this->postJson('/api/turnos/'.$turnoId.'/volantes')->assertForbidden();
    }

    public function test_consulta_de_volante_requiere_documents_view(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->turnar($folio);
        $volante = Volante::where('turno_id', $turnoId)->firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/volantes/'.$volante->id)->assertForbidden();
        $this->getJson('/api/turnos/'.$turnoId.'/volantes')->assertForbidden();
    }
}
