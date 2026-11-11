<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Document;
use App\Models\Movimiento;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TurnadoTest extends TestCase
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

    private function areaUser(): User
    {
        return $this->userConRol('area');
    }

    private function responsableUser(): User
    {
        return $this->userConRol('responsable');
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

    public function test_oficialia_turna_a_varias_areas_en_un_acto(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $area2 = Area::factory()->create();

        $response = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [
                $this->payloadTurno(),
                $this->payloadTurno(['area_id' => $area2->id, 'tipo_asignacion' => 'conocimiento']),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.estado', 'pendiente')
            ->assertJsonPath('data.0.es_turnado_inicial', true)
            ->assertJsonPath('data.1.tipo_asignacion', 'conocimiento');

        $this->assertSame(2, Turno::count());
        $this->assertSame(2, Movimiento::where('tipo', 'turnado')->count());
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.creado']);
    }

    public function test_turnado_interno_sin_regresar_a_oficialia(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $receptor = $this->areaUser();
        $areaOrigen = Area::factory()->create();

        $turno = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['area_id' => $areaOrigen->id, 'responsable_id' => $receptor->id])],
        ])->assertCreated()->json('data.0.id');

        Sanctum::actingAs($receptor);

        $areaDestino = Area::factory()->create();

        $response = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['area_id' => $areaDestino->id])],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.0.es_turnado_inicial', false)
            ->assertJsonPath('data.0.turno_padre_id', $turno);

        $this->assertSame(2, Turno::count());
    }

    public function test_area_sin_turno_activo_no_puede_turnar(): void
    {
        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();

        Sanctum::actingAs($this->areaUser());

        $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno()],
        ])->assertForbidden();
    }

    public function test_usuario_sin_permiso_de_turnar_no_puede(): void
    {
        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();

        Sanctum::actingAs($this->responsableUser());

        $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno()],
        ])->assertForbidden();
    }

    public function test_valida_al_menos_un_area_destino(): void
    {
        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();

        $this->postJson('/api/documents/'.$folio.'/turnos', ['turnos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['turnos']);
    }

    public function test_listado_de_turnos_del_documento(): void
    {
        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();

        $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(), $this->payloadTurno(['tipo_asignacion' => 'conocimiento'])],
        ])->assertCreated();

        $this->getJson('/api/documents/'.$folio.'/turnos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_seguimiento_cambia_pendiente_a_en_tramite(): void
    {
        $responsable = $this->areaUser();
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable->id])],
        ])->json('data.0.id');

        Sanctum::actingAs($responsable);

        $this->postJson('/api/turnos/'.$turnoId.'/seguimiento', [
            'notas' => 'Se giró solicitud a área técnica.',
        ])->assertOk()->assertJsonPath('data.estado', 'en_tramite');

        $this->assertDatabaseHas('movimientos', ['turno_id' => $turnoId, 'tipo' => 'seguimiento']);
    }

    public function test_solo_el_responsable_vigente_puede_resolver(): void
    {
        $responsable = $this->areaUser();
        $otro = $this->areaUser();

        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable->id])],
        ])->json('data.0.id');

        Sanctum::actingAs($otro);
        $this->postJson('/api/turnos/'.$turnoId.'/resolver')->assertForbidden();

        Sanctum::actingAs($responsable);
        $this->postJson('/api/turnos/'.$turnoId.'/resolver')
            ->assertOk()
            ->assertJsonPath('data.estado', 'resuelto')
            ->assertJsonPath('data.resuelto_por.id', $responsable->id);

        $this->assertDatabaseHas('movimientos', ['turno_id' => $turnoId, 'tipo' => 'resolucion']);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.resuelto']);
    }

    public function test_resolver_un_turno_no_cierra_los_demas(): void
    {
        $responsable = $this->areaUser();
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $ids = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [
                $this->payloadTurno(['responsable_id' => $responsable->id]),
                $this->payloadTurno(['responsable_id' => $responsable->id]),
            ],
        ])->assertCreated();

        $primero = $ids->json('data.0.id');
        $segundo = $ids->json('data.1.id');

        Sanctum::actingAs($responsable);

        $this->postJson('/api/turnos/'.$primero.'/resolver')->assertOk();

        $this->assertSame('resuelto', Turno::findOrFail($primero)->estado->value);
        $this->assertSame('pendiente', Turno::findOrFail($segundo)->estado->value);
    }

    public function test_reasignar_conserva_historial_del_responsable_anterior(): void
    {
        $responsable = $this->areaUser();
        $nuevo = $this->areaUser();

        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable->id])],
        ])->json('data.0.id');

        $this->postJson('/api/turnos/'.$turnoId.'/reasignar', [
            'responsable_id' => $nuevo->id,
            'notas' => 'Cambio por disponibilidad.',
        ])->assertOk()->assertJsonPath('data.responsable.id', $nuevo->id);

        $movimiento = Movimiento::query()
            ->where('turno_id', $turnoId)
            ->where('tipo', 'reasignacion')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame($responsable->id, $movimiento->responsable_anterior_id);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.reasignado']);
    }

    public function test_conocimiento_nunca_se_marca_vencido(): void
    {
        Sanctum::actingAs($this->oficialia());

        $conocimiento = Turno::create([
            'document_id' => Document::factory()->create()->id,
            'area_destino_id' => Area::factory()->create()->id,
            'tipo_asignacion' => 'conocimiento',
            'estado' => 'pendiente',
            'plazo' => now()->subDays(3)->toDateString(),
            'turnado_por_id' => $this->oficialia()->id,
        ]);

        $this->assertFalse($conocimiento->fresh()->vencido());

        $this->getJson('/api/turnos?vencido=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_atencion_con_plazo_pasado_se_marca_vencido(): void
    {
        Sanctum::actingAs($this->oficialia());

        $atencion = Turno::create([
            'document_id' => Document::factory()->create()->id,
            'area_destino_id' => Area::factory()->create()->id,
            'tipo_asignacion' => 'atencion',
            'estado' => 'pendiente',
            'plazo' => now()->subDays(3)->toDateString(),
            'turnado_por_id' => $this->oficialia()->id,
        ]);

        $this->assertTrue($atencion->fresh()->vencido());

        $this->getJson('/api/turnos?vencido=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vencido', true);
    }

    public function test_bandeja_filtra_por_responsable_y_estado(): void
    {
        $responsable = $this->areaUser();
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable->id])],
        ])->assertCreated();

        Sanctum::actingAs($responsable);

        $this->getJson('/api/turnos?responsable=me')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'pendiente');

        $this->getJson('/api/turnos?responsable=me&estado=resuelto')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/turnos?responsable=me&tipo_asignacion=atencion')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_turno_resuelto_es_inmutable(): void
    {
        $responsable = $this->areaUser();

        Sanctum::actingAs($this->oficialia());
        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno(['responsable_id' => $responsable->id])],
        ])->json('data.0.id');

        Sanctum::actingAs($responsable);
        $this->postJson('/api/turnos/'.$turnoId.'/resolver')->assertOk();

        $this->postJson('/api/turnos/'.$turnoId.'/resolver')->assertStatus(409);
        $this->postJson('/api/turnos/'.$turnoId.'/seguimiento', ['notas' => 'x'])->assertStatus(409);

        Sanctum::actingAs($this->oficialia());
        $this->putJson('/api/turnos/'.$turnoId, ['instrucciones' => 'Nueva'])->assertStatus(409);
        $this->postJson('/api/turnos/'.$turnoId.'/cancelar', ['motivo' => 'x'])->assertStatus(409);
    }

    public function test_editar_instrucciones_solo_en_pendiente(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno()],
        ])->json('data.0.id');

        $this->putJson('/api/turnos/'.$turnoId, ['instrucciones' => 'Instrucciones corregidas'])
            ->assertOk()
            ->assertJsonPath('data.instrucciones', 'Instrucciones corregidas');

        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.actualizado']);
    }

    public function test_cancelar_turno_con_motivo(): void
    {
        Sanctum::actingAs($this->oficialia());

        $folio = $this->crearDocumento();
        $turnoId = $this->postJson('/api/documents/'.$folio.'/turnos', [
            'turnos' => [$this->payloadTurno()],
        ])->json('data.0.id');

        $this->postJson('/api/turnos/'.$turnoId.'/cancelar', [
            'motivo' => 'Documento duplicado.',
        ])->assertOk()->assertJsonPath('data.estado', 'cancelado');

        $this->assertDatabaseHas('movimientos', ['turno_id' => $turnoId, 'tipo' => 'cancelacion']);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'turno.cancelado']);
    }
}
