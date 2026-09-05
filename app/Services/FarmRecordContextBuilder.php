<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Species;
use Carbon\CarbonImmutable;

class FarmRecordContextBuilder
{
    private const RECORD_LIMIT = 20;

    /** @return array<string, mixed> */
    public function build(Farm $farm): array
    {
        $now = CarbonImmutable::now($farm->timezone);
        $today = $now->startOfDay();
        $attentionStatuses = ['sick', 'under_observation', 'under_treatment', 'quarantined'];

        $individualSpecies = $farm->animals()->notArchived()
            ->selectRaw('species_id, COUNT(*) AS total')
            ->groupBy('species_id')
            ->pluck('total', 'species_id');
        $batchSpecies = $farm->batches()
            ->where('status', 'active')
            ->selectRaw('species_id, COUNT(*) AS batch_count, COALESCE(SUM(current_count), 0) AS population')
            ->groupBy('species_id')
            ->get()
            ->keyBy('species_id');
        $speciesIds = $individualSpecies->keys()->merge($batchSpecies->keys())->unique()->values();
        $species = Species::query()
            ->whereIn('id', $speciesIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Species $species) use ($individualSpecies, $batchSpecies): array {
                $batch = $batchSpecies->get($species->id);

                return [
                    'name' => $species->name,
                    'individuals' => (int) $individualSpecies->get($species->id, 0),
                    'active_batches' => (int) ($batch?->batch_count ?? 0),
                    'batch_population' => (int) ($batch?->population ?? 0),
                ];
            })
            ->values()
            ->all();

        $attentionQuery = $farm->animals()->notArchived()->whereIn('status', $attentionStatuses);
        $attention = (clone $attentionQuery)
            ->with('species:id,name')
            ->orderBy('status')
            ->orderBy('animal_code')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'species_id', 'animal_code', 'name', 'status', 'location'])
            ->map(fn ($animal): array => [
                'code' => $animal->animal_code,
                'name' => $animal->name,
                'species' => $animal->species->name,
                'status' => $animal->status,
                'location' => $animal->location,
            ])
            ->all();

        $reminderQuery = $farm->reminders()->where('status', 'pending');
        $reminders = (clone $reminderQuery)
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'type', 'title', 'due_on', 'priority'])
            ->map(fn ($reminder): array => [
                'subject' => $reminder->animal?->animal_code ?? $reminder->batch?->batch_code ?? 'Farm-wide',
                'type' => $reminder->type,
                'title' => $reminder->title,
                'due_on' => $reminder->due_on->toDateString(),
                'timing' => $reminder->due_on->isBefore($today) ? 'overdue' : ($reminder->due_on->isSameDay($today) ? 'due today' : 'upcoming'),
                'priority' => $reminder->priority,
            ])
            ->all();

        $recentHealth = $farm->healthRecords()
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->latest('observed_on')
            ->latest('id')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'observed_on', 'observation', 'symptoms', 'severity', 'temperature', 'appetite', 'behavior'])
            ->map(fn ($record): array => [
                'subject' => $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide',
                'observed_on' => $record->observed_on->toDateString(),
                'observation' => $record->observation,
                'symptoms' => $record->symptoms,
                'severity' => $record->severity,
                'temperature' => $record->temperature,
                'appetite' => $record->appetite,
                'behavior' => $record->behavior,
            ])
            ->all();

        $recentTreatments = $farm->treatments()
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->latest('treated_on')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'treated_on', 'reason', 'diagnosis', 'medication', 'dosage', 'follow_up_on'])
            ->map(fn ($record): array => [
                'subject' => $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide',
                'treated_on' => $record->treated_on->toDateString(),
                'reason' => $record->reason,
                'recorded_diagnosis' => $record->diagnosis,
                'medication' => $record->medication,
                'dosage' => $record->dosage,
                'follow_up_on' => $record->follow_up_on?->toDateString(),
            ])
            ->all();

        $vaccinations = $farm->vaccinations()
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->latest('given_on')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'vaccine_name', 'given_on', 'next_due_on'])
            ->map(fn ($record): array => [
                'subject' => $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide',
                'vaccine' => $record->vaccine_name,
                'given_on' => $record->given_on->toDateString(),
                'next_due_on' => $record->next_due_on?->toDateString(),
            ])
            ->all();

        $deworming = $farm->dewormingRecords()
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->latest('administered_on')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'product_name', 'administered_on', 'next_due_on'])
            ->map(fn ($record): array => [
                'subject' => $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide',
                'product' => $record->product_name,
                'administered_on' => $record->administered_on->toDateString(),
                'next_due_on' => $record->next_due_on?->toDateString(),
            ])
            ->all();

        $pregnancyQuery = $farm->pregnancyRecords()
            ->where('status', 'confirmed')
            ->whereDoesntHave('birthRecords');
        $pregnancies = (clone $pregnancyQuery)
            ->with('animal:id,animal_code,name')
            ->orderByRaw('expected_delivery_on IS NULL')
            ->orderBy('expected_delivery_on')
            ->orderBy('id')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'animal_id', 'checked_on', 'expected_delivery_on', 'is_estimated'])
            ->map(fn ($record): array => [
                'subject' => $record->animal->animal_code,
                'name' => $record->animal->name,
                'checked_on' => $record->checked_on->toDateString(),
                'expected_delivery_on' => $record->expected_delivery_on?->toDateString(),
                'estimated_date' => $record->is_estimated,
            ])
            ->all();

        $weights = $farm->weightRecords()
            ->with(['animal:id,animal_code', 'batch:id,batch_code'])
            ->latest('measured_on')
            ->latest('id')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'animal_id', 'livestock_batch_id', 'measured_on', 'weight_kg', 'sample_size'])
            ->map(fn ($record): array => [
                'subject' => $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide',
                'measured_on' => $record->measured_on->toDateString(),
                'weight_kg' => (float) $record->weight_kg,
                'sample_size' => $record->sample_size,
            ])
            ->all();

        return [
            'generated_at' => $now->toIso8601String(),
            'farm' => ['name' => $farm->name, 'timezone' => $farm->timezone],
            'inventory' => [
                'individuals' => $farm->animals()->notArchived()->count(),
                'active_batches' => $farm->batches()->where('status', 'active')->count(),
                'batch_population' => (int) $farm->batches()->where('status', 'active')->sum('current_count'),
                'animal_statuses' => $farm->animals()->notArchived()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($total): int => (int) $total)->all(),
                'species' => $species,
            ],
            'attention' => [
                'total' => (clone $attentionQuery)->count(),
                'records' => $attention,
            ],
            'reminders' => [
                'pending' => (clone $reminderQuery)->count(),
                'overdue' => (clone $reminderQuery)->whereDate('due_on', '<', $today)->count(),
                'due_today' => (clone $reminderQuery)->whereDate('due_on', $today)->count(),
                'records' => $reminders,
            ],
            'recent_care' => [
                'health_observations' => $recentHealth,
                'treatments' => $recentTreatments,
                'vaccinations' => $vaccinations,
                'deworming' => $deworming,
            ],
            'reproduction' => [
                'active_pregnancies' => (clone $pregnancyQuery)->count(),
                'records' => $pregnancies,
            ],
            'recent_weights' => $weights,
            'record_limit_per_section' => self::RECORD_LIMIT,
        ];
    }
}
