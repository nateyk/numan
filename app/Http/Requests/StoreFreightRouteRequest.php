<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFreightRouteRequest extends FormRequest
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
            'origin_location_id' => ['required', 'integer', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id', 'different:origin_location_id'],
            'origin_exact_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_exact_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_exact_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_exact_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'origin_site_note' => ['nullable', 'string', 'max:500'],
            'destination_site_note' => ['nullable', 'string', 'max:500'],
            'origin_point_confirmed_at' => ['nullable', 'date'],
            'destination_point_confirmed_at' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after:today'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'pickup_date_flexibility_days' => ['nullable', 'integer', 'between:0,30'],
            'schedule_type' => ['nullable', Rule::in(['one_time', 'recurring'])],
        ];
    }

    public function messages(): array
    {
        return [
            'origin_location_id.required' => 'Choose an origin.',
            'destination_location_id.required' => 'Choose a destination.',
            'origin_location_id.different' => 'Origin and destination must be different.',
            'destination_location_id.different' => 'Origin and destination must be different.',
            'origin_exact_lat.between' => 'Origin latitude is invalid.',
            'pickup_date.after' => 'Pickup date must be in the future.',
        ];
    }
}
