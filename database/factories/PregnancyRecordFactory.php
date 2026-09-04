<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\PregnancyRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PregnancyRecord>
 */
class PregnancyRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'animal_id' => Animal::factory(), 'checked_on' => fake()->dateTimeBetween('-60 days'), 'status' => 'confirmed', 'gestation_days' => 150, 'expected_delivery_on' => fake()->dateTimeBetween('+10 days', '+120 days'), 'is_estimated' => true, 'recorded_by' => User::factory()];
    }
}
