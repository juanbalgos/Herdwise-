<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmInvitation>
 */
class FarmInvitationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'role' => 'veterinarian',
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => now()->addDays(7),
            'invited_by_user_id' => User::factory(),
        ];
    }
}
