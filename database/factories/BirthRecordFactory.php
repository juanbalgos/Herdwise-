<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\BirthRecord;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BirthRecord>
 */
class BirthRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $offspring = fake()->numberBetween(1, 4);

        return ['farm_id' => Farm::factory(), 'animal_id' => Animal::factory(), 'delivered_on' => fake()->dateTimeBetween('-60 days'), 'outcome' => 'live_birth', 'offspring_count' => $offspring, 'live_count' => $offspring, 'stillborn_count' => 0, 'recorded_by' => User::factory()];
    }
}
