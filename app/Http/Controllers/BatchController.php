<?php

namespace App\Http\Controllers;

use App\Models\LivestockBatch;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $batches = $farm->batches()->with(['species', 'breed'])->latest()->paginate(12);

        return view('livestock.batches', compact('farm', 'batches'));
    }

    public function create(Request $request): View
    {
        abort_if($this->currentFarmRole($request) === 'veterinarian', 403);

        return view('livestock.batch-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        $data = $request->validate(['batch_code' => ['required', 'max:40'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'started_on' => ['required', 'date', 'before_or_equal:today'], 'initial_count' => ['required', 'integer', 'min:1'], 'current_count' => ['required', 'integer', 'min:0', 'lte:initial_count'], 'purpose' => ['nullable', 'max:80'], 'status' => ['required', 'in:active,under_observation,quarantined,closed'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->batches()->create($data);

        return redirect()->route('batches.index')->with('success', 'Batch added to the registry.');
    }

    public function show(Request $request, LivestockBatch $batch): View
    {
        $this->activateFarmContext($request, $batch->farm_id);
        $batch->load(['species', 'breed', 'healthRecords.recorder', 'treatments.recorder', 'vaccinations.recorder', 'dewormingRecords.recorder', 'weightRecords' => fn ($query) => $query->oldest('measured_on'), 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $batch->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($batch->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => ($record->medication ?: 'No medication recorded').' · '.$record->recorder->name]))
            ->concat($batch->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($batch->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($batch->weightRecords->map(fn ($record) => ['date' => $record->measured_on, 'type' => 'Average weight', 'title' => number_format((float) $record->weight_kg, 2).' kg', 'meta' => 'Sample size: '.$record->sample_size]))
            ->sortByDesc('date')->values();
        $weightMaximum = max(1, (float) $batch->weightRecords->max('weight_kg'));

        return view('livestock.batch-show', compact('batch', 'timeline', 'weightMaximum'));
    }
}
