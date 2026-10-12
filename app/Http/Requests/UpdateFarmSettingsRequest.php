<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFarmSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0));

        return $farm?->pivot?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}
