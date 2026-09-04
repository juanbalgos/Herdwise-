<?php

namespace Database\Factories;

use App\Models\DewormingRecord;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DewormingRecord>
 */
class DewormingRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'product_name' => fake()->word(), 'administered_on' => fake()->dateTimeBetween('-90 days'), 'dose' => '1 mL', 'recorded_by' => User::factory()];
    }
}
