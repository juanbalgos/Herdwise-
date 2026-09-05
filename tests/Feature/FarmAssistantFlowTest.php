<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\HealthRecord;
use App\Models\LivestockBatch;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmAssistantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_record_assistant(): void
    {
        $this->get('/assistant')->assertRedirect('/login');
        $this->post('/assistant', ['question' => 'What needs attention?'])->assertRedirect('/login');
    }

    public function test_authorized_veterinarian_can_view_the_selected_farm_snapshot(): void
    {
        [$veterinarian, $farm, $species] = $this->farmMember('veterinarian');
        Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'VET-A01']);
        LivestockBatch::factory()->for($farm)->for($species)->create([
            'batch_code' => 'VET-B01',
            'current_count' => 24,
        ]);

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/assistant')
            ->assertOk()
            ->assertSee('Ask HerdWise about '.$farm->name)
            ->assertSee('24')
            ->assertSee('Veterinary safety boundary');
    }

    public function test_local_assistant_uses_only_the_current_farm_and_escapes_record_content(): void
    {
        $this->travelTo('2026-09-05 09:00:00');
        [$owner, $farm, $species] = $this->farmMember();
        $animal = Animal::factory()->for($farm)->for($species)->create([
            'animal_code' => 'GOAT-ATTN',
            'status' => 'sick',
        ]);
        $unsafeObservation = '<script>alert("assistant")</script> Reduced appetite';
        HealthRecord::factory()->create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'observed_on' => '2026-09-05',
            'observation' => $unsafeObservation,
            'severity' => 'high',
            'recorded_by' => $owner->id,
        ]);
        Animal::factory()->for($farm)->for($species)->create([
            'animal_code' => 'ARCHIVED-ATTN',
            'status' => 'archived',
            'status_before_archive' => 'sick',
            'archived_at' => now(),
            'archived_by' => $owner->id,
        ]);
        $otherFarm = Farm::factory()->create(['name' => 'Hidden Valley Farm']);
        $otherAnimal = Animal::factory()->for($otherFarm)->for($species)->create([
            'animal_code' => 'OTHER-ATTN',
            'status' => 'sick',
        ]);
        HealthRecord::factory()->create([
            'farm_id' => $otherFarm->id,
            'animal_id' => $otherAnimal->id,
            'observation' => 'Secret other farm observation',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/assistant', ['question' => 'Which animals need health attention?']);

        $response->assertOk()
            ->assertSee('GOAT-ATTN')
            ->assertSee('1 active individual animals are currently marked as needing attention.')
            ->assertSee('not a veterinary diagnosis')
            ->assertDontSee('ARCHIVED-ATTN')
            ->assertDontSee('OTHER-ATTN')
            ->assertDontSee('Secret other farm observation')
            ->assertDontSee($unsafeObservation, false)
            ->assertViewHas('result', fn (array $result): bool => $result['mode'] === 'local');
    }

    public function test_assistant_follows_the_selected_farm_for_a_multi_farm_user(): void
    {
        $user = User::factory()->create();
        $firstFarm = Farm::factory()->create(['name' => 'First Farm']);
        $secondFarm = Farm::factory()->create(['name' => 'Second Farm']);
        $firstFarm->users()->attach($user, ['role' => 'veterinarian', 'is_active' => true]);
        $secondFarm->users()->attach($user, ['role' => 'veterinarian', 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);
        Animal::factory()->for($firstFarm)->for($species)->create(['animal_code' => 'FIRST-A01']);
        Animal::factory()->for($secondFarm)->for($species)->count(2)->create();

        $this->actingAs($user)
            ->withSession(['current_farm_id' => $secondFarm->id])
            ->post('/assistant', ['question' => 'Summarize the inventory'])
            ->assertOk()
            ->assertSee('Current inventory: 2 individual animals')
            ->assertDontSee('FIRST-A01');
    }

    public function test_assistant_requires_a_question_within_the_character_limit(): void
    {
        [$owner, $farm] = $this->farmMember();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from('/assistant')
            ->post('/assistant', [])
            ->assertRedirect('/assistant')
            ->assertSessionHasErrors(['question' => 'Enter a question about this farm\'s records.']);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from('/assistant')
            ->post('/assistant', ['question' => Str::repeat('x', 801)])
            ->assertRedirect('/assistant')
            ->assertSessionHasErrors(['question' => 'Keep the question to 800 characters or less.']);
    }

    public function test_openai_provider_receives_a_private_bounded_farm_context_and_renders_escaped_output(): void
    {
        config([
            'herdwise.assistant.provider' => 'openai',
            'herdwise.assistant.openai.api_key' => 'test-api-key',
            'herdwise.assistant.openai.model' => 'gpt-5.6-luna',
        ]);
        Http::preventStrayRequests();
        $unsafeAnswer = '<script>alert("live")</script> Review GOAT-LIVE.';
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => $unsafeAnswer]],
                ]],
            ]),
        ]);
        [$owner, $farm, $species] = $this->farmMember();
        Animal::factory()->for($farm)->for($species)->create([
            'animal_code' => 'GOAT-LIVE',
            'status' => 'sick',
        ]);
        $otherFarm = Farm::factory()->create(['name' => 'Private Other Farm']);
        Animal::factory()->for($otherFarm)->for($species)->create(['animal_code' => 'OTHER-PRIVATE']);

        $response = $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/assistant', ['question' => 'Summarize the inventory.']);

        $response->assertOk()
            ->assertSee('LIVE AI · FARM RECORDS')
            ->assertSee('Review GOAT-LIVE.')
            ->assertDontSee($unsafeAnswer, false)
            ->assertViewHas('result', fn (array $result): bool => $result['mode'] === 'openai');
        Http::assertSent(function (Request $request) use ($owner): bool {
            $data = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $data['model'] === 'gpt-5.6-luna'
                && $data['store'] === false
                && $data['max_output_tokens'] === 700
                && $data['safety_identifier'] === hash('sha256', (string) $owner->id)
                && str_contains($data['instructions'], 'Never diagnose an animal')
                && str_contains($data['input'], 'GOAT-LIVE')
                && ! str_contains($data['input'], 'OTHER-PRIVATE')
                && ! str_contains($data['input'], $owner->email);
        });
    }

    public function test_live_provider_failure_falls_back_to_local_analysis(): void
    {
        config([
            'herdwise.assistant.provider' => 'openai',
            'herdwise.assistant.openai.api_key' => 'test-api-key',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Unavailable']], 503),
        ]);
        [$owner, $farm, $species] = $this->farmMember();
        Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'GOAT-FALLBACK']);

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/assistant', ['question' => 'Summarize the inventory.'])
            ->assertOk()
            ->assertSee('Live AI is temporarily unavailable')
            ->assertSee('Current inventory: 1 individual animals')
            ->assertViewHas('result', fn (array $result): bool => $result['mode'] === 'local');
        Http::assertSentCount(1);
    }

    public function test_openai_mode_without_a_key_uses_local_analysis_without_an_external_request(): void
    {
        config([
            'herdwise.assistant.provider' => 'openai',
            'herdwise.assistant.openai.api_key' => null,
        ]);
        Http::preventStrayRequests();
        [$owner, $farm] = $this->farmMember();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/assistant', ['question' => 'Give me an overview.'])
            ->assertOk()
            ->assertSee('Live AI is not configured')
            ->assertViewHas('result', fn (array $result): bool => $result['mode'] === 'local');
        Http::assertNothingSent();
    }

    /** @return array{User, Farm, Species} */
    private function farmMember(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['name' => 'Green Pastures Farm']);
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => 'GOAT']);

        return [$user, $farm, $species];
    }
}
