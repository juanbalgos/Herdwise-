<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmSettingsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_update_the_current_farm_settings(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('settings.farm.index'))
            ->assertOk()
            ->assertSee('Farm settings')
            ->assertSee($farm->name);

        $this->patch(route('settings.farm.update'), [
            'name' => 'Green Valley Farm',
            'location' => 'Batangas City',
            'contact_number' => '+63 917 123 4567',
            'timezone' => 'Asia/Singapore',
        ])->assertRedirect(route('settings.farm.index'))
            ->assertSessionHas('success', 'Farm settings updated.');

        $this->assertDatabaseHas('farms', [
            'id' => $farm->id,
            'name' => 'Green Valley Farm',
            'location' => 'Batangas City',
            'contact_number' => '+63 917 123 4567',
            'timezone' => 'Asia/Singapore',
        ]);
    }

    public function test_non_owners_cannot_view_or_update_farm_settings(): void
    {
        foreach (['manager', 'staff', 'veterinarian'] as $role) {
            [$member, $farm] = $this->farmWithMember($role);

            $this->actingAs($member)
                ->withSession(['current_farm_id' => $farm->id])
                ->get(route('settings.farm.index'))
                ->assertForbidden();

            $this->patch(route('settings.farm.update'), [
                'name' => 'Changed farm',
                'timezone' => 'UTC',
            ])->assertForbidden();

            $this->assertDatabaseMissing('farms', ['id' => $farm->id, 'name' => 'Changed farm']);
        }
    }

    public function test_settings_require_a_name_and_valid_timezone(): void
    {
        [$owner, $farm] = $this->farmWithMember('owner');

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('settings.farm.index'))
            ->patch(route('settings.farm.update'), [
                'name' => '',
                'timezone' => 'not-a-timezone',
            ])
            ->assertRedirect(route('settings.farm.index'))
            ->assertSessionHasErrors(['name', 'timezone']);

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => $farm->name, 'timezone' => $farm->timezone]);
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
