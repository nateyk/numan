<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\LegalForm;
use App\Models\Location;
use App\Services\PhoneNumberService;
use Closure;
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
            'phone' => ['required_without:phone_numbers', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()\.]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()\.]{6,32}$/'],
            'phone_numbers' => ['required_without:phone', 'array', 'min:1', 'max:20'],
            'phone_numbers.*' => ['bail', 'required', 'string', 'max:32', 'distinct', $this->contactNumberRule()],
            'whatsapp_numbers' => ['sometimes', 'array', 'max:20'],
            'whatsapp_numbers.*' => ['bail', 'required', 'string', 'max:32', 'distinct', $this->contactNumberRule()],
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
            'phone.required_without' => 'Add at least one phone number.',
            'phone_numbers.required_without' => 'Add at least one phone number.',
            'phone_numbers.min' => 'Add at least one phone number.',
            'phone_numbers.*.distinct' => 'This phone number is already listed.',
            'whatsapp_numbers.*.distinct' => 'This WhatsApp number is already listed.',
            'phone.regex' => 'That does not look like a phone number.',
            'whatsapp.regex' => 'That does not look like a WhatsApp number.',
            'primary_location_id.exists' => 'That location does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phones = app(PhoneNumberService::class);

        foreach (['phone_numbers', 'whatsapp_numbers'] as $field) {
            $values = $this->input($field);

            if (is_array($values)) {
                $this->merge([$field => array_map(
                    fn (mixed $value): mixed => is_string($value) ? ($phones->normalizeContact($value) ?? $value) : $value,
                    array_values($values),
                )]);
            }
        }
    }

    private function contactNumberRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (app(PhoneNumberService::class)->normalizeContact($value) === null) {
                $fail('Enter a valid number with its country code, for example +251911234567.');
            }
        };
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
