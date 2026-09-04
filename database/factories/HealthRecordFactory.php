<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecord>
 */
class HealthRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'observed_on' => fake()->dateTimeBetween('-30 days'), 'observation' => fake()->sentence(), 'severity' => fake()->randomElement(['low', 'moderate', 'high']), 'recorded_by' => User::factory()];
    }
}
