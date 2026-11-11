<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }

    private function oficialia(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'oficialia')->firstOrFail());

        return $user;
    }

    public function test_admin_crea_area_y_queda_en_bitacora(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/areas', [
            'nombre' => 'Departamento de Recursos Humanos',
            'siglas' => 'RRHH',
        ])->assertCreated()
            ->assertJsonPath('data.nombre', 'Departamento de Recursos Humanos');

        $this->assertDatabaseHas('areas', ['nombre' => 'Departamento de Recursos Humanos']);

        $log = AuditLog::query()->where('accion', 'area.creada')->first();

        $this->assertNotNull($log);
        $this->assertSame('Departamento de Recursos Humanos', $log->new_values['nombre']);
    }

    public function test_admin_modifica_area_con_old_y_new(): void
    {
        $area = Area::factory()->create(['nombre' => 'Nombre Original']);

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/areas/'.$area->id, [
            'nombre' => 'Nombre Nuevo',
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre Nuevo');

        $log = AuditLog::query()->where('accion', 'area.actualizada')->first();

        $this->assertSame('Nombre Original', $log->old_values['nombre']);
        $this->assertSame('Nombre Nuevo', $log->new_values['nombre']);
        $this->assertNotNull($log->created_at);
        $this->assertNotNull($log->user_id);
    }

    public function test_admin_desactiva_area(): void
    {
        $area = Area::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/areas/'.$area->id, ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertFalse($area->fresh()->activo);
    }

    public function test_no_admin_no_puede_modificar_catalogos(): void
    {
        $area = Area::factory()->create();

        Sanctum::actingAs($this->oficialia());

        $this->postJson('/api/admin/areas', ['nombre' => 'Hacker'])->assertForbidden();
        $this->putJson('/api/admin/areas/'.$area->id, ['nombre' => 'Hacker'])->assertForbidden();
    }

    public function test_catalogos_son_legibles_para_formularios(): void
    {
        Area::factory()->create(['nombre' => 'Área Visible']);
        Area::factory()->inactiva()->create(['nombre' => 'Área Oculta']);

        Sanctum::actingAs($this->oficialia());

        $this->getJson('/api/admin/areas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Área Visible');

        $this->getJson('/api/admin/areas?todas=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_crea_cargo_y_titular(): void
    {
        Sanctum::actingAs($this->admin());

        $areaId = $this->postJson('/api/admin/areas', ['nombre' => 'Área Titular'])->json('data.id');

        $cargoId = $this->postJson('/api/admin/cargos', ['nombre' => 'Jefe de Área'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/admin/titulares', [
            'nombre' => 'Ana López',
            'area_id' => $areaId,
            'cargo_id' => $cargoId,
            'desde' => now()->subYear()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.nombre', 'Ana López')
            ->assertJsonPath('data.area.nombre', 'Área Titular');

        $this->assertDatabaseHas('titulares', ['nombre' => 'Ana López']);
    }

    public function test_titular_requiere_area_y_cargo_validos(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/titulares', [
            'nombre' => 'Sin Datos',
            'area_id' => 9999,
            'cargo_id' => 9999,
            'desde' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['area_id', 'cargo_id']);
    }
}
