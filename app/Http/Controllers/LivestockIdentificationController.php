<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\LivestockBatch;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LivestockIdentificationController extends Controller
{
    public function scanAnimal(Request $request, Animal $animal): RedirectResponse
    {
        $this->activateFarmContext($request, $animal->farm_id);
        $this->recordScan($request, $animal->farm_id, 'animal', $animal->id);

        return redirect()->route('animals.show', $animal);
    }

    public function scanBatch(Request $request, LivestockBatch $batch): RedirectResponse
    {
        $this->activateFarmContext($request, $batch->farm_id);
        $this->recordScan($request, $batch->farm_id, 'batch', $batch->id);

        return redirect()->route('batches.show', $batch);
    }

    public function animalQrCode(Request $request, Animal $animal): Response
    {
        $this->authorizeFarmMember($request, $animal->farm_id);

        return $this->qrCodeResponse(
            URL::signedRoute('identify.animals.scan', ['animal' => $animal]),
            'herdwise-animal-'.Str::slug($animal->animal_code).'.svg',
        );
    }

    public function batchQrCode(Request $request, LivestockBatch $batch): Response
    {
        $this->authorizeFarmMember($request, $batch->farm_id);

        return $this->qrCodeResponse(
            URL::signedRoute('identify.batches.scan', ['batch' => $batch]),
            'herdwise-batch-'.Str::slug($batch->batch_code).'.svg',
        );
    }

    public function animalPrintCard(Request $request, Animal $animal): View
    {
        $this->authorizeFarmMember($request, $animal->farm_id);
        $animal->load(['farm', 'species', 'breed']);

        return view('identification.print-card', [
            'kind' => 'Individual animal',
            'code' => $animal->animal_code,
            'displayName' => $animal->name,
            'farm' => $animal->farm,
            'species' => $animal->species->name,
            'breed' => $animal->breed?->name,
            'status' => str_replace('_', ' ', $animal->status),
            'details' => [
                ['label' => 'Sex', 'value' => ucfirst($animal->sex)],
                ['label' => 'Ear tag', 'value' => $animal->ear_tag ?: 'Not assigned'],
                ['label' => 'Birth date', 'value' => $animal->birth_date?->format('M j, Y') ?: 'Unknown'],
                ['label' => 'Farm location', 'value' => $animal->farm->location ?: 'Not specified'],
            ],
            'qrImageUrl' => route('animals.qr-code', $animal),
            'backUrl' => route('animals.show', $animal),
        ]);
    }

    public function batchPrintCard(Request $request, LivestockBatch $batch): View
    {
        $this->authorizeFarmMember($request, $batch->farm_id);
        $batch->load(['farm', 'species', 'breed']);

        return view('identification.print-card', [
            'kind' => 'Livestock batch',
            'code' => $batch->batch_code,
            'displayName' => $batch->species->name.' group',
            'farm' => $batch->farm,
            'species' => $batch->species->name,
            'breed' => $batch->breed?->name,
            'status' => str_replace('_', ' ', $batch->status),
            'details' => [
                ['label' => 'Started', 'value' => $batch->started_on->format('M j, Y')],
                ['label' => 'Initial population', 'value' => number_format($batch->initial_count)],
                ['label' => 'Current population', 'value' => number_format($batch->current_count)],
                ['label' => 'Farm location', 'value' => $batch->farm->location ?: 'Not specified'],
            ],
            'qrImageUrl' => route('batches.qr-code', $batch),
            'backUrl' => route('batches.show', $batch),
        ]);
    }

    private function authorizeFarmMember(Request $request, int $farmId): void
    {
        abort_unless(
            $request->user()->farms()->whereKey($farmId)->wherePivot('is_active', true)->exists(),
            404,
        );
    }

    private function qrCodeResponse(string $lookupUrl, string $filename): Response
    {
        $qrCode = new QrCode(
            data: $lookupUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 18,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(18, 60, 45),
            backgroundColor: new Color(255, 255, 255),
        );
        $result = (new SvgWriter)->write($qrCode);

        return response($result->getString(), 200, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Type' => $result->getMimeType(),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function recordScan(Request $request, int $farmId, string $livestockType, int $livestockId): void
    {
        $request->user()->accessEventsAsSubject()->create([
            'farm_id' => $farmId,
            'actor_user_id' => $request->user()->id,
            'event_type' => 'qr_scanned',
            'livestock_type' => $livestockType,
            'livestock_id' => $livestockId,
        ]);
    }
}
