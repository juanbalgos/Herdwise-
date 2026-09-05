<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\LivestockBatch;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $showArchived = $request->string('view')->value() === 'archived';
        $search = $request->string('search');
        $batches = $farm->batches()
            ->with(['species', 'breed'])
            ->when($showArchived, fn ($query) => $query->archived(), fn ($query) => $query->notArchived())
            ->when($search->isNotEmpty(), fn ($query) => $query->where('batch_code', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('livestock.batches', ['farm' => $farm, 'batches' => $batches, 'showArchived' => $showArchived, 'currentFarmRole' => $farm->pivot->role]);
    }

    public function create(Request $request): View
    {
        abort_if($this->currentFarmRole($request) === 'veterinarian', 403);

        return view('livestock.batch-form', ['batch' => null, 'species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->batches()->create($data);

        return redirect()->route('batches.index')->with('success', 'Batch added to the registry.');
    }

    public function edit(Request $request, LivestockBatch $batch): View
    {
        $farm = $this->activateFarmContext($request, $batch->farm_id);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        abort_if($batch->archived_at, 409, 'Restore this batch before editing its record.');

        return view('livestock.batch-form', ['batch' => $batch, 'species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function update(UpdateBatchRequest $request, LivestockBatch $batch): RedirectResponse
    {
        abort_if($batch->archived_at, 409, 'Restore this batch before editing its record.');
        $this->updateWithAudit($batch, $request->validated(), $request->user()->id);

        return redirect()->route('batches.show', $batch)->with('success', 'Batch record updated and the change was added to its history.');
    }

    public function archive(Request $request, LivestockBatch $batch): RedirectResponse
    {
        $farm = $this->activateFarmContext($request, $batch->farm_id);
        abort_unless(in_array($farm->pivot->role, ['owner', 'manager'], true), 403);

        $didArchive = DB::transaction(function () use ($batch, $farm, $request): bool {
            $lockedBatch = LivestockBatch::query()->where('farm_id', $farm->id)->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->archived_at) {
                return false;
            }

            $previousStatus = $lockedBatch->status;
            $cancelledReminders = $lockedBatch->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
            $lockedBatch->update(['status_before_archive' => $previousStatus, 'status' => 'archived', 'archived_at' => now(), 'archived_by' => $request->user()->id]);
            $lockedBatch->changeLogs()->create([
                'farm_id' => $farm->id,
                'actor_user_id' => $request->user()->id,
                'action' => 'archived',
                'changes' => [
                    'status' => ['from' => $previousStatus, 'to' => 'archived'],
                    'pending_reminders' => ['from' => $cancelledReminders, 'to' => 0],
                ],
            ]);

            return true;
        });

        if (! $didArchive) {
            return redirect()->route('batches.show', $batch)->with('success', 'This batch is already archived.');
        }

        return redirect()->route('batches.show', $batch)->with('success', 'Batch archived. Its history remains available and pending reminders were cancelled.');
    }

    public function restore(Request $request, LivestockBatch $batch): RedirectResponse
    {
        $farm = $this->activateFarmContext($request, $batch->farm_id);
        abort_unless(in_array($farm->pivot->role, ['owner', 'manager'], true), 403);

        $didRestore = DB::transaction(function () use ($batch, $farm, $request): bool {
            $lockedBatch = LivestockBatch::query()->where('farm_id', $farm->id)->lockForUpdate()->findOrFail($batch->id);
            if (! $lockedBatch->archived_at) {
                return false;
            }

            $restoredStatus = $lockedBatch->status_before_archive ?: 'active';
            $lockedBatch->update(['status' => $restoredStatus, 'status_before_archive' => null, 'archived_at' => null, 'archived_by' => null]);
            $lockedBatch->changeLogs()->create([
                'farm_id' => $farm->id,
                'actor_user_id' => $request->user()->id,
                'action' => 'restored',
                'changes' => ['status' => ['from' => 'archived', 'to' => $restoredStatus]],
            ]);

            return true;
        });

        if (! $didRestore) {
            return redirect()->route('batches.show', $batch)->with('success', 'This batch is already active.');
        }

        return redirect()->route('batches.show', $batch)->with('success', 'Batch restored. Review its care schedule before creating new reminders.');
    }

    public function show(Request $request, LivestockBatch $batch): View
    {
        $farm = $this->activateFarmContext($request, $batch->farm_id);
        $batch->load(['species', 'breed', 'archiver', 'changeLogs.actor', 'healthRecords.recorder', 'treatments.recorder', 'vaccinations.recorder', 'dewormingRecords.recorder', 'weightRecords' => fn ($query) => $query->oldest('measured_on'), 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $batch->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($batch->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => ($record->medication ?: 'No medication recorded').' · '.$record->recorder->name]))
            ->concat($batch->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($batch->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($batch->weightRecords->map(fn ($record) => ['date' => $record->measured_on, 'type' => 'Average weight', 'title' => number_format((float) $record->weight_kg, 2).' kg', 'meta' => 'Sample size: '.$record->sample_size]))
            ->concat($batch->changeLogs->map(fn ($record) => ['date' => $record->created_at, 'type' => 'Registry '.ucfirst($record->action), 'title' => Str::headline($record->action).' batch record', 'meta' => ($record->actor?->name ?? 'Former farm member').' · '.collect(array_keys($record->changes ?? []))->map(fn (string $field): string => Str::headline($field))->join(', ')]))
            ->sortByDesc('date')->values();
        $weightMaximum = max(1, (float) $batch->weightRecords->max('weight_kg'));

        return view('livestock.batch-show', ['batch' => $batch, 'timeline' => $timeline, 'weightMaximum' => $weightMaximum, 'currentFarmRole' => $farm->pivot->role]);
    }

    /** @param array<string, mixed> $data */
    private function updateWithAudit(LivestockBatch $batch, array $data, int $actorUserId): void
    {
        DB::transaction(function () use ($batch, $data, $actorUserId): void {
            $lockedBatch = LivestockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_if($lockedBatch->archived_at, 409, 'Restore this batch before editing its record.');
            $before = $lockedBatch->getRawOriginal();
            $lockedBatch->update($data);
            $changes = [];

            foreach (array_keys($data) as $field) {
                if ($lockedBatch->wasChanged($field)) {
                    $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $lockedBatch->getRawOriginal($field)];
                }
            }

            if ($changes !== []) {
                $lockedBatch->changeLogs()->create(['farm_id' => $lockedBatch->farm_id, 'actor_user_id' => $actorUserId, 'action' => 'updated', 'changes' => $changes]);
            }
        });
    }
}
