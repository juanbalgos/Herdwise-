<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HerdWiseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_a_farm(): void
    {
        $this->post('/register', ['name' => 'Juan', 'email' => 'juan@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'farm_name' => 'Juan Farm', 'location' => 'Bulacan'])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('farms', ['name' => 'Juan Farm']);
        $this->assertDatabaseHas('farm_users', ['role' => 'owner']);
    }

    public function test_livestock_records_are_scoped_to_current_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farm->users()->attach($user, ['role' => 'owner']);
        $species = Species::create(['name' => 'Goat', 'code' => 'GOAT']);
        $this->actingAs($user)->post('/animals', ['animal_code' => 'G-001', 'species_id' => $species->id, 'sex' => 'female', 'status' => 'healthy'])->assertRedirect('/animals');
        $this->assertDatabaseHas('animals', ['farm_id' => $farm->id, 'animal_code' => 'G-001']);
    }

    public function test_authenticated_owner_can_read_the_farm_scoped_api(): void
    {
        $user = User::factory()->create();
        $farm = Farm::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farm->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user)->getJson('/api/v1/animals')->assertOk()->assertJsonCount(0, 'data');
    }
}
