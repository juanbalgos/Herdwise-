<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QrIdentificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_scan_reveals_no_livestock_data_before_sign_in(): void
    {
        [, , $animal] = $this->farmMemberWithLivestock();
        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $animal]);

        $this->get($scanUrl)
            ->assertRedirect(route('login'))
            ->assertDontSee($animal->animal_code);
    }

    public function test_farm_member_can_generate_and_print_an_animal_qr_card(): void
    {
        [$user, $farm, $animal] = $this->farmMemberWithLivestock();

        $this->actingAs($user)->get(route('animals.qr-code', $animal))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false);

        $this->actingAs($user)->get(route('animals.print-card', $animal))
            ->assertOk()
            ->assertSee($animal->animal_code)
            ->assertSee($farm->name)
            ->assertSee('Farm team sign-in required')
            ->assertSee(asset('js/herdwise.js'), false)
            ->assertSee('data-print-page', false)
            ->assertDontSee('onclick=', false)
            ->assertSee(route('animals.qr-code', $animal), false);
    }

    public function test_valid_animal_scan_opens_the_authorized_profile(): void
    {
        [$user, , $animal] = $this->farmMemberWithLivestock();
        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $animal]);

        $this->actingAs($user)->get($scanUrl)
            ->assertRedirect(route('animals.show', $animal));
    }

    public function test_tampered_scan_link_is_rejected(): void
    {
        [$user, $farm, $animal, , $species] = $this->farmMemberWithLivestock();
        $otherAnimal = Animal::factory()->for($farm)->for($species)->create();
        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $animal]);
        $tamperedUrl = str_replace(
            '/identify/animals/'.$animal->getRouteKey(),
            '/identify/animals/'.$otherAnimal->getRouteKey(),
            $scanUrl,
        );

        $this->actingAs($user)->get($tamperedUrl)->assertForbidden();
    }

    public function test_other_farm_cannot_generate_print_or_open_animal_qr(): void
    {
        [$user] = $this->farmMemberWithLivestock();
        $otherAnimal = Animal::factory()->create();
        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $otherAnimal]);

        $this->actingAs($user)->get(route('animals.qr-code', $otherAnimal))->assertNotFound();
        $this->actingAs($user)->get(route('animals.print-card', $otherAnimal))->assertNotFound();
        $this->actingAs($user)->get($scanUrl)->assertNotFound();
    }

    public function test_batch_qr_card_and_signed_scan_use_the_batch_profile(): void
    {
        [$user, $farm, , $batch] = $this->farmMemberWithLivestock();

        $this->actingAs($user)->get(route('batches.qr-code', $batch))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs($user)->get(route('batches.print-card', $batch))
            ->assertOk()
            ->assertSee($batch->batch_code)
            ->assertSee($farm->name);

        $scanUrl = URL::signedRoute('identify.batches.scan', ['batch' => $batch]);
        $this->actingAs($user)->get($scanUrl)
            ->assertRedirect(route('batches.show', $batch));
    }

    /** @return array{User, Farm, Animal, LivestockBatch, Species} */
    private function farmMemberWithLivestock(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => 'owner', 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);
        $animal = Animal::factory()->for($farm)->for($species)->create([
            'animal_code' => 'G-007',
            'ear_tag' => 'ET-1007',
            'name' => 'Amihan',
        ]);
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create([
            'batch_code' => 'B-2026-007',
        ]);

        return [$user, $farm, $animal, $batch, $species];
    }
}
