<?php

namespace Database\Factories;

use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['name' => ucfirst($name), 'code' => strtoupper($name), 'supports_individuals' => true, 'supports_batches' => true, 'default_gestation_days' => 150];
    }
}
