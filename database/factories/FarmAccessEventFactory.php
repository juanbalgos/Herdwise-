<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmAccessEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmAccessEvent>
 */
class FarmAccessEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'subject_user_id' => User::factory(),
            'actor_user_id' => User::factory(),
            'event_type' => 'veterinarian_invited',
            'metadata' => [],
        ];
    }
}
