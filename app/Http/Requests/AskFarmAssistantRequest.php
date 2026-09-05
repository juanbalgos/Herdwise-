<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskFarmAssistantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->activeFarm((int) $this->session()->get('current_farm_id', 0)) !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:800'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'question.required' => 'Enter a question about this farm\'s records.',
            'question.max' => 'Keep the question to 800 characters or less.',
        ];
    }
}
