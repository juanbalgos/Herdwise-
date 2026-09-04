<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBreedingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->farms()->wherePivot('is_active', true)->exists() ?? false;
    }

    public function rules(): array
    {
        return ['female_animal_id' => ['required', 'integer'], 'male_animal_id' => ['nullable', 'integer'], 'male_reference' => ['nullable', 'string', 'max:120'], 'heat_detected_on' => ['nullable', 'date', 'before_or_equal:occurred_on'], 'occurred_on' => ['required', 'date', 'before_or_equal:today'], 'method' => ['required', Rule::in(['natural', 'artificial_insemination'])], 'pregnancy_check_due_on' => ['nullable', 'date', 'after:occurred_on'], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('male_animal_id') && ! $this->filled('male_reference')) {
                $validator->errors()->add('male_reference', 'Select a male animal or enter an external male or semen reference.');
            }
        }];
    }
}
