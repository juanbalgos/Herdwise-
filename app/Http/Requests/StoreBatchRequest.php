<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0));

        return $farm !== null && $farm->pivot->role !== 'veterinarian';
    }

    public function rules(): array
    {
        $farmId = $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0))?->id ?? 0;

        return [
            'batch_code' => ['required', 'string', 'max:40', Rule::unique('livestock_batches', 'batch_code')->where('farm_id', $farmId)],
            'species_id' => ['required', Rule::exists('species', 'id')->where('supports_batches', true)],
            'breed_id' => ['nullable', Rule::exists('breeds', 'id')->where('species_id', $this->integer('species_id'))],
            'started_on' => ['required', 'date', 'before_or_equal:today'],
            'initial_count' => ['required', 'integer', 'between:1,1000000'],
            'current_count' => ['required', 'integer', 'min:0', 'lte:initial_count'],
            'purpose' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'under_observation', 'quarantined', 'closed'])],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_code.unique' => 'This batch ID is already used on the current farm.',
            'breed_id.exists' => 'Choose a breed that belongs to the selected species.',
            'species_id.exists' => 'Choose a species that supports batch records.',
        ];
    }
}
