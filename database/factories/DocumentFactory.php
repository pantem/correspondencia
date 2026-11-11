<?php

namespace Database\Factories;

use App\Enums\EstadoDocumento;
use App\Enums\Prioridad;
use App\Enums\SentidoCorrespondencia;
use App\Enums\TipoCorrespondencia;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folio' => sprintf('%04d-%d', fake()->unique()->numberBetween(1, 9999), now()->year),
            'anio' => now()->year,
            'sentido' => SentidoCorrespondencia::Recibida,
            'tipo_correspondencia' => TipoCorrespondencia::Oficio,
            'fecha_documento' => now()->subDays(3)->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
            'oficio_original' => 'OF-'.fake()->unique()->numberBetween(1, 99999),
            'remitente' => fake()->name(),
            'dependencia' => fake()->company(),
            'cargo_remitente' => fake()->jobTitle(),
            'asunto' => fake()->sentence(6),
            'resumen' => fake()->paragraph(),
            'observaciones' => null,
            'anexos' => null,
            'canal_recepcion' => 'presencial',
            'prioridad' => Prioridad::Normal,
            'plazo' => null,
            'estado' => EstadoDocumento::Registrado,
            'area_id' => null,
            'datos_extra' => null,
            'user_id' => User::factory(),
        ];
    }
}
