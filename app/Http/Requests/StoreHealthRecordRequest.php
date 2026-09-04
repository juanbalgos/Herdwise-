<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthRecordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $parts = explode(':', (string) $this->input('target'), 2);
        if (count($parts) === 2) {
            $this->merge(['subject_type' => $parts[0], 'subject_id' => $parts[1]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->farms()->wherePivot('is_active', true)->exists() ?? false;
    }

    public function rules(): array
    {
        return ['subject_type' => ['required', Rule::in(['animal', 'batch'])], 'subject_id' => ['required', 'integer'], 'observed_on' => ['required', 'date', 'before_or_equal:today'], 'observation' => ['required', 'string', 'max:2000'], 'symptoms' => ['nullable', 'string', 'max:2000'], 'severity' => ['required', Rule::in(['low', 'moderate', 'high', 'critical'])], 'temperature' => ['nullable', 'numeric', 'between:20,50'], 'appetite' => ['nullable', Rule::in(['normal', 'reduced', 'none'])], 'behavior' => ['nullable', 'string', 'max:120'], 'resulting_status' => ['nullable', Rule::in(['healthy', 'under_observation', 'sick', 'under_treatment', 'pregnant', 'quarantined', 'active'])], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
