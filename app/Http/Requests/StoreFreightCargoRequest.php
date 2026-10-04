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
     * The fields the cargo step collects.
     *
     * cargo_description, insurance_required, insurance_value and
     * depreciation_rate were dropped from this step: item_name is now the single
     * description of what is being loaded, and the cargo_type_id row replaces
     * the free-text description entirely. The insurance and depreciation columns
     * are gone from the UI because nothing downstream reads them, and a field
     * nobody reads is a field nobody fills in correctly.
     *
     * Those columns are deliberately still fillable on FreightPost. Historical
     * rows carry cargo_description, and the admin freight search still matches
     * on it, so dropping it from $fillable would make those rows unsearchable and
     * would break the API for any client that still sends it. They are simply not
     * in rules(), so they are not accepted from this form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cargo_type_id' => ['required', 'integer', 'exists:cargo_types,id'],
            'item_name' => ['required', 'string', 'max:1000'],
            'actual_weight' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'weight_unit' => ['required', 'string', 'in:kg,quintal,ton'],
            'tariff_weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'tariff_weight_unit' => ['nullable', 'string', 'in:kg,quintal,ton'],
            'tariff_amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'tariff_currency' => ['required', 'string', 'in:ETB,USD'],
            'required_truck_type_id' => ['required', 'integer', 'exists:truck_types,id'],
            'truck_count' => ['required', 'integer', 'between:1,50'],
        ];
    }

    public function messages(): array
    {
        return [
            'cargo_type_id.required' => 'Please select a cargo type.',
            'item_name.required' => 'Please describe the item to be loaded.',
            'actual_weight.required' => 'Please enter the actual weight.',
            'weight_unit.required' => 'Please select a weight unit.',
            'tariff_amount.required' => 'Please enter the tariff amount.',
            'tariff_currency.required' => 'Please select a currency.',
            'required_truck_type_id.required' => 'Please select a truck type.',
            'truck_count.required' => 'Please enter the number of trucks.',
        ];
    }
}
