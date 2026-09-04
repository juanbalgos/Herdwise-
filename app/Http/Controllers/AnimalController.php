<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnimalController extends Controller
{
    private function farm(Request $request): Farm
    {
        return $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();
    }

    private function authorizeFarm(Animal $animal): void
    {
        abort_unless(request()->user()->farms()->whereKey($animal->farm_id)->exists(), 404);
    }

    public function index(Request $request): View
    {
        $farm = $this->farm($request);
        $search = $request->string('search');
        $animals = $farm->animals()->with(['species', 'breed'])->when($search->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q->where('animal_code', 'like', '%'.$search.'%')->orWhere('ear_tag', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))->latest()->paginate(12)->withQueryString();

        return view('livestock.animals', compact('farm', 'animals'));
    }

    public function create(): View
    {
        return view('livestock.animal-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $farm = $this->farm($request);
        $data = $request->validate(['animal_code' => ['required', 'max:40'], 'ear_tag' => ['nullable', 'max:40'], 'name' => ['nullable', 'max:80'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'sex' => ['required', 'in:male,female,unknown'], 'birth_date' => ['nullable', 'date', 'before_or_equal:today'], 'status' => ['required', 'in:healthy,under_observation,sick,under_treatment,pregnant,quarantined,sold,deceased,archived'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->animals()->create($data);

        return redirect()->route('animals.index')->with('success', 'Animal added to the registry.');
    }

    public function show(Animal $animal): View
    {
        $this->authorizeFarm($animal);
        $animal->load(['species', 'breed', 'healthRecords.recorder', 'treatments', 'vaccinations', 'dewormingRecords', 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $animal->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($animal->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => $record->medication ?: 'No medication recorded']))
            ->concat($animal->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => $record->dose ?: 'Dose not specified']))
            ->concat($animal->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => $record->dose ?: 'Dose not specified']))
            ->sortByDesc('date')->values();

        return view('livestock.animal-show', compact('animal', 'timeline'));
    }
}
