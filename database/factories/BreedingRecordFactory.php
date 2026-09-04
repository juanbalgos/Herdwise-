<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\BreedingRecord;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreedingRecord>
 */
class BreedingRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'female_animal_id' => Animal::factory(), 'occurred_on' => fake()->dateTimeBetween('-90 days'), 'method' => fake()->randomElement(['natural', 'artificial_insemination']), 'recorded_by' => User::factory()];
    }
}
