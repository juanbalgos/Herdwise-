<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['farm_id' => Farm::factory(), 'type' => fake()->randomElement(['vaccination', 'deworming', 'treatment_follow_up']), 'title' => fake()->sentence(4), 'due_on' => fake()->dateTimeBetween('today', '+30 days'), 'status' => 'pending', 'priority' => 'normal', 'created_by' => User::factory()];
    }
}
