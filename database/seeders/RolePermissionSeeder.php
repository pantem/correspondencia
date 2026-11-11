<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Permisos y perfiles autorizados (RF-59).
     *
     * @var array<int, array{name: string, slug: string}>
     */
    private const PERMISOS = [
        ['name' => 'Consultar correspondencia', 'slug' => 'documents.view'],
        ['name' => 'Registrar correspondencia', 'slug' => 'documents.create'],
        ['name' => 'Modificar correspondencia', 'slug' => 'documents.update'],
        ['name' => 'Cargar archivos digitales', 'slug' => 'documents.upload'],
        ['name' => 'Turnar correspondencia', 'slug' => 'documents.turn'],
        ['name' => 'Gestionar turnos (reasignar/cancelar)', 'slug' => 'turns.update'],
        ['name' => 'Atender turnos (seguimiento/resolución)', 'slug' => 'turns.respond'],
        ['name' => 'Administrar catálogos institucionales', 'slug' => 'admin.catalogs'],
        ['name' => 'Administrar usuarios y permisos', 'slug' => 'admin.users'],
        ['name' => 'Consultar bitácora', 'slug' => 'audit.view'],
    ];

    /**
     * @var array<int, array{name: string, slug: string, permisos: array<int, string>}>
     */
    private const ROLES = [
        [
            'name' => 'Administrador',
            'slug' => 'admin',
            'permisos' => [
                'documents.view', 'documents.create', 'documents.update', 'documents.upload',
                'documents.turn', 'turns.update', 'turns.respond',
                'admin.catalogs', 'admin.users', 'audit.view',
            ],
        ],
        [
            'name' => 'Oficialía de Partes',
            'slug' => 'oficialia',
            'permisos' => [
                'documents.view', 'documents.create', 'documents.update', 'documents.upload',
                'documents.turn', 'turns.update', 'turns.respond',
            ],
        ],
        [
            'name' => 'Personal de áreas',
            'slug' => 'area',
            'permisos' => [
                'documents.view', 'documents.upload',
                'documents.turn', 'turns.update', 'turns.respond',
            ],
        ],
        [
            'name' => 'Responsable de atención',
            'slug' => 'responsable',
            'permisos' => ['documents.view', 'documents.upload', 'turns.respond'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::query()->updateOrCreate(['slug' => $permiso['slug']], $permiso);
        }

        foreach (self::ROLES as $rol) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $rol['slug']],
                ['name' => $rol['name']],
            );

            $permisoIds = Permission::query()
                ->whereIn('slug', $rol['permisos'])
                ->pluck('id');

            $role->permissions()->sync($permisoIds);
        }
    }
}
