<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Breed;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HerdWiseSeeder extends Seeder
{
    public function run(): void
    {
        $species = collect([['name' => 'Goat', 'code' => 'GOAT', 'supports_individuals' => true, 'supports_batches' => true, 'breeds' => ['Boer', 'Native/Philippine']], ['name' => 'Cattle', 'code' => 'CATTLE', 'supports_individuals' => true, 'supports_batches' => false, 'breeds' => ['Brahman', 'Holstein']], ['name' => 'Pig', 'code' => 'PIG', 'supports_individuals' => true, 'supports_batches' => true, 'breeds' => ['Large White', 'Native Pig']], ['name' => 'Chicken', 'code' => 'CHICKEN', 'supports_individuals' => false, 'supports_batches' => true, 'breeds' => ['Broiler', 'Layer', 'Native Chicken']]])->mapWithKeys(function ($item) {
            $breeds = $item['breeds'];
            unset($item['breeds']);
            $model = Species::updateOrCreate(['code' => $item['code']], $item);
            foreach ($breeds as $name) {
                Breed::firstOrCreate(['species_id' => $model->id, 'name' => $name]);
            }

return [$model->code => $model];
        });
        $user = User::firstOrCreate(['email' => 'juan@herdwise.test'], ['name' => 'Juan Dela Cruz', 'password' => Hash::make('password')]);
        $farm = Farm::firstOrCreate(['slug' => 'green-pastures-demo'], ['name' => 'Green Pastures Farm', 'location' => 'Nueva Ecija, Philippines']);
        $farm->users()->syncWithoutDetaching([$user->id => ['role' => 'owner', 'is_active' => true]]);
        Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'G-024'], ['species_id' => $species['GOAT']->id, 'breed_id' => $species['GOAT']->breeds()->where('name', 'Boer')->value('id'), 'ear_tag' => 'ET-1024', 'name' => 'Luna', 'sex' => 'female', 'birth_date' => '2024-03-18', 'status' => 'pregnant', 'location' => 'North Pen', 'created_by' => $user->id]);
        Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'P-011'], ['species_id' => $species['PIG']->id, 'sex' => 'male', 'status' => 'under_observation', 'location' => 'Isolation Pen', 'created_by' => $user->id]);
        LivestockBatch::firstOrCreate(['farm_id' => $farm->id, 'batch_code' => 'B-2026-003'], ['species_id' => $species['CHICKEN']->id, 'breed_id' => $species['CHICKEN']->breeds()->where('name', 'Broiler')->value('id'), 'started_on' => '2026-08-12', 'initial_count' => 250, 'current_count' => 246, 'purpose' => 'Meat production', 'status' => 'active', 'location' => 'Poultry House A', 'created_by' => $user->id]);
    }
}
