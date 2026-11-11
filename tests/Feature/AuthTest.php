<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_iniciar_sesion_y_recibe_token(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'oficialia@correspondencia.test',
            'password' => 'secret-password',
        ]);
        $user->roles()->attach(
            Role::query()->where('slug', 'oficialia')->firstOrFail()
        );

        $response = $this->postJson('/api/auth/login', [
            'email' => 'oficialia@correspondencia.test',
            'password' => 'secret-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'roles']]])
            ->assertJsonPath('data.user.email', 'oficialia@correspondencia.test');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_rechaza_password_incorrecto(): void
    {
        User::factory()->create([
            'email' => 'user@correspondencia.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@correspondencia.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rechaza_usuario_inactivo(): void
    {
        User::factory()->create([
            'email' => 'inactivo@correspondencia.test',
            'password' => 'secret-password',
            'activo' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactivo@correspondencia.test',
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_aplica_rate_limit(): void
    {
        $user = User::factory()->create([
            'email' => 'limite@correspondencia.test',
            'password' => 'secret-password',
        ]);

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/auth/login', [
                'email' => 'limite@correspondencia.test',
                'password' => 'wrong-'.$i,
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'limite@correspondencia.test',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_me_devuelve_usuario_con_permisos(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('slug', 'oficialia')->firstOrFail()
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data' => ['permissions' => []]]);
    }

    public function test_logout_revoca_el_token(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_rutas_protegidas_requieren_autenticacion(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
