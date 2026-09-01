<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFreightCargoRequest extends FormRequest
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
            'cargo_type_id' => ['nullable', 'integer', 'exists:cargo_types,id'],
            'cargo_description' => ['nullable', 'string', 'max:1000'],
            'required_truck_type_id' => ['nullable', 'integer', 'exists:truck_types,id'],
            'truck_count' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $cargoType = $this->input('cargo_type_id');
            $desc = $this->input('cargo_description');
            if (empty($cargoType) && empty($desc)) {
                // Allow empty during draft step; submit will enforce
            }
        });
    }
}
