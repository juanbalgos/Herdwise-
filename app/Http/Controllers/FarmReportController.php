<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmReportRequest;
use App\Models\Farm;
use App\Models\Species;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmReportController extends Controller
{
    public function index(FarmReportRequest $request): View
    {
        $farm = $this->currentFarm($request);
        $report = $this->buildReport($farm, ...$this->period($request));

        return view('reports.index', [
            ...$report,
            'section' => $request->validated('section', 'all'),
        ]);
    }

    public function csv(FarmReportRequest $request): StreamedResponse
    {
        $farm = $this->currentFarm($request);
        [$startDate, $endDate] = $this->period($request);
        $section = $request->validated('section', 'all');
        $filename = $this->filename($farm, $startDate, $endDate, 'csv');

        return response()->streamDownload(function () use ($farm, $startDate, $endDate, $section): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            $this->putCsv($stream, ['Section', 'Record type', 'Livestock code', 'Species', 'Date', 'Summary', 'Status or value', 'Recorded by']);
            $this->writeCsvRows($stream, $farm, $startDate, $endDate, $section);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(FarmReportRequest $request): Response
    {
        $farm = $this->currentFarm($request);
        [$startDate, $endDate] = $this->period($request);
        $section = $request->validated('section', 'all');
        $report = $this->buildReport($farm, $startDate, $endDate);
        $html = view('reports.pdf', [...$report, 'section' => $section])->render();

        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $footerFont = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $dompdf->getCanvas()->page_text(38, 814, 'HerdWise - private farm record', $footerFont, 7, [0.38, 0.45, 0.42]);
        $dompdf->getCanvas()->page_text(500, 814, 'Page {PAGE_NUM} of {PAGE_COUNT}', $footerFont, 7, [0.38, 0.45, 0.42]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($farm, $startDate, $endDate, 'pdf').'"',
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function period(FarmReportRequest $request): array
    {
        return [
            CarbonImmutable::parse($request->validated('start_date', today()->subDays(29)->toDateString()))->startOfDay(),
            CarbonImmutable::parse($request->validated('end_date', today()->toDateString()))->endOfDay(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildReport(Farm $farm, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $individualSpecies = $farm->animals()
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
        $speciesBreakdown = Species::query()
            ->whereIn('id', $speciesIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Species $species) use ($individualSpecies, $batchSpecies): array {
                $batch = $batchSpecies->get($species->id);

                return [
                    'name' => $species->name,
                    'individuals' => (int) $individualSpecies->get($species->id, 0),
                    'batches' => (int) ($batch?->batch_count ?? 0),
                    'batch_population' => (int) ($batch?->population ?? 0),
                ];
            });

        $inventory = [
            'individuals' => $farm->animals()->count(),
            'active_batches' => $farm->batches()->where('status', 'active')->count(),
            'batch_population' => (int) $farm->batches()->where('status', 'active')->sum('current_count'),
            'statuses' => $farm->animals()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status'),
            'species' => $speciesBreakdown,
        ];

        $care = [
            'health' => $farm->healthRecords()->whereBetween('observed_on', [$startDate, $endDate])->count(),
            'treatments' => $farm->treatments()->whereBetween('treated_on', [$startDate, $endDate])->count(),
            'vaccinations' => $farm->vaccinations()->whereBetween('given_on', [$startDate, $endDate])->count(),
            'deworming' => $farm->dewormingRecords()->whereBetween('administered_on', [$startDate, $endDate])->count(),
            'high_priority' => $farm->healthRecords()->whereBetween('observed_on', [$startDate, $endDate])->whereIn('severity', ['high', 'critical'])->count(),
            'recent' => $farm->healthRecords()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('observed_on', [$startDate, $endDate])->latest('observed_on')->limit(8)->get(),
        ];
        $care['total'] = $care['health'] + $care['treatments'] + $care['vaccinations'] + $care['deworming'];

        $birthTotals = $farm->birthRecords()
            ->whereBetween('delivered_on', [$startDate, $endDate])
            ->selectRaw('COUNT(*) AS events, COALESCE(SUM(live_count), 0) AS live_count, COALESCE(SUM(stillborn_count), 0) AS stillborn_count')
            ->first();
        $reproduction = [
            'breeding' => $farm->breedingRecords()->whereBetween('occurred_on', [$startDate, $endDate])->count(),
            'pregnancy_checks' => $farm->pregnancyRecords()->whereBetween('checked_on', [$startDate, $endDate])->count(),
            'birth_events' => (int) $birthTotals->events,
            'live_births' => (int) $birthTotals->live_count,
            'stillborn' => (int) $birthTotals->stillborn_count,
            'active_pregnancies' => $farm->pregnancyRecords()->where('status', 'confirmed')->whereDoesntHave('birthRecords')->count(),
            'recent_births' => $farm->birthRecords()->with(['animal.species', 'recorder'])->whereBetween('delivered_on', [$startDate, $endDate])->latest('delivered_on')->limit(8)->get(),
        ];

        $weightQuery = $farm->weightRecords()->whereBetween('measured_on', [$startDate, $endDate]);
        $growth = [
            'measurements' => (clone $weightQuery)->count(),
            'individual_measurements' => (clone $weightQuery)->whereNotNull('animal_id')->count(),
            'batch_measurements' => (clone $weightQuery)->whereNotNull('livestock_batch_id')->count(),
            'average_weight' => (float) ((clone $weightQuery)->avg('weight_kg') ?? 0),
            'recent' => (clone $weightQuery)->with(['animal.species', 'batch.species', 'recorder'])->latest('measured_on')->limit(8)->get(),
        ];

        $pendingReminders = $farm->reminders()->where('status', 'pending');
        $reminders = [
            'pending' => (clone $pendingReminders)->count(),
            'overdue' => (clone $pendingReminders)->whereDate('due_on', '<', today())->count(),
            'due_in_period' => (clone $pendingReminders)->whereBetween('due_on', [$startDate, $endDate])->count(),
            'upcoming' => (clone $pendingReminders)->with(['animal.species', 'batch.species'])->orderBy('due_on')->limit(8)->get(),
        ];

        return compact('farm', 'startDate', 'endDate', 'inventory', 'care', 'reproduction', 'growth', 'reminders') + [
            'generatedAt' => now(),
        ];
    }

    /** @param resource $stream */
    private function writeCsvRows($stream, Farm $farm, CarbonImmutable $startDate, CarbonImmutable $endDate, string $section): void
    {
        if (in_array($section, ['all', 'inventory'], true)) {
            $farm->animals()->with('species')->lazyById(200)->each(function ($animal) use ($stream): void {
                $this->putCsv($stream, ['Inventory', 'Individual animal', $animal->animal_code, $animal->species->name, $animal->created_at->toDateString(), $animal->name ?: 'Registered animal', $animal->status, '']);
            });
            $farm->batches()->with('species')->lazyById(200)->each(function ($batch) use ($stream): void {
                $this->putCsv($stream, ['Inventory', 'Livestock batch', $batch->batch_code, $batch->species->name, $batch->started_on->toDateString(), ($batch->purpose ?: 'Registered batch').' - '.$batch->current_count.' current animals', $batch->status, '']);
            });
        }

        if (in_array($section, ['all', 'care'], true)) {
            $farm->healthRecords()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('observed_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Care', 'Health observation', $this->livestockCode($record), $this->livestockSpecies($record), $record->observed_on->toDateString(), $record->observation, $record->severity, $record->recorder->name]);
            });
            $farm->treatments()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('treated_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Care', 'Treatment', $this->livestockCode($record), $this->livestockSpecies($record), $record->treated_on->toDateString(), $record->reason, $record->medication ?: 'No medication entered', $record->recorder->name]);
            });
            $farm->vaccinations()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('given_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Care', 'Vaccination', $this->livestockCode($record), $this->livestockSpecies($record), $record->given_on->toDateString(), $record->vaccine_name, $record->dose ?: 'Dose not entered', $record->recorder->name]);
            });
            $farm->dewormingRecords()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('administered_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Care', 'Deworming', $this->livestockCode($record), $this->livestockSpecies($record), $record->administered_on->toDateString(), $record->product_name, $record->dose ?: 'Dose not entered', $record->recorder->name]);
            });
        }

        if (in_array($section, ['all', 'reproduction'], true)) {
            $farm->breedingRecords()->with(['femaleAnimal.species', 'recorder'])->whereBetween('occurred_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Reproduction', 'Breeding', $record->femaleAnimal->animal_code, $record->femaleAnimal->species->name, $record->occurred_on->toDateString(), str_replace('_', ' ', $record->method), $record->pregnancy_check_due_on?->toDateString() ?: 'No check scheduled', $record->recorder->name]);
            });
            $farm->pregnancyRecords()->with(['animal.species', 'recorder'])->whereBetween('checked_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Reproduction', 'Pregnancy check', $record->animal->animal_code, $record->animal->species->name, $record->checked_on->toDateString(), $record->check_method ?: 'Method not entered', $record->status, $record->recorder->name]);
            });
            $farm->birthRecords()->with(['animal.species', 'recorder'])->whereBetween('delivered_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $this->putCsv($stream, ['Reproduction', 'Birth outcome', $record->animal->animal_code, $record->animal->species->name, $record->delivered_on->toDateString(), $record->live_count.' live, '.$record->stillborn_count.' stillborn', str_replace('_', ' ', $record->outcome), $record->recorder->name]);
            });
        }

        if (in_array($section, ['all', 'growth'], true)) {
            $farm->weightRecords()->with(['animal.species', 'batch.species', 'recorder'])->whereBetween('measured_on', [$startDate, $endDate])->lazyById(200)->each(function ($record) use ($stream): void {
                $summary = $record->livestock_batch_id ? 'Sample size: '.($record->sample_size ?: 'not entered') : 'Individual measurement';
                $this->putCsv($stream, ['Growth', 'Weight measurement', $this->livestockCode($record), $this->livestockSpecies($record), $record->measured_on->toDateString(), $summary, number_format((float) $record->weight_kg, 2).' kg', $record->recorder->name]);
            });
        }

        if (in_array($section, ['all', 'reminders'], true)) {
            $farm->reminders()->with(['animal.species', 'batch.species', 'creator'])->whereBetween('due_on', [$startDate, $endDate])->lazyById(200)->each(function ($reminder) use ($stream): void {
                $this->putCsv($stream, ['Reminders', str_replace('_', ' ', $reminder->type), $this->livestockCode($reminder), $this->livestockSpecies($reminder), $reminder->due_on->toDateString(), $reminder->title, $reminder->status, $reminder->creator->name]);
            });
        }
    }

    /** @param resource $stream */
    private function putCsv($stream, array $values): void
    {
        $safeValues = array_map(function (mixed $value): string {
            $value = (string) $value;

            return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
        }, $values);

        fputcsv($stream, $safeValues, ',', '"', '');
    }

    private function livestockCode(object $record): string
    {
        return $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide';
    }

    private function livestockSpecies(object $record): string
    {
        return $record->animal?->species?->name ?? $record->batch?->species?->name ?? 'All livestock';
    }

    private function filename(Farm $farm, CarbonImmutable $startDate, CarbonImmutable $endDate, string $extension): string
    {
        return Str::slug($farm->name).'-report-'.$startDate->toDateString().'-'.$endDate->toDateString().'.'.$extension;
    }
}
