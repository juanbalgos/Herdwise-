<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnimalRequest extends FormRequest
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
            'animal_code' => ['required', 'string', 'max:40', Rule::unique('animals', 'animal_code')->where('farm_id', $farmId)],
            'ear_tag' => ['nullable', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:80'],
            'species_id' => ['required', Rule::exists('species', 'id')->where('supports_individuals', true)],
            'breed_id' => ['nullable', Rule::exists('breeds', 'id')->where('species_id', $this->integer('species_id'))],
            'sex' => ['required', Rule::in(['male', 'female', 'unknown'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['healthy', 'under_observation', 'sick', 'under_treatment', 'pregnant', 'quarantined', 'sold', 'deceased'])],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') === 'pregnant' && $this->input('sex') !== 'female') {
                $validator->errors()->add('status', 'Only a female animal can have pregnant status.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'animal_code.unique' => 'This animal ID is already used on the current farm.',
            'breed_id.exists' => 'Choose a breed that belongs to the selected species.',
            'species_id.exists' => 'Choose a species that supports individual animal records.',
        ];
    }
}
