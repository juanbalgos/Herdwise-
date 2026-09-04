<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'observed_on' => $this->observed_on->toDateString(),
            'observation' => $this->observation,
            'symptoms' => $this->symptoms,
            'severity' => $this->severity,
            'temperature' => $this->temperature,
            'appetite' => $this->appetite,
            'resulting_status' => $this->resulting_status,
            'subject' => $this->animal
                ? ['type' => 'animal', 'id' => $this->animal->id, 'code' => $this->animal->animal_code]
                : ['type' => 'batch', 'id' => $this->batch->id, 'code' => $this->batch->batch_code],
            'recorded_by' => $this->recorder->name,
        ];
    }
}
