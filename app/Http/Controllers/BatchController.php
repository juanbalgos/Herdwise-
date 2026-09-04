<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    private function farm(Request $request): Farm
    {
        return $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();
    }

    public function index(Request $request): View
    {
        $farm = $this->farm($request);
        $batches = $farm->batches()->with(['species', 'breed'])->latest()->paginate(12);

        return view('livestock.batches', compact('farm', 'batches'));
    }

    public function create(): View
    {
        return view('livestock.batch-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $farm = $this->farm($request);
        $data = $request->validate(['batch_code' => ['required', 'max:40'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'started_on' => ['required', 'date', 'before_or_equal:today'], 'initial_count' => ['required', 'integer', 'min:1'], 'current_count' => ['required', 'integer', 'min:0', 'lte:initial_count'], 'purpose' => ['nullable', 'max:80'], 'status' => ['required', 'in:active,under_observation,quarantined,closed'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->batches()->create($data);

        return redirect()->route('batches.index')->with('success', 'Batch added to the registry.');
    }

    public function show(LivestockBatch $batch): View
    {
        abort_unless(request()->user()->farms()->whereKey($batch->farm_id)->exists(), 404);
        $batch->load(['species', 'breed', 'healthRecords.recorder', 'treatments', 'vaccinations', 'dewormingRecords', 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $batch->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($batch->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => $record->medication ?: 'No medication recorded']))
            ->concat($batch->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => $record->dose ?: 'Dose not specified']))
            ->concat($batch->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => $record->dose ?: 'Dose not specified']))
            ->sortByDesc('date')->values();

        return view('livestock.batch-show', compact('batch', 'timeline'));
    }
}
