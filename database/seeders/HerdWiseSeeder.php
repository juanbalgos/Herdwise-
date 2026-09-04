<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Breed;
use App\Models\DewormingRecord;
use App\Models\Farm;
use App\Models\HealthRecord;
use App\Models\LivestockBatch;
use App\Models\Species;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Vaccination;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HerdWiseSeeder extends Seeder
{
    public function run(): void
    {
        $species = collect([
            ['name' => 'Goat', 'code' => 'GOAT', 'supports_individuals' => true, 'supports_batches' => true, 'breeds' => ['Boer', 'Native/Philippine']],
            ['name' => 'Cattle', 'code' => 'CATTLE', 'supports_individuals' => true, 'supports_batches' => false, 'breeds' => ['Brahman', 'Holstein']],
            ['name' => 'Pig', 'code' => 'PIG', 'supports_individuals' => true, 'supports_batches' => true, 'breeds' => ['Large White', 'Native Pig']],
            ['name' => 'Chicken', 'code' => 'CHICKEN', 'supports_individuals' => false, 'supports_batches' => true, 'breeds' => ['Broiler', 'Layer', 'Native Chicken']],
        ])->mapWithKeys(function (array $item): array {
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

        $goat = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'G-024'], ['species_id' => $species['GOAT']->id, 'breed_id' => $species['GOAT']->breeds()->where('name', 'Boer')->value('id'), 'ear_tag' => 'ET-1024', 'name' => 'Luna', 'sex' => 'female', 'birth_date' => '2024-03-18', 'status' => 'pregnant', 'location' => 'North Pen', 'created_by' => $user->id]);
        $pig = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'P-011'], ['species_id' => $species['PIG']->id, 'sex' => 'male', 'status' => 'under_treatment', 'location' => 'Isolation Pen', 'created_by' => $user->id]);
        $batch = LivestockBatch::firstOrCreate(['farm_id' => $farm->id, 'batch_code' => 'B-2026-003'], ['species_id' => $species['CHICKEN']->id, 'breed_id' => $species['CHICKEN']->breeds()->where('name', 'Broiler')->value('id'), 'started_on' => today()->subDays(23), 'initial_count' => 250, 'current_count' => 246, 'purpose' => 'Meat production', 'status' => 'active', 'location' => 'Poultry House A', 'created_by' => $user->id]);

        HealthRecord::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $pig->id, 'observed_on' => today()->subDay()], ['observation' => 'Reduced appetite and occasional coughing observed during morning rounds.', 'symptoms' => 'Reduced appetite; cough', 'severity' => 'moderate', 'appetite' => 'reduced', 'resulting_status' => 'under_observation', 'recorded_by' => $user->id]);
        $treatment = Treatment::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $pig->id, 'treated_on' => today()], ['reason' => 'Supportive care following respiratory observation', 'medication' => 'Treatment recorded from veterinarian instructions', 'veterinarian_name' => 'Dr. Santos', 'follow_up_on' => today()->addDay(), 'recorded_by' => $user->id]);
        $vaccination = Vaccination::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $goat->id, 'vaccine_name' => 'Clostridial vaccine', 'given_on' => today()->subMonths(6)], ['dose' => '2 mL', 'administered_by' => 'Farm technician', 'next_due_on' => today()->addDays(3), 'recorded_by' => $user->id]);
        $deworming = DewormingRecord::firstOrCreate(['farm_id' => $farm->id, 'livestock_batch_id' => $batch->id, 'product_name' => 'Poultry dewormer', 'administered_on' => today()->subMonths(2)], ['dose' => 'Per label and veterinarian guidance', 'administered_by' => 'Juan Dela Cruz', 'next_due_on' => today()->addDays(10), 'recorded_by' => $user->id]);

        $this->seedReminder($treatment, $farm, ['animal_id' => $pig->id, 'livestock_batch_id' => null], 'treatment_follow_up', 'Treatment follow-up: P-011', today()->addDay(), $user);
        $this->seedReminder($vaccination, $farm, ['animal_id' => $goat->id, 'livestock_batch_id' => null], 'vaccination', 'Vaccination due: G-024', today()->addDays(3), $user);
        $this->seedReminder($deworming, $farm, ['animal_id' => null, 'livestock_batch_id' => $batch->id], 'deworming', 'Deworming due: B-2026-003', today()->addDays(10), $user);
    }

    private function seedReminder(Treatment|Vaccination|DewormingRecord $source, Farm $farm, array $foreignKeys, string $type, string $title, CarbonInterface $dueOn, User $user): void
    {
        $source->reminder()->firstOrCreate([], [...$foreignKeys, 'farm_id' => $farm->id, 'type' => $type, 'title' => $title, 'due_on' => $dueOn, 'status' => 'pending', 'created_by' => $user->id]);
    }
}
