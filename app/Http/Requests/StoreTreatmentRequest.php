<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentRequest extends FormRequest
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
        $role = $this->user()?->farms()->wherePivot('is_active', true)->first()?->pivot?->role;

        return in_array($role, ['owner', 'manager', 'veterinarian'], true);
    }

    public function rules(): array
    {
        return ['subject_type' => ['required', Rule::in(['animal', 'batch'])], 'subject_id' => ['required', 'integer'], 'health_record_id' => ['nullable', 'integer'], 'treated_on' => ['required', 'date', 'before_or_equal:today'], 'reason' => ['required', 'string', 'max:255'], 'diagnosis' => ['nullable', 'string', 'max:2000'], 'medication' => ['nullable', 'string', 'max:255'], 'dosage' => ['nullable', 'string', 'max:120'], 'duration_days' => ['nullable', 'integer', 'between:1,365'], 'veterinarian_name' => ['nullable', 'string', 'max:120'], 'follow_up_on' => ['nullable', 'date', 'after_or_equal:treated_on'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
