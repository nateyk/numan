<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\TruckType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The carrier half of the public profile.
 *
 * Intentionally partial: every field is optional so the page can save one
 * section without erasing another. Readiness, not request validation, decides
 * whether the carrier can express interest.
 */
class UpdateTransportProfileRequest extends FormRequest
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
            'truck_type_ids' => ['sometimes', 'nullable', 'array', 'max:10'],
            'truck_type_ids.*' => ['integer', Rule::exists(TruckType::class, 'id')],
            'routes' => ['sometimes', 'nullable', 'array', 'max:5'],
            'routes.*.origin_location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'routes.*.destination_location_id' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id'),
                'different:routes.*.origin_location_id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'truck_type_ids.*.exists' => 'One of the selected truck types is invalid.',
            'routes.*.origin_location_id.required' => 'Origin is required for each route.',
            'routes.*.destination_location_id.required' => 'Destination is required for each route.',
            'routes.*.destination_location_id.different' => 'Origin and destination must be different.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $routes = $this->input('routes');

            if (! is_array($routes)) {
                return;
            }

            $seen = [];

            foreach ($routes as $index => $route) {
                if (! is_array($route) || ! is_numeric($route['origin_location_id'] ?? null) || ! is_numeric($route['destination_location_id'] ?? null)) {
                    continue;
                }

                $key = (int) $route['origin_location_id'].':'.(int) $route['destination_location_id'];

                if (isset($seen[$key])) {
                    $validator->errors()->add("routes.{$index}.destination_location_id", 'This route has already been added.');
                }

                $seen[$key] = true;
            }
        });
    }
}
