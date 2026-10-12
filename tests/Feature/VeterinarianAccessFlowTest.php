<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\FarmInvitation;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VeterinarianAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_private_veterinarian_invitation(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        Str::createRandomStringsUsing(fn (): string => str_repeat('a', 64));

        try {
            $response = $this->actingAs($owner)
                ->withSession(['current_farm_id' => $farm->id])
                ->post('/team/invitations', ['email' => 'vet@example.com', 'expires_in_days' => 7]);
        } finally {
            Str::createRandomStringsNormally();
        }

        $response->assertRedirect('/team')
            ->assertSessionHas('invitation_link', route('invitations.show', ['token' => str_repeat('a', 64)]));
        $this->assertDatabaseHas('farm_invitations', [
            'farm_id' => $farm->id,
            'email' => 'vet@example.com',
            'role' => 'veterinarian',
            'token_hash' => hash('sha256', str_repeat('a', 64)),
        ]);
        $this->assertDatabaseHas('farm_access_events', [
            'farm_id' => $farm->id,
            'event_type' => 'veterinarian_invited',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_invited_veterinarian_can_register_and_accept_farm_access(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        $token = str_repeat('b', 64);
        $invitation = FarmInvitation::factory()->for($farm)->create([
            'email' => 'doctor@example.com',
            'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $owner->id,
        ]);

        $this->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee($farm->name)
            ->assertSee('Create veterinarian account');

        $this->post('/register/veterinarian', [
            'name' => 'Dr. Maria Santos',
            'email' => 'doctor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $token,
        ])->assertRedirect(route('invitations.show', ['token' => $token]));

        $veterinarian = User::where('email', 'doctor@example.com')->firstOrFail();
        $this->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('current_farm_id', $farm->id);
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $veterinarian->id,
            'role' => 'veterinarian',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('farm_invitations', [
            'id' => $invitation->id,
            'accepted_by_user_id' => $veterinarian->id,
        ]);
        $this->assertDatabaseHas('farm_access_events', [
            'farm_id' => $farm->id,
            'subject_user_id' => $veterinarian->id,
            'event_type' => 'veterinarian_access_accepted',
        ]);
    }

    public function test_only_the_invited_email_can_view_or_accept_an_invitation(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $token = str_repeat('c', 64);
        FarmInvitation::factory()->for($farm)->create([
            'email' => 'doctor@example.com',
            'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser)->get(route('invitations.show', ['token' => $token]))->assertForbidden();
        $this->actingAs($otherUser)->post(route('invitations.accept', ['token' => $token]))->assertForbidden();
        $this->assertDatabaseMissing('farm_users', ['farm_id' => $farm->id, 'user_id' => $otherUser->id]);
    }

    public function test_expired_veterinarian_invitation_cannot_be_used(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        $veterinarian = User::factory()->create(['email' => 'doctor@example.com']);
        $token = str_repeat('d', 64);
        FarmInvitation::factory()->for($farm)->create([
            'email' => $veterinarian->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
            'invited_by_user_id' => $owner->id,
        ]);

        $this->get(route('invitations.show', ['token' => $token]))->assertStatus(410);
        $this->actingAs($veterinarian)->post(route('invitations.accept', ['token' => $token]))->assertStatus(410);
        $this->assertDatabaseMissing('farm_users', ['farm_id' => $farm->id, 'user_id' => $veterinarian->id]);
    }

    public function test_qr_scan_switches_a_veterinarian_to_the_authorized_farm_and_is_audited(): void
    {
        [$veterinarian, $firstFarm] = $this->farmWithMember('veterinarian');
        $secondFarm = Farm::factory()->create();
        $secondFarm->users()->attach($veterinarian, ['role' => 'veterinarian', 'is_active' => true]);
        $species = Species::factory()->create();
        $animal = Animal::factory()->for($secondFarm)->for($species)->create(['animal_code' => 'G-222']);
        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $animal]);

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $firstFarm->id])
            ->get($scanUrl)
            ->assertRedirect(route('animals.show', $animal))
            ->assertSessionHas('current_farm_id', $secondFarm->id);

        $this->getJson('/api/v1/animals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.animal_code', 'G-222');
        $this->assertDatabaseHas('farm_access_events', [
            'farm_id' => $secondFarm->id,
            'subject_user_id' => $veterinarian->id,
            'event_type' => 'qr_scanned',
            'livestock_type' => 'animal',
            'livestock_id' => $animal->id,
        ]);
    }

    #[DataProvider('veterinarianRestrictedRoutes')]
    public function test_veterinarian_is_forbidden_from_farm_management_actions(string $method, string $uri): void
    {
        [$veterinarian, $farm] = $this->farmWithMember('veterinarian');

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->call($method, $uri)
            ->assertForbidden();
    }

    public static function veterinarianRestrictedRoutes(): array
    {
        return [
            'create animal page' => ['GET', '/animals/create'],
            'create animal record' => ['POST', '/animals'],
            'create batch page' => ['GET', '/batches/create'],
            'create batch record' => ['POST', '/batches'],
            'record weight page' => ['GET', '/weights/create'],
            'record weight entry' => ['POST', '/weights'],
            'record breeding page' => ['GET', '/reproduction/create/breeding'],
            'record breeding entry' => ['POST', '/reproduction/breeding'],
            'record birth page' => ['GET', '/reproduction/create/birth'],
            'record birth entry' => ['POST', '/reproduction/births'],
            'manage farm team' => ['GET', '/team'],
            'invite team member' => ['POST', '/team/invitations'],
        ];
    }

    public function test_authorized_veterinarian_can_record_a_formal_diagnosis(): void
    {
        [$veterinarian, $farm] = $this->farmWithMember('veterinarian');
        $species = Species::factory()->create();
        $animal = Animal::factory()->for($farm)->for($species)->create();

        $this->actingAs($veterinarian)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/activities/treatments', [
                'target' => 'animal:'.$animal->id,
                'treated_on' => today()->toDateString(),
                'reason' => 'Persistent cough',
                'diagnosis' => 'Suspected bacterial respiratory infection',
                'medication' => 'Veterinarian-prescribed antibiotic',
            ])->assertRedirect('/activities');

        $this->assertDatabaseHas('treatments', [
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'recorded_by' => $veterinarian->id,
            'diagnosis' => 'Suspected bacterial respiratory infection',
            'veterinarian_name' => $veterinarian->name,
        ]);
    }

    public function test_owner_can_revoke_veterinarian_access(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        $veterinarian = User::factory()->create();
        $farm->users()->attach($veterinarian, ['role' => 'veterinarian', 'is_active' => true]);
        $animal = Animal::factory()->for($farm)->create();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('team.members.revoke', $veterinarian))
            ->assertRedirect();

        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $veterinarian->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('farm_access_events', [
            'farm_id' => $farm->id,
            'subject_user_id' => $veterinarian->id,
            'event_type' => 'farm_access_revoked',
        ]);

        $scanUrl = URL::signedRoute('identify.animals.scan', ['animal' => $animal]);
        $this->actingAs($veterinarian)->get($scanUrl)->assertOk()->assertSee($animal->animal_code);
    }

    public function test_user_cannot_switch_to_an_unassigned_farm(): void
    {
        [$user, $farm] = $this->farmWithMember('veterinarian');
        $otherFarm = Farm::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_farm_id' => $farm->id])
            ->post('/farm-context', ['farm_id' => $otherFarm->id])
            ->assertNotFound()
            ->assertSessionHas('current_farm_id', $farm->id);
    }

    public function test_owner_cannot_revoke_another_farms_invitation(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');
        $otherFarm = Farm::factory()->create();
        $invitation = FarmInvitation::factory()->for($otherFarm)->create();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('team.invitations.revoke', $invitation))
            ->assertNotFound();
        $this->assertDatabaseHas('farm_invitations', ['id' => $invitation->id, 'revoked_at' => null]);
    }

    /** @return array{User, Farm} */
    private function farmWithMember(string $role): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => true]);

        return [$user, $farm];
    }
}
