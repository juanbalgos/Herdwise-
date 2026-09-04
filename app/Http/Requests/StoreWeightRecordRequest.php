<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeightRecordRequest extends FormRequest
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
        $role = $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0))?->pivot?->role;

        return in_array($role, ['owner', 'manager', 'staff'], true);
    }

    public function rules(): array
    {
        return ['subject_type' => ['required', Rule::in(['animal', 'batch'])], 'subject_id' => ['required', 'integer'], 'measured_on' => ['required', 'date', 'before_or_equal:today'], 'weight_kg' => ['required', 'numeric', 'between:0.01,99999.99'], 'sample_size' => [Rule::requiredIf($this->input('subject_type') === 'batch'), Rule::prohibitedIf($this->input('subject_type') === 'animal'), 'nullable', 'integer', 'between:1,10000'], 'measurement_method' => ['nullable', 'string', 'max:120'], 'next_check_on' => ['nullable', 'date', 'after:measured_on'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
