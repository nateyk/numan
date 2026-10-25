<?php

namespace App\Http\Requests\Settings;

use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The marketplace half of a user's identity: how they are named, how they can be
 * reached, and whether they consent to being reached that way.
 *
 * These live on public_profiles rather than users, because users is the account
 * and this is the person as a counterparty sees them. They were previously only
 * reachable through the transporter form, which meant a consignor — who has all
 * the same identity fields and none of the transporter ones — could not set them
 * at all.
 */
class MarketplaceDetailsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actsInTheMarketplace = $this->actingInTheMarketplace();

        return [
            // Required only for somebody who has a capability. A user who is
            // neither a consignor nor a carrier is not reaching this form to trade,
            // and making them invent a phone number they will never publish is
            // friction for no benefit.
            'display_name' => [
                $actsInTheMarketplace ? 'required' : 'nullable',
                'string',
                'max:120',
            ],

            'phone' => [
                $actsInTheMarketplace ? 'required' : 'nullable',
                'string',
                'max:32',
                // Ethiopian numbers, tolerating the separators people actually type.
                // Not a hard gate on registration — a carrier without a number
                // cannot be contacted, but that is the completeness check's job,
                // not this form's.
                'regex:/^\+?[0-9\s\-()]{6,32}$/',
            ],

            'whatsapp' => [
                'nullable',
                'string',
                'max:32',
                // Optional: not everyone uses WhatsApp, and a consignor with no
                // number simply has no WhatsApp action on their listing.
                'regex:/^\+?[0-9\s\-()]{6,32}$/',
            ],

            'primary_location_id' => [
                'nullable',
                'integer',
                Rule::exists(Location::class, 'id'),
            ],

            'phone_visible' => ['nullable', 'boolean'],
            'whatsapp_visible' => ['nullable', 'boolean'],
            'email_visible' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Enter the name people will see when you post or offer a load.',
            'phone.required' => 'Enter a phone number — it is how a counterparty reaches you.',
            'phone.regex' => 'That does not look like a phone number.',
            'whatsapp.regex' => 'That does not look like a WhatsApp number.',
            'primary_location_id.exists' => 'That location does not exist.',
        ];
    }

    /**
     * Whether this user does anything in the marketplace at all.
     *
     * Capabilities are independent and a person can hold both, so this is an
     * "either" rather than a role lookup. Reading it from the database rather than
     * the request means a crafted payload cannot talk its way into skipping the
     * required fields.
     */
    private function actingInTheMarketplace(): bool
    {
        $profile = $this->user()?->profile()->first();

        return (bool) ($profile?->posts_freight || $profile?->provides_transport);
    }
}
