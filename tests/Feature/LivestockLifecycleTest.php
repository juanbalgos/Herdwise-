<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Breed;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\LivestockChangeLog;
use App\Models\Reminder;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivestockLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_log_factory_keeps_the_subject_and_farm_consistent(): void
    {
        $changeLog = LivestockChangeLog::factory()->create();

        $this->assertSame($changeLog->subject->farm_id, $changeLog->farm_id);
    }

    public function test_farm_member_can_correct_an_animal_and_the_change_is_audited(): void
    {
        [$staff, $farm, $species, $animal] = $this->farmWithAnimal('staff');

        $response = $this->actingAs($staff)
            ->withSession(['current_farm_id' => $farm->id])
            ->put(route('animals.update', $animal), $this->animalPayload($species, ['name' => 'Maya', 'location' => 'Recovery Pen']));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'name' => 'Maya', 'location' => 'Recovery Pen']);
        $this->assertDatabaseHas('livestock_change_logs', [
            'farm_id' => $farm->id,
            'actor_user_id' => $staff->id,
            'subject_type' => Animal::class,
            'subject_id' => $animal->id,
            'action' => 'updated',
        ]);
        $changes = $animal->changeLogs()->firstOrFail()->changes;
        $this->assertSame(['name', 'location'], array_keys($changes));
    }

    public function test_owner_can_correct_a_batch_count_and_the_change_is_audited(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create(['batch_code' => 'B-EDIT', 'initial_count' => 100, 'current_count' => 90]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->put(route('batches.update', $batch), $this->batchPayload($species, ['batch_code' => 'B-EDIT', 'initial_count' => 100, 'current_count' => 84]));

        $response->assertRedirect(route('batches.show', $batch));
        $this->assertDatabaseHas('livestock_batches', ['id' => $batch->id, 'current_count' => 84]);
        $this->assertDatabaseHas('livestock_change_logs', [
            'actor_user_id' => $owner->id,
            'subject_type' => LivestockBatch::class,
            'subject_id' => $batch->id,
            'action' => 'updated',
        ]);
    }

    public function test_duplicate_animal_code_on_the_same_farm_has_a_clear_validation_error(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('animals.create'))
            ->post(route('animals.store'), $this->animalPayload($species));

        $response->assertRedirect(route('animals.create'))
            ->assertSessionHasErrors(['animal_code' => 'This animal ID is already used on the current farm.']);
        $this->assertDatabaseCount('animals', 1);
    }

    public function test_duplicate_batch_code_on_the_same_farm_has_a_clear_validation_error(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        LivestockBatch::factory()->for($farm)->for($species)->create(['batch_code' => 'B-001']);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('batches.create'))
            ->post(route('batches.store'), $this->batchPayload($species));

        $response->assertRedirect(route('batches.create'))
            ->assertSessionHasErrors(['batch_code' => 'This batch ID is already used on the current farm.']);
        $this->assertDatabaseCount('livestock_batches', 1);
    }

    public function test_animal_breed_must_belong_to_the_selected_species(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        $otherSpecies = Species::factory()->create();
        $otherBreed = Breed::create(['species_id' => $otherSpecies->id, 'name' => 'Other breed']);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('animals.create'))
            ->post(route('animals.store'), $this->animalPayload($species, ['animal_code' => 'G-002', 'breed_id' => $otherBreed->id]));

        $response->assertRedirect(route('animals.create'))
            ->assertSessionHasErrors(['breed_id' => 'Choose a breed that belongs to the selected species.']);
        $this->assertDatabaseCount('animals', 1);
    }

    public function test_only_a_female_animal_can_have_pregnant_status(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('animals.create'))
            ->post(route('animals.store'), $this->animalPayload($species, [
                'animal_code' => 'G-002',
                'sex' => 'male',
                'status' => 'pregnant',
            ]));

        $response->assertRedirect(route('animals.create'))
            ->assertSessionHasErrors(['status' => 'Only a female animal can have pregnant status.']);
        $this->assertDatabaseCount('animals', 1);
    }

    public function test_manager_can_archive_an_animal_without_deleting_history(): void
    {
        [$manager, $farm, , $animal] = $this->farmWithAnimal('manager', ['status' => 'under_observation']);
        $reminder = Reminder::factory()->for($farm)->create(['animal_id' => $animal->id, 'created_by' => $manager->id]);

        $response = $this->actingAs($manager)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('animals.archive', $animal));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'status' => 'archived', 'status_before_archive' => 'under_observation', 'archived_by' => $manager->id]);
        $this->assertNotNull($animal->fresh()->archived_at);
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('livestock_change_logs', ['subject_type' => Animal::class, 'subject_id' => $animal->id, 'action' => 'archived']);
        $this->assertModelExists($animal);
    }

    public function test_owner_can_restore_an_animal_to_its_previous_status(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal('owner', [
            'status' => 'archived',
            'status_before_archive' => 'quarantined',
            'archived_at' => now()->subDay(),
            'archived_by' => null,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('animals.restore', $animal));

        $response->assertRedirect(route('animals.show', $animal));
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'status' => 'quarantined', 'status_before_archive' => null, 'archived_at' => null, 'archived_by' => null]);
        $this->assertDatabaseHas('livestock_change_logs', ['subject_type' => Animal::class, 'subject_id' => $animal->id, 'action' => 'restored']);
    }

    public function test_owner_can_archive_and_preserve_a_batch_record(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create(['status' => 'closed']);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('batches.archive', $batch));

        $response->assertRedirect(route('batches.show', $batch));
        $this->assertDatabaseHas('livestock_batches', ['id' => $batch->id, 'status' => 'archived', 'status_before_archive' => 'closed']);
        $this->assertDatabaseHas('livestock_change_logs', ['subject_type' => LivestockBatch::class, 'subject_id' => $batch->id, 'action' => 'archived']);
        $this->assertModelExists($batch);
    }

    public function test_owner_can_restore_a_batch_to_its_previous_status(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create([
            'status' => 'archived',
            'status_before_archive' => 'under_observation',
            'archived_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('batches.restore', $batch));

        $response->assertRedirect(route('batches.show', $batch));
        $this->assertDatabaseHas('livestock_batches', [
            'id' => $batch->id,
            'status' => 'under_observation',
            'status_before_archive' => null,
            'archived_at' => null,
            'archived_by' => null,
        ]);
        $this->assertDatabaseHas('livestock_change_logs', [
            'subject_type' => LivestockBatch::class,
            'subject_id' => $batch->id,
            'action' => 'restored',
        ]);
    }

    public function test_staff_cannot_archive_livestock(): void
    {
        [$staff, $farm, , $animal] = $this->farmWithAnimal('staff');

        $this->actingAs($staff)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('animals.archive', $animal))
            ->assertForbidden();

        $this->assertNull($animal->fresh()->archived_at);
        $this->assertDatabaseCount('livestock_change_logs', 0);
    }

    public function test_veterinarian_cannot_edit_livestock_registry_data(): void
    {
        [$veterinarian, $farm, $species, $animal] = $this->farmWithAnimal('veterinarian');

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->put(route('animals.update', $animal), $this->animalPayload($species, ['location' => 'Unauthorized change']))
            ->assertForbidden();

        $this->assertDatabaseMissing('animals', ['id' => $animal->id, 'location' => 'Unauthorized change']);
        $this->assertDatabaseCount('livestock_change_logs', 0);
    }

    public function test_member_cannot_update_another_farms_animal(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal();
        $otherAnimal = Animal::factory()->for(Farm::factory())->for($species)->create();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->put(route('animals.update', $otherAnimal), $this->animalPayload($species, ['animal_code' => $otherAnimal->animal_code]))
            ->assertNotFound();

        $this->assertDatabaseCount('livestock_change_logs', 0);
    }

    public function test_archived_animal_is_hidden_from_the_active_registry(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal('owner', ['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('animals.index'))
            ->assertOk()
            ->assertDontSee($animal->animal_code);
    }

    public function test_archived_animal_is_visible_in_the_archive_registry(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal('owner', ['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('animals.index', ['view' => 'archived']))
            ->assertOk()
            ->assertSee($animal->animal_code);
    }

    public function test_archived_animal_cannot_receive_new_care_records(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal('owner', ['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->post(route('activities.health.store'), [
                'target' => 'animal:'.$animal->id,
                'observed_on' => today()->toDateString(),
                'observation' => 'Should not be stored',
                'severity' => 'low',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('health_records', 0);
    }

    public function test_archived_livestock_is_excluded_from_the_api_registry(): void
    {
        [$owner, $farm, $species] = $this->farmWithAnimal('owner', ['status' => 'archived', 'archived_at' => now()]);
        LivestockBatch::factory()->for($farm)->for($species)->create(['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->getJson(route('api.v1.animals.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->getJson(route('api.v1.batches.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @return array{User, Farm, Species, Animal} */
    private function farmWithAnimal(string $role = 'owner', array $animalAttributes = []): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);
        $animal = Animal::factory()->for($farm)->for($species)->create($animalAttributes + ['animal_code' => 'G-001', 'sex' => 'female']);

        return [$user, $farm, $species, $animal];
    }

    /** @return array<string, mixed> */
    private function animalPayload(Species $species, array $overrides = []): array
    {
        return $overrides + [
            'animal_code' => 'G-001',
            'species_id' => $species->id,
            'sex' => 'female',
            'status' => 'healthy',
        ];
    }

    /** @return array<string, mixed> */
    private function batchPayload(Species $species, array $overrides = []): array
    {
        return $overrides + [
            'batch_code' => 'B-001',
            'species_id' => $species->id,
            'started_on' => today()->toDateString(),
            'initial_count' => 100,
            'current_count' => 90,
            'status' => 'active',
        ];
    }
}
