<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'treated_on' => fake()->dateTimeBetween('-30 days'), 'reason' => fake()->sentence(4), 'medication' => fake()->word(), 'recorded_by' => User::factory()];
    }
}
