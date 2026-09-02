<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransporterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-\(\)]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-\(\)]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'is_company' => ['nullable', 'boolean'],
            'primary_location_id' => ['required', 'integer', 'exists:locations,id'],
            'truck_type_ids' => ['nullable', 'array', 'max:10'],
            'truck_type_ids.*' => ['integer', 'exists:truck_types,id'],
            'routes' => ['nullable', 'array', 'max:5'],
            'routes.*.origin_location_id' => ['required_with:routes', 'integer', 'exists:locations,id'],
            'routes.*.destination_location_id' => ['required_with:routes', 'integer', 'exists:locations,id', 'different:routes.*.origin_location_id'],
            'phone_visible' => ['nullable', 'boolean'],
            'whatsapp_visible' => ['nullable', 'boolean'],
            'email_visible' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array', 'max:3'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'documents_types' => ['nullable', 'array'],
            'documents_types.*' => ['string', Rule::in(['license', 'insurance', 'other'])],
        ];
    }

    public function messages(): array
    {
        return [
            'display_name.required' => 'Please enter a display name.',
            'primary_location_id.required' => 'Please select a primary location.',
            'primary_location_id.exists' => 'The selected location does not exist.',
            'truck_type_ids.*.exists' => 'One of the selected truck types is invalid.',
            'routes.*.origin_location_id.required_with' => 'Origin is required for each route.',
            'routes.*.destination_location_id.different' => 'Origin and destination must be different.',
            'documents.*.mimes' => 'Documents must be PDF, JPG or PNG.',
            'documents.*.max' => 'Each document must not exceed 5MB.',
        ];
    }
}
