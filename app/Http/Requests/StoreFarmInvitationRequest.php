<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFarmInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->string('email')->trim()->lower()->toString()]);
    }

    public function authorize(): bool
    {
        $farm = $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0));

        return $farm?->pivot?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'expires_in_days' => ['required', 'integer', 'between:1,30'],
        ];
    }
}
