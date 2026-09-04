<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeightRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'subject' => $this->animal ? ['type' => 'animal', 'id' => $this->animal->id, 'code' => $this->animal->animal_code, 'species' => $this->animal->species->name] : ['type' => 'batch', 'id' => $this->batch->id, 'code' => $this->batch->batch_code, 'species' => $this->batch->species->name], 'measured_on' => $this->measured_on->toDateString(), 'weight_kg' => (float) $this->weight_kg, 'sample_size' => $this->sample_size, 'measurement_method' => $this->measurement_method, 'next_check_on' => $this->next_check_on?->toDateString()];
    }
}
