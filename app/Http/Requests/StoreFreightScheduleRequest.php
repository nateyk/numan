<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFreightScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pickup_date' => ['nullable', 'date', 'after:today'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'pickup_date_flexibility_days' => ['nullable', 'integer', 'between:0,30'],
            'schedule_type' => ['nullable', Rule::in(['one_time', 'recurring'])],
        ];
    }
}
