<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\User;
use App\Models\WeightRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeightRecord>
 */
class WeightRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'measured_on' => fake()->dateTimeBetween('-90 days'), 'weight_kg' => fake()->randomFloat(2, 1, 800), 'measurement_method' => 'Livestock scale', 'recorded_by' => User::factory()];
    }
}
