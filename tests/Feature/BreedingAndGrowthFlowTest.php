<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\BreedingRecord;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\PregnancyRecord;
use App\Models\Species;
use App\Models\User;
use App\Models\WeightRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreedingAndGrowthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_breeding_event_schedules_a_farm_scoped_pregnancy_check(): void
    {
        [$user,$farm,$female,$species] = $this->farmWithFemale();
        $male = Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'G-002', 'sex' => 'male']);
        $this->actingAs($user)->post('/reproduction/breeding', ['female_animal_id' => $female->id, 'male_animal_id' => $male->id, 'occurred_on' => today()->toDateString(), 'method' => 'natural', 'pregnancy_check_due_on' => today()->addDays(30)->toDateString()])->assertRedirect('/reproduction');
        $this->assertDatabaseHas('breeding_records', ['farm_id' => $farm->id, 'female_animal_id' => $female->id, 'male_animal_id' => $male->id]);
        $this->assertDatabaseHas('reminders', ['farm_id' => $farm->id, 'animal_id' => $female->id, 'type' => 'pregnancy_check']);
    }

    public function test_confirmed_pregnancy_calculates_delivery_and_updates_animal(): void
    {
        [$user,$farm,$female] = $this->farmWithFemale();
        $breeding = BreedingRecord::factory()->create(['farm_id' => $farm->id, 'female_animal_id' => $female->id, 'occurred_on' => today()->subDays(20), 'recorded_by' => $user->id]);
        $this->actingAs($user)->post('/reproduction/pregnancies', ['animal_id' => $female->id, 'breeding_record_id' => $breeding->id, 'checked_on' => today()->toDateString(), 'status' => 'confirmed'])->assertRedirect('/reproduction');
        $pregnancy = PregnancyRecord::firstOrFail();
        $this->assertSame(today()->addDays(80)->toDateString(), $pregnancy->expected_delivery_on->toDateString());
        $this->assertTrue($pregnancy->is_estimated);
        $this->assertDatabaseHas('animals', ['id' => $female->id, 'status' => 'pregnant']);
        $this->assertSame(today()->addDays(80)->toDateString(), $pregnancy->reminder->due_on->toDateString());
    }

    public function test_staff_cannot_record_a_pregnancy_check(): void
    {
        [$staff,,$female] = $this->farmWithFemale('staff');
        $this->actingAs($staff)->post('/reproduction/pregnancies', ['animal_id' => $female->id, 'checked_on' => today()->toDateString(), 'status' => 'uncertain'])->assertForbidden();
        $this->assertDatabaseCount('pregnancy_records', 0);
    }

    public function test_birth_counts_must_reconcile(): void
    {
        [$user,,$female] = $this->farmWithFemale();
        $this->actingAs($user)->from('/reproduction/create/birth')->post('/reproduction/births', ['animal_id' => $female->id, 'delivered_on' => today()->toDateString(), 'outcome' => 'mixed', 'offspring_count' => 3, 'live_count' => 1, 'stillborn_count' => 1])->assertRedirect('/reproduction/create/birth')->assertSessionHasErrors('offspring_count');
        $this->assertDatabaseCount('birth_records', 0);
    }

    public function test_birth_outcome_must_match_the_recorded_counts(): void
    {
        [$user,,$female] = $this->farmWithFemale();
        $this->actingAs($user)->from('/reproduction/create/birth')->post('/reproduction/births', ['animal_id' => $female->id, 'delivered_on' => today()->toDateString(), 'outcome' => 'live_birth', 'offspring_count' => 2, 'live_count' => 1, 'stillborn_count' => 1])->assertRedirect('/reproduction/create/birth')->assertSessionHasErrors('outcome');
        $this->assertDatabaseCount('birth_records', 0);
    }

    public function test_known_offspring_sex_counts_cannot_exceed_live_births(): void
    {
        [$user,,$female] = $this->farmWithFemale();
        $this->actingAs($user)->from('/reproduction/create/birth')->post('/reproduction/births', ['animal_id' => $female->id, 'delivered_on' => today()->toDateString(), 'outcome' => 'live_birth', 'offspring_count' => 2, 'live_count' => 2, 'stillborn_count' => 0, 'male_count' => 3])->assertRedirect('/reproduction/create/birth')->assertSessionHasErrors('male_count');
        $this->assertDatabaseCount('birth_records', 0);
    }

    public function test_birth_completes_expected_birth_reminder_and_updates_mother(): void
    {
        [$user,$farm,$female] = $this->farmWithFemale();
        $female->update(['status' => 'pregnant']);
        $pregnancy = PregnancyRecord::factory()->create(['farm_id' => $farm->id, 'animal_id' => $female->id, 'recorded_by' => $user->id]);
        $reminder = $pregnancy->reminder()->create(['farm_id' => $farm->id, 'animal_id' => $female->id, 'type' => 'expected_birth', 'title' => 'Expected birth: G-001', 'due_on' => today(), 'status' => 'pending', 'created_by' => $user->id]);
        $this->actingAs($user)->post('/reproduction/births', ['animal_id' => $female->id, 'pregnancy_record_id' => $pregnancy->id, 'delivered_on' => today()->toDateString(), 'outcome' => 'live_birth', 'offspring_count' => 2, 'live_count' => 2, 'stillborn_count' => 0])->assertRedirect('/reproduction');
        $this->assertDatabaseHas('birth_records', ['pregnancy_record_id' => $pregnancy->id, 'live_count' => 2]);
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => 'completed']);
        $this->assertDatabaseHas('animals', ['id' => $female->id, 'status' => 'healthy']);
    }

    public function test_batch_weight_requires_a_sample_size(): void
    {
        [$user,$farm,,$species] = $this->farmWithFemale();
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create();
        $this->actingAs($user)->from('/weights/create')->post('/weights', ['target' => 'batch:'.$batch->id, 'measured_on' => today()->toDateString(), 'weight_kg' => 18.4])->assertRedirect('/weights/create')->assertSessionHasErrors('sample_size');
        $this->assertDatabaseCount('weight_records', 0);
    }

    public function test_weight_record_creates_next_check_and_api_is_farm_scoped(): void
    {
        [$user,$farm,$female] = $this->farmWithFemale();
        $otherAnimal = Animal::factory()->create();
        WeightRecord::factory()->create(['farm_id' => $otherAnimal->farm_id, 'animal_id' => $otherAnimal->id]);
        $this->actingAs($user)->post('/weights', ['target' => 'animal:'.$female->id, 'measured_on' => today()->toDateString(), 'weight_kg' => 38.25, 'next_check_on' => today()->addDays(14)->toDateString()])->assertRedirect('/weights');
        $this->assertDatabaseHas('weight_records', ['farm_id' => $farm->id, 'animal_id' => $female->id, 'weight_kg' => 38.25]);
        $this->assertDatabaseHas('reminders', ['farm_id' => $farm->id, 'type' => 'weight_check']);
        $this->actingAs($user)->getJson('/api/v1/weights')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.weight_kg', 38.25);
    }

    public function test_other_farms_animal_cannot_receive_a_weight_record(): void
    {
        [$user] = $this->farmWithFemale();
        $otherAnimal = Animal::factory()->create();
        $this->actingAs($user)->post('/weights', ['target' => 'animal:'.$otherAnimal->id, 'measured_on' => today()->toDateString(), 'weight_kg' => 10])->assertNotFound();
        $this->assertDatabaseMissing('weight_records', ['animal_id' => $otherAnimal->id, 'weight_kg' => 10]);
    }

    /** @return array{User,Farm,Animal,Species} */
    private function farmWithFemale(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT', 'default_gestation_days' => 100]);
        $female = Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'G-001', 'sex' => 'female']);

        return [$user, $farm, $female, $species];
    }
}
