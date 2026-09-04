<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LivestockBatch>
 */
class LivestockBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initialCount = fake()->numberBetween(20, 500);

        return ['farm_id' => Farm::factory(), 'species_id' => Species::factory(), 'batch_code' => fake()->unique()->bothify('B-####-###'), 'started_on' => fake()->dateTimeBetween('-6 months'), 'initial_count' => $initialCount, 'current_count' => fake()->numberBetween(1, $initialCount), 'status' => 'active'];
    }
}
