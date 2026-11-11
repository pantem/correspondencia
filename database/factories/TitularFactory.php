<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Titular;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Titular>
 */
class TitularFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'area_id' => Area::factory(),
            'cargo_id' => Cargo::factory(),
            'desde' => now()->subYear()->toDateString(),
            'hasta' => null,
            'activo' => true,
        ];
    }
}
