<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@correspondencia.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'activo' => true,
            ],
        );
        $admin->roles()->sync(Role::query()->where('slug', 'admin')->pluck('id'));

        $oficialia = User::query()->updateOrCreate(
            ['email' => 'oficialia@correspondencia.test'],
            [
                'name' => 'Oficialía de Partes',
                'password' => Hash::make('password'),
                'activo' => true,
            ],
        );
        $oficialia->roles()->sync(Role::query()->where('slug', 'oficialia')->pluck('id'));
    }
}
