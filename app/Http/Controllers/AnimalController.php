<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnimalController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $search = $request->string('search');
        $animals = $farm->animals()->with(['species', 'breed'])->when($search->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q->where('animal_code', 'like', '%'.$search.'%')->orWhere('ear_tag', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))->latest()->paginate(12)->withQueryString();

        return view('livestock.animals', compact('farm', 'animals'));
    }

    public function create(Request $request): View
    {
        abort_if($this->currentFarmRole($request) === 'veterinarian', 403);

        return view('livestock.animal-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        $data = $request->validate(['animal_code' => ['required', 'max:40'], 'ear_tag' => ['nullable', 'max:40'], 'name' => ['nullable', 'max:80'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'sex' => ['required', 'in:male,female,unknown'], 'birth_date' => ['nullable', 'date', 'before_or_equal:today'], 'status' => ['required', 'in:healthy,under_observation,sick,under_treatment,pregnant,quarantined,sold,deceased,archived'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->animals()->create($data);

        return redirect()->route('animals.index')->with('success', 'Animal added to the registry.');
    }

    public function show(Request $request, Animal $animal): View
    {
        $this->activateFarmContext($request, $animal->farm_id);
        $animal->load(['species', 'breed', 'healthRecords.recorder', 'treatments.recorder', 'vaccinations.recorder', 'dewormingRecords.recorder', 'breedingRecordsAsFemale.maleAnimal', 'pregnancyRecords', 'birthRecords', 'weightRecords' => fn ($query) => $query->oldest('measured_on'), 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $animal->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($animal->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => ($record->medication ?: 'No medication recorded').' · '.$record->recorder->name]))
            ->concat($animal->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($animal->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($animal->breedingRecordsAsFemale->map(fn ($record) => ['date' => $record->occurred_on, 'type' => 'Breeding', 'title' => ucfirst(str_replace('_', ' ', $record->method)), 'meta' => $record->maleAnimal?->animal_code ?? $record->male_reference ?? 'Male reference not specified']))
            ->concat($animal->pregnancyRecords->map(fn ($record) => ['date' => $record->checked_on, 'type' => 'Pregnancy check', 'title' => ucfirst(str_replace('_', ' ', $record->status)), 'meta' => $record->expected_delivery_on ? 'Expected '.$record->expected_delivery_on->format('M j, Y') : 'No expected delivery date']))
            ->concat($animal->birthRecords->map(fn ($record) => ['date' => $record->delivered_on, 'type' => 'Birth', 'title' => $record->live_count.' live of '.$record->offspring_count.' offspring', 'meta' => ucfirst(str_replace('_', ' ', $record->outcome))]))
            ->concat($animal->weightRecords->map(fn ($record) => ['date' => $record->measured_on, 'type' => 'Weight', 'title' => number_format((float) $record->weight_kg, 2).' kg', 'meta' => $record->measurement_method ?: 'Method not specified']))
            ->sortByDesc('date')->values();
        $weightMaximum = max(1, (float) $animal->weightRecords->max('weight_kg'));

        return view('livestock.animal-show', compact('animal', 'timeline', 'weightMaximum'));
    }
}
