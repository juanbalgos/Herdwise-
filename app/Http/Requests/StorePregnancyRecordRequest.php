<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePregnancyRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->farms()->wherePivot('is_active', true)->first()?->pivot?->role;

        return in_array($role, ['owner', 'manager', 'veterinarian'], true);
    }

    public function rules(): array
    {
        return ['animal_id' => ['required', 'integer'], 'breeding_record_id' => ['nullable', 'integer'], 'checked_on' => ['required', 'date', 'before_or_equal:today'], 'status' => ['required', Rule::in(['confirmed', 'not_pregnant', 'uncertain'])], 'check_method' => ['nullable', 'string', 'max:120'], 'gestation_days' => ['nullable', 'integer', 'between:20,400'], 'expected_delivery_on' => ['nullable', 'date', 'after:checked_on'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
