<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeightRecordRequest;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\LivestockBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeightRecordController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $records = $farm->weightRecords()->with(['animal.species', 'batch.species', 'recorder'])->latest('measured_on')->paginate(30);
        $latestAnimals = $farm->animals()->with(['species', 'weightRecords' => fn ($query) => $query->latest('measured_on')->limit(6)])->has('weightRecords')->get();
        $latestBatches = $farm->batches()->with(['species', 'weightRecords' => fn ($query) => $query->latest('measured_on')->limit(6)])->has('weightRecords')->get();

        return view('weights.index', compact('records', 'latestAnimals', 'latestBatches'));
    }

    public function create(Request $request): View
    {
        $farm = $this->currentFarm($request);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        $animals = $farm->animals()->with('species')->whereNotIn('status', ['sold', 'deceased', 'archived'])->orderBy('animal_code')->get();
        $batches = $farm->batches()->with('species')->where('status', '!=', 'closed')->orderBy('batch_code')->get();

        return view('weights.create', compact('animals', 'batches'));
    }

    public function store(StoreWeightRecordRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_if($farm->pivot->role === 'veterinarian', 403);
        $data = $request->validated();
        $target = $this->resolveTarget($farm, $data['subject_type'], (int) $data['subject_id']);
        unset($data['subject_type'], $data['subject_id']);

        DB::transaction(function () use ($farm, $request, $data, $target): void {
            $record = $farm->weightRecords()->create([...$data, ...$target['foreign_keys'], 'recorded_by' => $request->user()->id]);
            $this->scheduleReminder($record, $farm, $target['foreign_keys'], 'Weight check: '.$this->targetLabel($target['subject']), $data['next_check_on'] ?? null, $request->user()->id);
        });

        return redirect()->route('weights.index')->with('success', 'Weight recorded'.(filled($data['next_check_on'] ?? null) ? ' and next check scheduled.' : '.'));
    }

    /** @return array{subject: Animal|LivestockBatch, foreign_keys: array{animal_id: ?int, livestock_batch_id: ?int}} */
    private function resolveTarget(Farm $farm, string $subjectType, int $subjectId): array
    {
        if ($subjectType === 'animal') {
            $animal = $farm->animals()->findOrFail($subjectId);

            return ['subject' => $animal, 'foreign_keys' => ['animal_id' => $animal->id, 'livestock_batch_id' => null]];
        }

        $batch = $farm->batches()->findOrFail($subjectId);

        return ['subject' => $batch, 'foreign_keys' => ['animal_id' => null, 'livestock_batch_id' => $batch->id]];
    }

    /** @param array{animal_id: ?int, livestock_batch_id: ?int} $foreignKeys */
    private function scheduleReminder(Model $source, Farm $farm, array $foreignKeys, string $title, ?string $dueOn, int $userId): void
    {
        if (! $dueOn) {
            return;
        }

        $source->reminder()->create([...$foreignKeys, 'farm_id' => $farm->id, 'type' => 'weight_check', 'title' => $title, 'due_on' => $dueOn, 'status' => 'pending', 'created_by' => $userId]);
    }

    private function targetLabel(Animal|LivestockBatch $subject): string
    {
        return $subject instanceof Animal ? $subject->animal_code : $subject->batch_code;
    }
}
