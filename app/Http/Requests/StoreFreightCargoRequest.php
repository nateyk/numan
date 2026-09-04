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
            'item_name' => ['nullable', 'string', 'max:255'],
            'actual_weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'weight_unit' => ['nullable', 'string', 'in:kg,quintal,ton'],
            'tariff_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'tariff_currency' => ['nullable', 'string', 'in:ETB,USD'],
            'insurance_required' => ['nullable', 'boolean'],
            'insurance_value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
