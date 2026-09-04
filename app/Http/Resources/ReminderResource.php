<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'due_on' => $this->due_on->toDateString(),
            'status' => $this->status,
            'priority' => $this->priority,
            'subject' => $this->animal
                ? ['type' => 'animal', 'id' => $this->animal->id, 'code' => $this->animal->animal_code]
                : ['type' => 'batch', 'id' => $this->batch->id, 'code' => $this->batch->batch_code],
        ];
    }
}
