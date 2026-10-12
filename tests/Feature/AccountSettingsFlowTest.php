<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Juan Dela Cruz']);

        $this->actingAs($user)
            ->get(route('settings.account.index'))
            ->assertOk()
            ->assertSee('My account')
            ->assertSee('Juan Dela Cruz');

        $this->patch(route('settings.account.update'), ['name' => 'Juan Santos'])
            ->assertRedirect(route('settings.account.index'))
            ->assertSessionHas('success', 'Account details updated.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Juan Santos']);
    }

    public function test_authenticated_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        $this->actingAs($user)
            ->patch(route('settings.account.password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('settings.account.index'))
            ->assertSessionHas('success', 'Password updated.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        $this->actingAs($user)
            ->from(route('settings.account.index'))
            ->patch(route('settings.account.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('settings.account.index'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_guest_cannot_access_account_settings(): void
    {
        $this->get(route('settings.account.index'))->assertRedirect(route('login'));
        $this->patch(route('settings.account.update'), ['name' => 'Unauthenticated'])->assertRedirect(route('login'));
    }
}
