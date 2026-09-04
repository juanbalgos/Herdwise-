<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccination>
 */
class VaccinationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'vaccine_name' => fake()->word().' vaccine', 'given_on' => fake()->dateTimeBetween('-90 days'), 'dose' => '1 mL', 'recorded_by' => User::factory()];
    }
}
