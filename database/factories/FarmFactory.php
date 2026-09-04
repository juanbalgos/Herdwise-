<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Farm';

        return ['name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'), 'location' => fake()->city(), 'timezone' => 'Asia/Manila'];
    }
}
