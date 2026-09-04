<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LivestockBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'batch_code' => $this->batch_code, 'species' => $this->species->name, 'breed' => $this->breed?->name, 'started_on' => $this->started_on->toDateString(), 'initial_count' => $this->initial_count, 'current_count' => $this->current_count, 'purpose' => $this->purpose, 'status' => $this->status, 'location' => $this->location];
    }
}
