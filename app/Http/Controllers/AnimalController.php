<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Species;
use Illuminate\Http\Request;

class AnimalController extends Controller
{
    private function farm(Request $request)
    {
        return $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();
    }

    private function authorizeFarm(Animal $animal): void
    {
        abort_unless(request()->user()->farms()->whereKey($animal->farm_id)->exists(), 403);
    }

    public function index(Request $request)
    {
        $farm = $this->farm($request);
        $search = $request->string('search');
        $animals = $farm->animals()->with(['species', 'breed'])->when($search->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q->where('animal_code', 'like', '%'.$search.'%')->orWhere('ear_tag', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))->latest()->paginate(12)->withQueryString();

        return view('livestock.animals', compact('farm', 'animals'));
    }

    public function create()
    {
        return view('livestock.animal-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $farm = $this->farm($request);
        $data = $request->validate(['animal_code' => ['required', 'max:40'], 'ear_tag' => ['nullable', 'max:40'], 'name' => ['nullable', 'max:80'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'sex' => ['required', 'in:male,female,unknown'], 'birth_date' => ['nullable', 'date', 'before_or_equal:today'], 'status' => ['required', 'in:healthy,under_observation,sick,under_treatment,pregnant,quarantined,sold,deceased,archived'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->animals()->create($data);

        return redirect()->route('animals.index')->with('success', 'Animal added to the registry.');
    }

    public function show(Animal $animal)
    {
        $this->authorizeFarm($animal);

        return view('livestock.animal-show', ['animal' => $animal->load(['species', 'breed'])]);
    }
}
