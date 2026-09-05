<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnimalRequest;
use App\Http\Requests\UpdateAnimalRequest;
use App\Models\Animal;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnimalController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $search = $request->string('search');
        $showArchived = $request->string('view')->value() === 'archived';
        $animals = $farm->animals()
            ->with(['species', 'breed'])
            ->when($showArchived, fn ($query) => $query->archived(), fn ($query) => $query->notArchived())
            ->when($search->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('animal_code', 'like', '%'.$search.'%')->orWhere('ear_tag', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('livestock.animals', ['farm' => $farm, 'animals' => $animals, 'showArchived' => $showArchived, 'currentFarmRole' => $farm->pivot->role]);
    }

    public function create(Request $request): View
    {
        abort_if($this->currentFarmRole($request) === 'veterinarian', 403);

        return view('livestock.animal-form', ['animal' => null, 'species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function store(StoreAnimalRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $data += ['farm_id' => $farm->id, 'created_by' => $request->user()->id];
        $farm->animals()->create($data);

        return redirect()->route('animals.index')->with('success', 'Animal added to the registry.');
    }

    public function edit(Request $request, Animal $animal): View
    {
        $farm = $this->activateFarmContext($request, $animal->farm_id);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        abort_if($animal->archived_at, 409, 'Restore this animal before editing its record.');

        return view('livestock.animal-form', ['animal' => $animal, 'species' => Species::with('breeds')->orderBy('name')->get()]);
    }

    public function update(UpdateAnimalRequest $request, Animal $animal): RedirectResponse
    {
        abort_if($animal->archived_at, 409, 'Restore this animal before editing its record.');
        $this->updateWithAudit($animal, $request->validated(), $request->user()->id);

        return redirect()->route('animals.show', $animal)->with('success', 'Animal record updated and the change was added to its history.');
    }

    public function archive(Request $request, Animal $animal): RedirectResponse
    {
        $farm = $this->activateFarmContext($request, $animal->farm_id);
        abort_unless(in_array($farm->pivot->role, ['owner', 'manager'], true), 403);

        $didArchive = DB::transaction(function () use ($animal, $farm, $request): bool {
            $lockedAnimal = Animal::query()->where('farm_id', $farm->id)->lockForUpdate()->findOrFail($animal->id);
            if ($lockedAnimal->archived_at) {
                return false;
            }

            $previousStatus = $lockedAnimal->status;
            $cancelledReminders = $lockedAnimal->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
            $lockedAnimal->update(['status_before_archive' => $previousStatus, 'status' => 'archived', 'archived_at' => now(), 'archived_by' => $request->user()->id]);
            $lockedAnimal->changeLogs()->create([
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
            return redirect()->route('animals.show', $animal)->with('success', 'This animal is already archived.');
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Animal archived. Its history remains available and pending reminders were cancelled.');
    }

    public function restore(Request $request, Animal $animal): RedirectResponse
    {
        $farm = $this->activateFarmContext($request, $animal->farm_id);
        abort_unless(in_array($farm->pivot->role, ['owner', 'manager'], true), 403);

        $didRestore = DB::transaction(function () use ($animal, $farm, $request): bool {
            $lockedAnimal = Animal::query()->where('farm_id', $farm->id)->lockForUpdate()->findOrFail($animal->id);
            if (! $lockedAnimal->archived_at) {
                return false;
            }

            $restoredStatus = $lockedAnimal->status_before_archive ?: 'healthy';
            $lockedAnimal->update(['status' => $restoredStatus, 'status_before_archive' => null, 'archived_at' => null, 'archived_by' => null]);
            $lockedAnimal->changeLogs()->create([
                'farm_id' => $farm->id,
                'actor_user_id' => $request->user()->id,
                'action' => 'restored',
                'changes' => ['status' => ['from' => 'archived', 'to' => $restoredStatus]],
            ]);

            return true;
        });

        if (! $didRestore) {
            return redirect()->route('animals.show', $animal)->with('success', 'This animal is already active.');
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Animal restored. Review its care schedule before creating new reminders.');
    }

    public function show(Request $request, Animal $animal): View
    {
        $farm = $this->activateFarmContext($request, $animal->farm_id);
        $animal->load(['species', 'breed', 'archiver', 'changeLogs.actor', 'healthRecords.recorder', 'treatments.recorder', 'vaccinations.recorder', 'dewormingRecords.recorder', 'breedingRecordsAsFemale.maleAnimal', 'pregnancyRecords', 'birthRecords', 'weightRecords' => fn ($query) => $query->oldest('measured_on'), 'reminders' => fn ($query) => $query->where('status', 'pending')->orderBy('due_on')]);
        $timeline = $animal->healthRecords->map(fn ($record) => ['date' => $record->observed_on, 'type' => 'Health observation', 'title' => $record->observation, 'meta' => ucfirst($record->severity).' · '.$record->recorder->name])
            ->concat($animal->treatments->map(fn ($record) => ['date' => $record->treated_on, 'type' => 'Treatment', 'title' => $record->reason, 'meta' => ($record->medication ?: 'No medication recorded').' · '.$record->recorder->name]))
            ->concat($animal->vaccinations->map(fn ($record) => ['date' => $record->given_on, 'type' => 'Vaccination', 'title' => $record->vaccine_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($animal->dewormingRecords->map(fn ($record) => ['date' => $record->administered_on, 'type' => 'Deworming', 'title' => $record->product_name, 'meta' => ($record->dose ?: 'Dose not specified').' · '.$record->recorder->name]))
            ->concat($animal->breedingRecordsAsFemale->map(fn ($record) => ['date' => $record->occurred_on, 'type' => 'Breeding', 'title' => ucfirst(str_replace('_', ' ', $record->method)), 'meta' => $record->maleAnimal?->animal_code ?? $record->male_reference ?? 'Male reference not specified']))
            ->concat($animal->pregnancyRecords->map(fn ($record) => ['date' => $record->checked_on, 'type' => 'Pregnancy check', 'title' => ucfirst(str_replace('_', ' ', $record->status)), 'meta' => $record->expected_delivery_on ? 'Expected '.$record->expected_delivery_on->format('M j, Y') : 'No expected delivery date']))
            ->concat($animal->birthRecords->map(fn ($record) => ['date' => $record->delivered_on, 'type' => 'Birth', 'title' => $record->live_count.' live of '.$record->offspring_count.' offspring', 'meta' => ucfirst(str_replace('_', ' ', $record->outcome))]))
            ->concat($animal->weightRecords->map(fn ($record) => ['date' => $record->measured_on, 'type' => 'Weight', 'title' => number_format((float) $record->weight_kg, 2).' kg', 'meta' => $record->measurement_method ?: 'Method not specified']))
            ->concat($animal->changeLogs->map(fn ($record) => ['date' => $record->created_at, 'type' => 'Registry '.ucfirst($record->action), 'title' => Str::headline($record->action).' animal record', 'meta' => ($record->actor?->name ?? 'Former farm member').' · '.collect(array_keys($record->changes ?? []))->map(fn (string $field): string => Str::headline($field))->join(', ')]))
            ->sortByDesc('date')->values();
        $weightMaximum = max(1, (float) $animal->weightRecords->max('weight_kg'));

        return view('livestock.animal-show', ['animal' => $animal, 'timeline' => $timeline, 'weightMaximum' => $weightMaximum, 'currentFarmRole' => $farm->pivot->role]);
    }

    /** @param array<string, mixed> $data */
    private function updateWithAudit(Animal $animal, array $data, int $actorUserId): void
    {
        DB::transaction(function () use ($animal, $data, $actorUserId): void {
            $lockedAnimal = Animal::query()->lockForUpdate()->findOrFail($animal->id);
            abort_if($lockedAnimal->archived_at, 409, 'Restore this animal before editing its record.');
            $before = $lockedAnimal->getRawOriginal();
            $lockedAnimal->update($data);
            $changes = [];

            foreach (array_keys($data) as $field) {
                if ($lockedAnimal->wasChanged($field)) {
                    $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $lockedAnimal->getRawOriginal($field)];
                }
            }

            if ($changes !== []) {
                $lockedAnimal->changeLogs()->create(['farm_id' => $lockedAnimal->farm_id, 'actor_user_id' => $actorUserId, 'action' => 'updated', 'changes' => $changes]);
            }
        });
    }
}
