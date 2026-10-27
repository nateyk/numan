<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\LegalForm;
use App\Models\Location;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole identity card, as edited on /public-profile.
 *
 * Both capability booleans are required, and the after hook rejects a profile
 * that does neither: a person may post freight, carry freight, or both, but a
 * profile with no capability is a login rather than a marketplace identity.
 */
class UpdatePublicProfileRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()\.]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()\.]{6,32}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'primary_location_id' => ['nullable', 'integer', Rule::exists(Location::class, 'id')],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', Rule::in(LegalForm::values())],
            'legal_form_other' => ['nullable', 'string', 'max:255'],
            'phone_visible' => ['boolean'],
            'whatsapp_visible' => ['boolean'],
            'email_visible' => ['boolean'],
            'posts_freight' => ['required', 'boolean'],
            'provides_transport' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Enter the name people will see when you post or carry a load.',
            'account_type.required' => 'Choose whether this is a personal or business profile.',
            'phone.regex' => 'That does not look like a phone number.',
            'whatsapp.regex' => 'That does not look like a WhatsApp number.',
            'primary_location_id.exists' => 'That location does not exist.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('posts_freight') && ! $this->boolean('provides_transport')) {
                $validator->errors()->add(
                    'capabilities',
                    'Choose at least one: post freight, carry freight, or both.',
                );
            }
        });
    }
}
