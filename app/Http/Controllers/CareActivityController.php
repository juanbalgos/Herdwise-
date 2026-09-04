<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDewormingRequest;
use App\Http\Requests\StoreHealthRecordRequest;
use App\Http\Requests\StoreTreatmentRequest;
use App\Http\Requests\StoreVaccinationRequest;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Reminder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareActivityController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $reminders = $farm->reminders()->with(['animal.species', 'batch.species'])->where('status', 'pending')->orderBy('due_on')->get();
        $recentHealth = $farm->healthRecords()->with(['animal', 'batch', 'recorder'])->latest('observed_on')->limit(5)->get();
        $recentVaccinations = $farm->vaccinations()->with(['animal', 'batch'])->latest('given_on')->limit(4)->get();
        $recentDeworming = $farm->dewormingRecords()->with(['animal', 'batch'])->latest('administered_on')->limit(4)->get();
        $recentTreatments = $farm->treatments()->with(['animal', 'batch'])->latest('treated_on')->limit(4)->get();

        return view('activities.index', compact('farm', 'reminders', 'recentHealth', 'recentVaccinations', 'recentDeworming', 'recentTreatments'));
    }

    public function create(Request $request, string $type): View
    {
        abort_unless(in_array($type, ['health', 'treatment', 'vaccination', 'deworming'], true), 404);
        $farm = $this->currentFarm($request);
        $role = $farm->pivot->role;
        abort_if($type === 'treatment' && ! in_array($role, ['owner', 'manager', 'veterinarian'], true), 403);
        $animals = $farm->animals()->with('species')->whereNotIn('status', ['sold', 'deceased', 'archived'])->orderBy('animal_code')->get();
        $batches = $farm->batches()->with('species')->where('status', '!=', 'closed')->orderBy('batch_code')->get();

        return view('activities.create', compact('type', 'animals', 'batches', 'role'));
    }

    public function storeHealth(StoreHealthRecordRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $target = $this->resolveTarget($farm, $data['subject_type'], (int) $data['subject_id']);
        unset($data['subject_type'],$data['subject_id']);
        DB::transaction(function () use ($farm, $request, $data, $target): void {
            $farm->healthRecords()->create([...$data, ...$target['foreign_keys'], 'recorded_by' => $request->user()->id]);
            if ($data['resulting_status'] ?? null) {
                $target['subject']->update(['status' => $data['resulting_status']]);
            }
        });

        return redirect()->route('activities.index')->with('success', 'Health observation recorded and the livestock status updated.');
    }

    public function storeTreatment(StoreTreatmentRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $target = $this->resolveTarget($farm, $data['subject_type'], (int) $data['subject_id']);
        if (filled($data['health_record_id'] ?? null)) {
            $farm->healthRecords()->where($target['foreign_keys'])->findOrFail($data['health_record_id']);
        } $role = $farm->pivot->role;
        abort_if(filled($data['diagnosis'] ?? null) && $role !== 'veterinarian', 403, 'Only an authorized veterinarian can enter a formal diagnosis.');
        if ($role === 'veterinarian') {
            $data['veterinarian_name'] = $request->user()->name;
        }
        unset($data['subject_type'],$data['subject_id']);
        DB::transaction(function () use ($farm, $request, $data, $target): void {
            $treatment = $farm->treatments()->create([...$data, ...$target['foreign_keys'], 'recorded_by' => $request->user()->id]);
            $this->scheduleReminder($treatment, $farm, $target['foreign_keys'], 'treatment_follow_up', 'Treatment follow-up: '.$this->targetLabel($target['subject']), $data['follow_up_on'] ?? null, $request->user()->id);
            $target['subject']->update(['status' => 'under_treatment']);
        });

        return redirect()->route('activities.index')->with('success', 'Treatment recorded'.(filled($data['follow_up_on'] ?? null) ? ' and follow-up scheduled.' : '.'));
    }

    public function storeVaccination(StoreVaccinationRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $target = $this->resolveTarget($farm, $data['subject_type'], (int) $data['subject_id']);
        unset($data['subject_type'],$data['subject_id']);
        DB::transaction(function () use ($farm, $request, $data, $target): void {
            $record = $farm->vaccinations()->create([...$data, ...$target['foreign_keys'], 'recorded_by' => $request->user()->id]);
            $this->scheduleReminder($record, $farm, $target['foreign_keys'], 'vaccination', 'Vaccination due: '.$this->targetLabel($target['subject']), $data['next_due_on'] ?? null, $request->user()->id);
        });

        return redirect()->route('activities.index')->with('success', 'Vaccination recorded'.(filled($data['next_due_on'] ?? null) ? ' and next dose scheduled.' : '.'));
    }

    public function storeDeworming(StoreDewormingRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $target = $this->resolveTarget($farm, $data['subject_type'], (int) $data['subject_id']);
        unset($data['subject_type'],$data['subject_id']);
        DB::transaction(function () use ($farm, $request, $data, $target): void {
            $record = $farm->dewormingRecords()->create([...$data, ...$target['foreign_keys'], 'recorded_by' => $request->user()->id]);
            $this->scheduleReminder($record, $farm, $target['foreign_keys'], 'deworming', 'Deworming due: '.$this->targetLabel($target['subject']), $data['next_due_on'] ?? null, $request->user()->id);
        });

        return redirect()->route('activities.index')->with('success', 'Deworming recorded'.(filled($data['next_due_on'] ?? null) ? ' and next schedule created.' : '.'));
    }

    public function completeReminder(Request $request, Reminder $reminder): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_unless($reminder->farm_id === $farm->id, 404);
        $reminder->update(['status' => 'completed']);

        return back()->with('success', 'Reminder marked complete.');
    }

    /** @return array{subject: Animal|LivestockBatch, foreign_keys: array{animal_id: ?int, livestock_batch_id: ?int}} */
    private function resolveTarget(Farm $farm, string $subjectType, int $subjectId): array
    {
        if ($subjectType === 'animal') {
            $animal = $farm->animals()->findOrFail($subjectId);

            return ['subject' => $animal, 'foreign_keys' => ['animal_id' => $animal->id, 'livestock_batch_id' => null]];
        } $batch = $farm->batches()->findOrFail($subjectId);

        return ['subject' => $batch, 'foreign_keys' => ['animal_id' => null, 'livestock_batch_id' => $batch->id]];
    }

    private function scheduleReminder(Model $source, Farm $farm, array $foreignKeys, string $type, string $title, ?string $dueOn, int $userId): void
    {
        if (! $dueOn) {
            return;
        } $source->reminder()->create([...$foreignKeys, 'farm_id' => $farm->id, 'type' => $type, 'title' => $title, 'due_on' => $dueOn, 'status' => 'pending', 'created_by' => $userId]);
    }

    private function targetLabel(Animal|LivestockBatch $subject): string
    {
        return $subject instanceof Animal ? $subject->animal_code : $subject->batch_code;
    }
}
