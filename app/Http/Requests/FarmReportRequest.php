<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FarmReportRequest extends FormRequest
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'section' => ['nullable', Rule::in(['all', 'inventory', 'care', 'reproduction', 'growth', 'reminders'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $startDate = CarbonImmutable::parse($this->input('start_date', today()->subDays(29)->toDateString()));
            $endDate = CarbonImmutable::parse($this->input('end_date', today()->toDateString()));

            if ($endDate->greaterThan($startDate->addDays(365))) {
                $validator->errors()->add('end_date', 'Choose a reporting period of 366 days or less.');
            }
        });
    }
}
