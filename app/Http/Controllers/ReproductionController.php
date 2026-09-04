<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBirthRecordRequest;
use App\Http\Requests\StoreBreedingRecordRequest;
use App\Http\Requests\StorePregnancyRecordRequest;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReproductionController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $activePregnancies = $farm->pregnancyRecords()->with(['animal.species', 'breedingRecord'])->where('status', 'confirmed')->whereDoesntHave('birthRecords')->orderBy('expected_delivery_on')->get();
        $recentBreeding = $farm->breedingRecords()->with(['femaleAnimal.species', 'maleAnimal'])->latest('occurred_on')->limit(8)->get();
        $recentBirths = $farm->birthRecords()->with('animal.species')->latest('delivered_on')->limit(8)->get();

        return view('reproduction.index', compact('activePregnancies', 'recentBreeding', 'recentBirths'));
    }

    public function create(Request $request, string $type): View
    {
        abort_unless(in_array($type, ['breeding', 'pregnancy', 'birth'], true), 404);
        $farm = $this->currentFarm($request);
        $role = $farm->pivot->role;
        abort_if($type === 'pregnancy' && ! in_array($role, ['owner', 'manager', 'veterinarian'], true), 403);
        abort_if(in_array($type, ['breeding', 'birth'], true) && $role === 'veterinarian', 403);
        $females = $farm->animals()->with('species')->where('sex', 'female')->whereNotIn('status', ['sold', 'deceased', 'archived'])->orderBy('animal_code')->get();
        $males = $farm->animals()->with('species')->where('sex', 'male')->whereNotIn('status', ['sold', 'deceased', 'archived'])->orderBy('animal_code')->get();
        $breedingRecords = $farm->breedingRecords()->with('femaleAnimal.species')->latest('occurred_on')->get();
        $pregnancies = $farm->pregnancyRecords()->with('animal.species')->where('status', 'confirmed')->whereDoesntHave('birthRecords')->orderBy('expected_delivery_on')->get();

        return view('reproduction.create', compact('type', 'females', 'males', 'breedingRecords', 'pregnancies', 'role'));
    }

    public function storeBreeding(StoreBreedingRecordRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $female = $farm->animals()->with('species')->where('sex', 'female')->findOrFail($data['female_animal_id']);
        if (filled($data['male_animal_id'] ?? null)) {
            $male = $farm->animals()->where('sex', 'male')->findOrFail($data['male_animal_id']);
            if ($male->species_id !== $female->species_id) {
                throw ValidationException::withMessages(['male_animal_id' => 'The selected male must be the same species as the female.']);
            }
        }

        DB::transaction(function () use ($farm, $request, $data, $female): void {
            $record = $farm->breedingRecords()->create([...$data, 'recorded_by' => $request->user()->id]);
            $this->scheduleReminder($record, $farm, $female->id, 'pregnancy_check', 'Pregnancy check: '.$female->animal_code, $data['pregnancy_check_due_on'] ?? null, $request->user()->id);
        });

        return redirect()->route('reproduction.index')->with('success', 'Breeding event recorded'.(filled($data['pregnancy_check_due_on'] ?? null) ? ' and pregnancy check scheduled.' : '.'));
    }

    public function storePregnancy(StorePregnancyRecordRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $animal = $farm->animals()->with('species')->where('sex', 'female')->findOrFail($data['animal_id']);
        $breeding = null;
        if (filled($data['breeding_record_id'] ?? null)) {
            $breeding = $farm->breedingRecords()->where('female_animal_id', $animal->id)->findOrFail($data['breeding_record_id']);
        }

        if ($data['status'] === 'confirmed' && ! filled($data['expected_delivery_on'] ?? null)) {
            if (! $breeding || ! ($data['gestation_days'] ?? $animal->species->default_gestation_days)) {
                throw ValidationException::withMessages(['expected_delivery_on' => 'Enter an expected delivery date or select breeding data with a configured gestation period.']);
            }

            $data['gestation_days'] = $data['gestation_days'] ?? $animal->species->default_gestation_days;
            $data['expected_delivery_on'] = $breeding->occurred_on->addDays($data['gestation_days'])->toDateString();
            $data['is_estimated'] = true;
        } else {
            $data['is_estimated'] = false;
        }

        DB::transaction(function () use ($farm, $request, $data, $animal, $breeding): void {
            $record = $farm->pregnancyRecords()->create([...$data, 'recorded_by' => $request->user()->id]);
            if ($breeding) {
                $breeding->reminder?->update(['status' => 'completed']);
            }

            if ($data['status'] === 'confirmed') {
                $animal->update(['status' => 'pregnant']);
                $this->scheduleReminder($record, $farm, $animal->id, 'expected_birth', 'Expected birth: '.$animal->animal_code, $data['expected_delivery_on'] ?? null, $request->user()->id);
            } elseif ($animal->status === 'pregnant') {
                $animal->update(['status' => 'healthy']);
            }
        });

        return redirect()->route('reproduction.index')->with('success', $data['status'] === 'confirmed' ? 'Pregnancy confirmed and expected birth scheduled.' : 'Pregnancy check recorded.');
    }

    public function storeBirth(StoreBirthRecordRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $animal = $farm->animals()->where('sex', 'female')->findOrFail($data['animal_id']);
        $pregnancy = null;
        if (filled($data['pregnancy_record_id'] ?? null)) {
            $pregnancy = $farm->pregnancyRecords()->where('animal_id', $animal->id)->where('status', 'confirmed')->findOrFail($data['pregnancy_record_id']);
        }

        DB::transaction(function () use ($farm, $request, $data, $animal, $pregnancy): void {
            $farm->birthRecords()->create([...$data, 'recorded_by' => $request->user()->id]);
            $pregnancy?->reminder?->update(['status' => 'completed']);
            $animal->update(['status' => 'healthy']);
        });

        return redirect()->route('reproduction.index')->with('success', 'Birth outcome recorded and the mother’s profile updated.');
    }

    private function scheduleReminder(Model $source, Farm $farm, int $animalId, string $type, string $title, ?string $dueOn, int $userId): void
    {
        if (! $dueOn) {
            return;
        }

        $source->reminder()->create(['farm_id' => $farm->id, 'animal_id' => $animalId, 'type' => $type, 'title' => $title, 'due_on' => $dueOn, 'status' => 'pending', 'created_by' => $userId]);
    }
}
