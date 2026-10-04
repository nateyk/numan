<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ManualPaymentExpiryService;
use App\Services\MarketplaceTextSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-editable payment instructions.
 *
 * One account per method. A method is offered to payers only when every field it
 * needs is filled in and enabled, so a half-configured account is never shown --
 * somebody would transfer money to it.
 */
class MarketplacePaymentInstructionsController extends Controller
{
    public function __construct(
        protected MarketplaceTextSettingsService $settings,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/marketplace-payment-instructions', [
            'settings' => $this->settings->all(),
            'methods' => MarketplaceTextSettingsService::REQUIRED_FIELDS,
            'available' => $this->settings->availableMethods(),
            'enabled' => $this->settings->manualPaymentEnabled(),
            'expiryDays' => app(ManualPaymentExpiryService::class)->windowDays(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'instructions' => ['nullable', 'string', 'max:5000'],
            'manual_payment_instructions_enabled' => ['nullable', 'boolean'],
        ];

        foreach (MarketplaceTextSettingsService::REQUIRED_FIELDS as $method => $keys) {
            foreach ($keys as $key) {
                // Nullable so a half-filled method can be saved without being
                // offered. The availability rule is what protects the payer.
                $rules[$key] = ['nullable', 'string', 'max:255'];
                $rules[$key.'_enabled'] = ['nullable', 'boolean'];
            }
        }

        $validated = $request->validate($rules, [
            'instructions.max' => 'Keep the general instructions under 5000 characters.',
        ]);

        $values = [];

        if (array_key_exists('instructions', $validated)) {
            $values['manual_payment_instructions'] = [
                'value' => $validated['instructions'],
                'is_enabled' => (bool) ($validated['manual_payment_instructions_enabled'] ?? false),
            ];
        }

        foreach (MarketplaceTextSettingsService::REQUIRED_FIELDS as $keys) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $validated)) {
                    continue;
                }

                $values[$key] = [
                    'value' => $validated[$key],
                    'is_enabled' => (bool) ($validated[$key.'_enabled'] ?? false),
                ];
            }
        }

        $this->settings->setMany($values, $request->user()?->id);

        return back()->with('success', 'Payment instructions updated.');
    }
}
