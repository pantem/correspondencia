<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAdminTest extends TestCase
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

    public function test_admin_crea_usuario_con_rol(): void
    {
        $oficialiaRoleId = Role::query()->where('slug', 'oficialia')->firstOrFail()->id;

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/users', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@correspondencia.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role_ids' => [$oficialiaRoleId],
        ])->assertCreated()
            ->assertJsonPath('data.email', 'nuevo@correspondencia.test')
            ->assertJsonPath('data.roles.0.slug', 'oficialia');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@correspondencia.test']);

        $this->assertNotNull(AuditLog::query()->where('accion', 'usuario.creado')->first());
    }

    public function test_admin_desactiva_usuario(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/users/'.$user->id, ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertFalse($user->fresh()->activo);
    }

    public function test_admin_cambia_password_y_roles(): void
    {
        $user = User::factory()->create();
        $adminRoleId = Role::query()->where('slug', 'admin')->firstOrFail()->id;

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/users/'.$user->id, [
            'password' => 'nuevo-password',
            'password_confirmation' => 'nuevo-password',
            'role_ids' => [$adminRoleId],
        ])->assertOk()
            ->assertJsonPath('data.roles.0.slug', 'admin');

        $this->assertTrue(
            Hash::check('nuevo-password', $user->fresh()->password)
        );
    }

    public function test_no_admin_no_puede_gestionar_usuarios(): void
    {
        Sanctum::actingAs($this->oficialia());

        $this->getJson('/api/admin/users')->assertForbidden();

        $this->postJson('/api/admin/users', [
            'name' => 'X',
            'email' => 'x@correspondencia.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_ids' => [1],
        ])->assertForbidden();
    }

    public function test_email_duplicado_es_rechazado(): void
    {
        User::factory()->create(['email' => 'duplicado@correspondencia.test']);
        $roleId = Role::query()->firstOrFail()->id;

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/users', [
            'name' => 'Otro',
            'email' => 'duplicado@correspondencia.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_ids' => [$roleId],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
