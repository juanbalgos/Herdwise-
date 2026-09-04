<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\HealthRecord;
use App\Models\LivestockBatch;
use App\Models\Reminder;
use App\Models\Species;
use App\Models\User;
use App\Models\WeightRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmReportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_farm_reports(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/export/csv')->assertRedirect('/login');
        $this->get('/reports/export/pdf')->assertRedirect('/login');
    }

    public function test_report_uses_the_selected_period_and_active_farm_only(): void
    {
        $this->travelTo('2026-09-04 10:00:00');
        [$owner, $farm, $species, $animal] = $this->farmWithAnimal('owner', 'G-REPORT');
        LivestockBatch::factory()->for($farm)->for($species)->create(['batch_code' => 'B-REPORT', 'current_count' => 24]);
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-09-02',
            'observation' => 'Visible health observation',
            'severity' => 'high',
            'recorded_by' => $owner->id,
        ]);
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-07-01',
            'observation' => 'Outside selected dates',
            'recorded_by' => $owner->id,
        ]);
        $otherFarm = Farm::factory()->create(['name' => 'Hidden Farm']);
        $otherAnimal = Animal::factory()->for($otherFarm)->for($species)->create(['animal_code' => 'HIDDEN-01']);
        HealthRecord::factory()->create([
            'farm_id' => $otherFarm->id,
            'animal_id' => $otherAnimal->id,
            'observed_on' => '2026-09-02',
            'observation' => 'Hidden health observation',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports?start_date=2026-09-01&end_date=2026-09-04');

        $response->assertOk()
            ->assertSee('Visible health observation')
            ->assertDontSee('Outside selected dates')
            ->assertDontSee('Hidden health observation')
            ->assertViewHas('inventory', fn (array $inventory): bool => $inventory['individuals'] === 1 && $inventory['batch_population'] === 24)
            ->assertViewHas('care', fn (array $care): bool => $care['health'] === 1 && $care['high_priority'] === 1);
    }

    public function test_report_rejects_an_end_date_before_the_start_date(): void
    {
        [$owner, $farm] = $this->farmWithAnimal();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from('/reports')
            ->get('/reports?start_date=2026-09-04&end_date=2026-09-03')
            ->assertRedirect('/reports')
            ->assertSessionHasErrors('end_date');
    }

    public function test_report_rejects_a_period_longer_than_366_days(): void
    {
        [$owner, $farm] = $this->farmWithAnimal();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from('/reports')
            ->get('/reports?start_date=2025-01-01&end_date=2026-01-02')
            ->assertRedirect('/reports')
            ->assertSessionHasErrors(['end_date' => 'Choose a reporting period of 366 days or less.']);
    }

    public function test_report_rejects_an_unknown_section_filter(): void
    {
        [$owner, $farm] = $this->farmWithAnimal();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from('/reports')
            ->get('/reports?section=private_notes')
            ->assertRedirect('/reports')
            ->assertSessionHasErrors('section');
    }

    public function test_report_escapes_user_entered_health_notes(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal();
        $unsafeObservation = '<script>alert("report")</script>';
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-09-02',
            'observation' => $unsafeObservation,
            'recorded_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports?start_date=2026-09-01&end_date=2026-09-04&section=care')
            ->assertOk()
            ->assertDontSee($unsafeObservation, false);
    }

    public function test_csv_export_is_filtered_scoped_and_safe_for_spreadsheets(): void
    {
        [$owner, $farm, , $animal] = $this->farmWithAnimal('owner', 'G-CSV');
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-09-02',
            'observation' => '=HYPERLINK("https://unsafe.example")',
            'recorded_by' => $owner->id,
        ]);
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-08-01',
            'observation' => 'Old entry excluded',
            'recorded_by' => $owner->id,
        ]);
        $otherFarm = Farm::factory()->create();
        $otherAnimal = Animal::factory()->for($otherFarm)->create(['animal_code' => 'OTHER-CSV']);
        HealthRecord::factory()->create([
            'farm_id' => $otherFarm->id,
            'animal_id' => $otherAnimal->id,
            'observed_on' => '2026-09-02',
            'observation' => 'Other farm entry',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports/export/csv?start_date=2026-09-01&end_date=2026-09-04&section=care');
        $content = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBFSection,", $content);
        $this->assertStringContainsString('G-CSV', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString('Old entry excluded', $content);
        $this->assertStringNotContainsString('Other farm entry', $content);
        $this->assertStringNotContainsString('Individual animal', $content);
    }

    public function test_pdf_export_returns_a_real_downloadable_pdf(): void
    {
        [$owner, $farm] = $this->farmWithAnimal();

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports/export/pdf?start_date=2026-09-01&end_date=2026-09-04');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_authorized_veterinarian_can_view_and_export_the_assigned_farm_report(): void
    {
        [$veterinarian, $farm, , $animal] = $this->farmWithAnimal('veterinarian', 'VET-REPORT');
        WeightRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'measured_on' => '2026-09-03',
            'weight_kg' => 48.75,
            'recorded_by' => $veterinarian->id,
        ]);

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports?start_date=2026-09-01&end_date=2026-09-04&section=growth')
            ->assertOk()
            ->assertSee('VET-REPORT')
            ->assertSee('48.75 kg');

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/reports/export/csv?start_date=2026-09-01&end_date=2026-09-04&section=growth')
            ->assertOk();
    }

    public function test_report_follows_the_current_farm_context_for_multi_farm_users(): void
    {
        $user = User::factory()->create();
        $firstFarm = Farm::factory()->create(['name' => 'First Farm']);
        $secondFarm = Farm::factory()->create(['name' => 'Second Farm']);
        $firstFarm->users()->attach($user, ['role' => 'veterinarian', 'is_active' => true]);
        $secondFarm->users()->attach($user, ['role' => 'veterinarian', 'is_active' => true]);
        $firstSpecies = Species::factory()->create(['name' => 'First Farm Species']);
        $secondSpecies = Species::factory()->create(['name' => 'Second Farm Species']);
        Animal::factory()->for($firstFarm)->for($firstSpecies)->create(['animal_code' => 'FIRST-001']);
        Animal::factory()->for($secondFarm)->for($secondSpecies)->create(['animal_code' => 'SECOND-001']);
        Reminder::factory()->for($secondFarm)->create(['title' => 'Second farm reminder']);

        $this->actingAs($user)
            ->withSession(['current_farm_id' => $secondFarm->id])
            ->get('/reports?section=inventory')
            ->assertOk()
            ->assertSee('Second Farm')
            ->assertSee('Second Farm Species')
            ->assertDontSee('First Farm Species')
            ->assertViewHas('inventory', fn (array $inventory): bool => $inventory['individuals'] === 1);
    }

    /** @return array{User, Farm, Species, Animal} */
    private function farmWithAnimal(string $role = 'owner', string $animalCode = 'G-001'): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);
        $animal = Animal::factory()->for($farm)->for($species)->create(['animal_code' => $animalCode]);

        return [$user, $farm, $species, $animal];
    }
}
