<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareActivityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_member_records_an_observation_and_updates_the_animal_status(): void
    {
        [$user, $farm, $animal] = $this->farmWithAnimal();

        $response = $this->actingAs($user)->post('/activities/health', [
            'target' => 'animal:'.$animal->id,
            'observed_on' => today()->toDateString(),
            'observation' => 'Reduced appetite during morning feeding.',
            'severity' => 'moderate',
            'resulting_status' => 'under_observation',
        ]);

        $response->assertRedirect('/activities');
        $this->assertDatabaseHas('health_records', ['farm_id' => $farm->id, 'animal_id' => $animal->id, 'severity' => 'moderate']);
        $this->assertDatabaseHas('animals', ['id' => $animal->id, 'status' => 'under_observation']);
    }

    public function test_next_vaccination_date_creates_a_traceable_reminder(): void
    {
        [$user, $farm, $animal] = $this->farmWithAnimal();

        $this->actingAs($user)->post('/activities/vaccinations', [
            'target' => 'animal:'.$animal->id,
            'vaccine_name' => 'Clostridial vaccine',
            'given_on' => today()->toDateString(),
            'next_due_on' => today()->addMonths(6)->toDateString(),
        ])->assertRedirect('/activities');

        $this->assertDatabaseHas('vaccinations', ['farm_id' => $farm->id, 'animal_id' => $animal->id, 'vaccine_name' => 'Clostridial vaccine']);
        $this->assertDatabaseHas('reminders', ['farm_id' => $farm->id, 'animal_id' => $animal->id, 'type' => 'vaccination', 'status' => 'pending']);
        $this->assertNotNull(Reminder::firstOrFail()->source);
    }

    public function test_member_cannot_record_care_for_another_farms_animal(): void
    {
        [$user] = $this->farmWithAnimal();
        $otherAnimal = Animal::factory()->create();

        $this->actingAs($user)->post('/activities/health', [
            'target' => 'animal:'.$otherAnimal->id,
            'observed_on' => today()->toDateString(),
            'observation' => 'Should not be visible.',
            'severity' => 'low',
        ])->assertNotFound();

        $this->assertDatabaseMissing('health_records', ['animal_id' => $otherAnimal->id]);
    }

    public function test_staff_cannot_create_treatment_records(): void
    {
        [$staff, , $animal] = $this->farmWithAnimal('staff');

        $this->actingAs($staff)->post('/activities/treatments', [
            'target' => 'animal:'.$animal->id,
            'treated_on' => today()->toDateString(),
            'reason' => 'Supportive care',
        ])->assertForbidden();

        $this->assertDatabaseCount('treatments', 0);
    }

    public function test_only_veterinarian_can_enter_a_formal_diagnosis(): void
    {
        [$owner, , $animal] = $this->farmWithAnimal();

        $this->actingAs($owner)->post('/activities/treatments', [
            'target' => 'animal:'.$animal->id,
            'treated_on' => today()->toDateString(),
            'reason' => 'Respiratory signs',
            'diagnosis' => 'Formal diagnosis',
        ])->assertForbidden();

        $this->assertDatabaseCount('treatments', 0);
    }

    public function test_invalid_next_due_date_returns_a_clear_validation_error(): void
    {
        [$user, , $animal] = $this->farmWithAnimal();

        $this->actingAs($user)->from('/activities/create/deworming')->post('/activities/deworming', [
            'target' => 'animal:'.$animal->id,
            'product_name' => 'Dewormer',
            'administered_on' => today()->toDateString(),
            'next_due_on' => today()->subDay()->toDateString(),
        ])->assertRedirect('/activities/create/deworming')->assertSessionHasErrors('next_due_on');

        $this->assertDatabaseCount('deworming_records', 0);
    }

    public function test_reminder_from_another_farm_is_not_exposed_when_completing(): void
    {
        [$user] = $this->farmWithAnimal();
        $reminder = Reminder::factory()->create();

        $this->actingAs($user)->patch('/reminders/'.$reminder->id.'/complete')->assertNotFound();
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => 'pending']);
    }

    public function test_care_api_returns_only_the_signed_in_farms_records(): void
    {
        [$user, , $animal] = $this->farmWithAnimal();
        $otherAnimal = Animal::factory()->create();
        Reminder::factory()->create(['farm_id' => $animal->farm_id, 'animal_id' => $animal->id, 'title' => 'Visible reminder']);
        Reminder::factory()->create(['farm_id' => $otherAnimal->farm_id, 'animal_id' => $otherAnimal->id, 'title' => 'Hidden reminder']);

        $response = $this->actingAs($user)->getJson('/api/v1/reminders');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Visible reminder');
    }

    /** @return array{User, Farm, Animal} */
    private function farmWithAnimal(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);
        $animal = Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'G-001']);

        return [$user, $farm, $animal];
    }
}
