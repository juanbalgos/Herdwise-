<?php

namespace App\Http\Controllers;

use App\Models\LivestockBatch;
use App\Models\Species;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    private function farm(Request $request)
    {
        return $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();
    }

    public function index(Request $request)
    {
        $farm = $this->farm($request);
        $batches = $farm->batches()->with(['species', 'breed'])->latest()->paginate(12);

        return view('livestock.batches', compact('farm', 'batches'));
    }

    public function create()
    {
        return view('livestock.batch-form', ['species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $farm = $this->farm($request);
        $data = $request->validate(['batch_code' => ['required', 'max:40'], 'species_id' => ['required', 'exists:species,id'], 'breed_id' => ['nullable', 'exists:breeds,id'], 'started_on' => ['required', 'date', 'before_or_equal:today'], 'initial_count' => ['required', 'integer', 'min:1'], 'current_count' => ['required', 'integer', 'min:0', 'lte:initial_count'], 'purpose' => ['nullable', 'max:80'], 'status' => ['required', 'in:active,under_observation,quarantined,closed'], 'location' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000']]);
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->batches()->create($data);

        return redirect()->route('batches.index')->with('success', 'Batch added to the registry.');
    }

    public function show(LivestockBatch $batch)
    {
        abort_unless(request()->user()->farms()->whereKey($batch->farm_id)->exists(), 403);

        return view('livestock.batch-show', ['batch' => $batch->load(['species', 'breed'])]);
    }
}
