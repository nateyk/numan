<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Location;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The first-post dialog: enough profile to publish, nothing more.
 *
 * Posting is what the user clicked, so the capability is implied. Carrying is
 * optional and, when chosen, needs a base location before the profile is ready.
 */
class CompleteProfileSetupRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in(AccountType::values())],
            'phone' => app(PhoneNumberService::class)->rules(required: true),
            'provides_transport' => ['required', 'boolean'],
            'primary_location_id' => [
                Rule::requiredIf(fn (): bool => $this->boolean('provides_transport')),
                'nullable',
                'integer',
                Rule::exists(Location::class, 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Enter the name people will see when you post a load.',
            'account_type.required' => 'Choose whether this is a personal or business profile.',
            'phone.required' => 'Enter a phone number so a carrier can reach you.',
            'primary_location_id.required' => 'Choose your base location so carriers know where you run from.',
        ];
    }
}
