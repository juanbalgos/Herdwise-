<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBirthRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->farms()->wherePivot('is_active', true)->exists() ?? false;
    }

    public function rules(): array
    {
        return ['animal_id' => ['required', 'integer'], 'pregnancy_record_id' => ['nullable', 'integer'], 'delivered_on' => ['required', 'date', 'before_or_equal:today'], 'outcome' => ['required', Rule::in(['live_birth', 'stillbirth', 'mixed'])], 'offspring_count' => ['required', 'integer', 'between:1,100'], 'live_count' => ['required', 'integer', 'min:0'], 'stillborn_count' => ['required', 'integer', 'min:0'], 'male_count' => ['nullable', 'integer', 'min:0'], 'female_count' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['offspring_count', 'live_count', 'stillborn_count', 'male_count', 'female_count'])) {
                return;
            }

            if ($this->integer('live_count') + $this->integer('stillborn_count') !== $this->integer('offspring_count')) {
                $validator->errors()->add('offspring_count', 'The offspring total must equal the live and stillborn counts.');
            }

            if ($this->input('outcome') === 'live_birth' && $this->integer('stillborn_count') > 0) {
                $validator->errors()->add('outcome', 'Choose mixed outcome when both live and stillborn offspring are recorded.');
            }

            if ($this->input('outcome') === 'stillbirth' && $this->integer('live_count') > 0) {
                $validator->errors()->add('outcome', 'Choose mixed outcome when both live and stillborn offspring are recorded.');
            }

            if ($this->input('outcome') === 'mixed' && ($this->integer('live_count') === 0 || $this->integer('stillborn_count') === 0)) {
                $validator->errors()->add('outcome', 'A mixed outcome requires both live and stillborn offspring.');
            }

            if ($this->integer('male_count') + $this->integer('female_count') > $this->integer('live_count')) {
                $validator->errors()->add('male_count', 'Known male and female counts cannot exceed the live offspring count.');
            }
        }];
    }
}
