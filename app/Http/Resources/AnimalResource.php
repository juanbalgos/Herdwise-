<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'animal_code' => $this->animal_code, 'ear_tag' => $this->ear_tag, 'name' => $this->name, 'species' => $this->species->name, 'breed' => $this->breed?->name, 'sex' => $this->sex, 'birth_date' => $this->birth_date?->toDateString(), 'status' => $this->status, 'location' => $this->location];
    }
}
