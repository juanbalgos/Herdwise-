<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\LivestockBatch;
use App\Models\LivestockChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LivestockChangeLog>
 */
class LivestockChangeLogFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (LivestockChangeLog $changeLog): void {
            if ($changeLog->subject_type === Animal::class) {
                $changeLog->farm_id = Animal::query()->findOrFail($changeLog->subject_id)->farm_id;
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => null,
            'actor_user_id' => User::factory(),
            'subject_type' => Animal::class,
            'subject_id' => Animal::factory(),
            'action' => 'updated',
            'changes' => ['location' => ['from' => 'North Pen', 'to' => 'South Pen']],
        ];
    }

    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (): array => ['farm_id' => $animal->farm_id, 'subject_type' => Animal::class, 'subject_id' => $animal->id]);
    }

    public function forBatch(LivestockBatch $batch): static
    {
        return $this->state(fn (): array => ['farm_id' => $batch->farm_id, 'subject_type' => LivestockBatch::class, 'subject_id' => $batch->id]);
    }
}
