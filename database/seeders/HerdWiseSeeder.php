<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\BirthRecord;
use App\Models\Breed;
use App\Models\BreedingRecord;
use App\Models\DewormingRecord;
use App\Models\Farm;
use App\Models\FarmAccessEvent;
use App\Models\HealthRecord;
use App\Models\LivestockBatch;
use App\Models\PregnancyRecord;
use App\Models\Species;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\WeightRecord;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HerdWiseSeeder extends Seeder
{
    public function run(): void
    {
        $species = collect([
            ['name' => 'Goat', 'code' => 'GOAT', 'supports_individuals' => true, 'supports_batches' => true, 'default_gestation_days' => 150, 'breeds' => ['Boer', 'Native/Philippine']],
            ['name' => 'Cattle', 'code' => 'CATTLE', 'supports_individuals' => true, 'supports_batches' => false, 'default_gestation_days' => 283, 'breeds' => ['Brahman', 'Holstein']],
            ['name' => 'Pig', 'code' => 'PIG', 'supports_individuals' => true, 'supports_batches' => true, 'default_gestation_days' => 114, 'breeds' => ['Large White', 'Native Pig']],
            ['name' => 'Chicken', 'code' => 'CHICKEN', 'supports_individuals' => false, 'supports_batches' => true, 'default_gestation_days' => null, 'breeds' => ['Broiler', 'Layer', 'Native Chicken']],
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
        $staff = User::firstOrCreate(['email' => 'staff@herdwise.test'], ['name' => 'Ana Reyes', 'password' => Hash::make('password')]);
        $veterinarian = User::firstOrCreate(['email' => 'vet@herdwise.test'], ['name' => 'Dr. Maria Santos', 'password' => Hash::make('password')]);
        $farm = Farm::firstOrCreate(['slug' => 'green-pastures-demo'], ['name' => 'Green Pastures Farm', 'location' => 'Nueva Ecija, Philippines']);
        $farm->users()->syncWithoutDetaching([$user->id => ['role' => 'owner', 'is_active' => true]]);
        $farm->users()->syncWithoutDetaching([$staff->id => ['role' => 'staff', 'is_active' => true]]);
        $farm->users()->syncWithoutDetaching([$veterinarian->id => ['role' => 'veterinarian', 'is_active' => true]]);
        FarmAccessEvent::firstOrCreate(['farm_id' => $farm->id, 'subject_user_id' => $veterinarian->id, 'event_type' => 'veterinarian_access_accepted'], ['actor_user_id' => $user->id, 'metadata' => ['source' => 'demo_seed']]);

        $goat = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'G-024'], ['species_id' => $species['GOAT']->id, 'breed_id' => $species['GOAT']->breeds()->where('name', 'Boer')->value('id'), 'ear_tag' => 'ET-1024', 'name' => 'Luna', 'sex' => 'female', 'birth_date' => '2024-03-18', 'status' => 'pregnant', 'location' => 'North Pen', 'created_by' => $user->id]);
        $maleGoat = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'G-009'], ['species_id' => $species['GOAT']->id, 'breed_id' => $species['GOAT']->breeds()->where('name', 'Boer')->value('id'), 'ear_tag' => 'ET-1009', 'name' => 'Bruno', 'sex' => 'male', 'birth_date' => '2023-01-10', 'status' => 'healthy', 'location' => 'Breeding Pen', 'created_by' => $user->id]);
        $motherGoat = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'G-018'], ['species_id' => $species['GOAT']->id, 'breed_id' => $species['GOAT']->breeds()->where('name', 'Native/Philippine')->value('id'), 'name' => 'Maya', 'sex' => 'female', 'status' => 'healthy', 'location' => 'North Pen', 'created_by' => $user->id]);
        $pig = Animal::firstOrCreate(['farm_id' => $farm->id, 'animal_code' => 'P-011'], ['species_id' => $species['PIG']->id, 'sex' => 'male', 'status' => 'under_treatment', 'location' => 'Isolation Pen', 'created_by' => $user->id]);
        $batch = LivestockBatch::firstOrCreate(['farm_id' => $farm->id, 'batch_code' => 'B-2026-003'], ['species_id' => $species['CHICKEN']->id, 'breed_id' => $species['CHICKEN']->breeds()->where('name', 'Broiler')->value('id'), 'started_on' => today()->subDays(23), 'initial_count' => 250, 'current_count' => 246, 'purpose' => 'Meat production', 'status' => 'active', 'location' => 'Poultry House A', 'created_by' => $user->id]);

        HealthRecord::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $pig->id, 'observed_on' => today()->subDay()], ['observation' => 'Reduced appetite and occasional coughing observed during morning rounds.', 'symptoms' => 'Reduced appetite; cough', 'severity' => 'moderate', 'appetite' => 'reduced', 'resulting_status' => 'under_observation', 'recorded_by' => $user->id]);
        $treatment = Treatment::updateOrCreate(['farm_id' => $farm->id, 'animal_id' => $pig->id, 'treated_on' => today()], ['reason' => 'Supportive care following respiratory observation', 'diagnosis' => 'Suspected respiratory infection', 'medication' => 'Veterinarian-prescribed supportive treatment', 'veterinarian_name' => $veterinarian->name, 'follow_up_on' => today()->addDay(), 'recorded_by' => $veterinarian->id]);
        $vaccination = Vaccination::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $goat->id, 'vaccine_name' => 'Clostridial vaccine', 'given_on' => today()->subMonths(6)], ['dose' => '2 mL', 'administered_by' => 'Farm technician', 'next_due_on' => today()->addDays(3), 'recorded_by' => $user->id]);
        $deworming = DewormingRecord::firstOrCreate(['farm_id' => $farm->id, 'livestock_batch_id' => $batch->id, 'product_name' => 'Poultry dewormer', 'administered_on' => today()->subMonths(2)], ['dose' => 'Per label and veterinarian guidance', 'administered_by' => 'Juan Dela Cruz', 'next_due_on' => today()->addDays(10), 'recorded_by' => $user->id]);

        $breeding = BreedingRecord::firstOrCreate(['farm_id' => $farm->id, 'female_animal_id' => $goat->id, 'occurred_on' => today()->subDays(120)], ['male_animal_id' => $maleGoat->id, 'heat_detected_on' => today()->subDays(121), 'method' => 'natural', 'pregnancy_check_due_on' => today()->subDays(90), 'recorded_by' => $user->id]);
        $pregnancy = PregnancyRecord::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $goat->id, 'breeding_record_id' => $breeding->id], ['checked_on' => today()->subDays(85), 'status' => 'confirmed', 'check_method' => 'Veterinary palpation', 'gestation_days' => 150, 'expected_delivery_on' => today()->addDays(30), 'is_estimated' => true, 'recorded_by' => $user->id]);
        BirthRecord::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $motherGoat->id, 'delivered_on' => today()->subDays(20)], ['outcome' => 'live_birth', 'offspring_count' => 2, 'live_count' => 2, 'stillborn_count' => 0, 'male_count' => 1, 'female_count' => 1, 'recorded_by' => $user->id]);

        foreach ([[today()->subDays(60), 31.4], [today()->subDays(30), 34.1], [today(), 37.6]] as [$date, $weight]) {
            WeightRecord::firstOrCreate(['farm_id' => $farm->id, 'animal_id' => $goat->id, 'measured_on' => $date], ['weight_kg' => $weight, 'measurement_method' => 'Livestock scale', 'recorded_by' => $user->id]);
        }
        foreach ([[today()->subDays(14), 0.72], [today()->subDays(7), 1.08], [today(), 1.46]] as [$date, $weight]) {
            $batchWeight = WeightRecord::firstOrCreate(['farm_id' => $farm->id, 'livestock_batch_id' => $batch->id, 'measured_on' => $date], ['weight_kg' => $weight, 'sample_size' => 25, 'measurement_method' => 'Digital sample scale', 'next_check_on' => $date->isToday() ? today()->addDays(7) : null, 'recorded_by' => $user->id]);
        }

        $this->seedReminder($treatment, $farm, ['animal_id' => $pig->id, 'livestock_batch_id' => null], 'treatment_follow_up', 'Treatment follow-up: P-011', today()->addDay(), $user);
        $this->seedReminder($vaccination, $farm, ['animal_id' => $goat->id, 'livestock_batch_id' => null], 'vaccination', 'Vaccination due: G-024', today()->addDays(3), $user);
        $this->seedReminder($deworming, $farm, ['animal_id' => null, 'livestock_batch_id' => $batch->id], 'deworming', 'Deworming due: B-2026-003', today()->addDays(10), $user);
        $this->seedReminder($pregnancy, $farm, ['animal_id' => $goat->id, 'livestock_batch_id' => null], 'expected_birth', 'Expected birth: G-024', today()->addDays(30), $user);
        $this->seedReminder($batchWeight, $farm, ['animal_id' => null, 'livestock_batch_id' => $batch->id], 'weight_check', 'Weight check: B-2026-003', today()->addDays(7), $user);
    }

    private function seedReminder(Treatment|Vaccination|DewormingRecord|PregnancyRecord|WeightRecord $source, Farm $farm, array $foreignKeys, string $type, string $title, CarbonInterface $dueOn, User $user): void
    {
        $source->reminder()->firstOrCreate([], [...$foreignKeys, 'farm_id' => $farm->id, 'type' => $type, 'title' => $title, 'due_on' => $dueOn, 'status' => 'pending', 'created_by' => $user->id]);
    }
}
