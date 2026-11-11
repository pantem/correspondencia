<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Titular;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $areas = [
            ['nombre' => 'Oficialía de Partes', 'siglas' => 'OFP'],
            ['nombre' => 'Dirección General', 'siglas' => 'DG'],
            ['nombre' => 'Departamento Jurídico', 'siglas' => 'DJ'],
            ['nombre' => 'Departamento de Administración', 'siglas' => 'ADM'],
        ];

        foreach ($areas as $area) {
            Area::query()->updateOrCreate(['nombre' => $area['nombre']], $area);
        }

        $cargos = ['Director', 'Coordinador', 'Asistente Administrativo', 'Titular'];

        foreach ($cargos as $nombre) {
            Cargo::query()->updateOrCreate(['nombre' => $nombre]);
        }

        $titular = [
            'nombre' => 'María González',
            'area_id' => Area::query()->where('nombre', 'Dirección General')->firstOrFail()->id,
            'cargo_id' => Cargo::query()->where('nombre', 'Director')->firstOrFail()->id,
            'desde' => now()->subYear()->toDateString(),
        ];

        Titular::query()->updateOrCreate(
            ['nombre' => $titular['nombre'], 'area_id' => $titular['area_id']],
            $titular,
        );
    }
}
