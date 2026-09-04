<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'species_id' => Species::factory(), 'animal_code' => fake()->unique()->bothify('A-###'), 'sex' => fake()->randomElement(['male', 'female']), 'status' => 'healthy', 'location' => fake()->randomElement(['North Pen', 'South Pen', 'Pasture A'])];
    }
}
