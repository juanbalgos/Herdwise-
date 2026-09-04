<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVaccinationRequest extends FormRequest
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
        return ['subject_type' => ['required', Rule::in(['animal', 'batch'])], 'subject_id' => ['required', 'integer'], 'vaccine_name' => ['required', 'string', 'max:160'], 'given_on' => ['required', 'date', 'before_or_equal:today'], 'dose' => ['nullable', 'string', 'max:120'], 'administered_by' => ['nullable', 'string', 'max:120'], 'next_due_on' => ['nullable', 'date', 'after:given_on'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
