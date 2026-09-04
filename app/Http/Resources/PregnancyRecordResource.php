<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PregnancyRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'animal' => ['id' => $this->animal->id, 'code' => $this->animal->animal_code, 'species' => $this->animal->species->name], 'checked_on' => $this->checked_on->toDateString(), 'status' => $this->status, 'check_method' => $this->check_method, 'gestation_days' => $this->gestation_days, 'expected_delivery_on' => $this->expected_delivery_on?->toDateString(), 'is_system_estimate' => $this->is_estimated];
    }
}
