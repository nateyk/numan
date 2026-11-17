<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChapaPaymentSetting;
use App\Services\ChapaConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChapaPaymentSettingsController extends Controller
{
    public function index(ChapaConfiguration $configuration): Response
    {
        $stored = ChapaPaymentSetting::query()->find(1);

        // Only status flags and the public endpoint are sent to Inertia;
        // decrypted keys must never be sent back to the browser.
        $sources = [];
        foreach ([
            'publicKey' => 'public_key',
            'secretKey' => 'secret_key',
            'webhookSecret' => 'webhook_secret',
        ] as $key => $column) {
            $sources[$key] = filled($stored?->$column)
                ? 'dashboard'
                : ($configuration->configured($key) ? 'environment' : 'missing');
        }

        return Inertia::render('admin/chapa-settings', [
            'settings' => [
                'mode' => $configuration->mode(),
                'public_key_source' => $sources['publicKey'],
                'secret_key_source' => $sources['secretKey'],
                'webhook_secret_source' => $sources['webhookSecret'],
                'last_updated' => $stored?->updated_at?->toISOString(),
            ],
            'webhook_url' => route('webhooks.chapa'),
        ]);
    }

    public function update(Request $request, ChapaConfiguration $configuration): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'public_key' => ['nullable', 'string', 'max:512'],
            'secret_key' => ['nullable', 'string', 'max:512'],
            'webhook_secret' => ['nullable', 'string', 'min:12', 'max:512'],
        ]);

        $mode = $validated['mode'];
        $incoming = [];
        foreach (['public_key', 'secret_key', 'webhook_secret'] as $field) {
            $incoming[$field] = trim((string) ($validated[$field] ?? ''));
        }

        // A key that belongs to Test Mode must never be used in Live Mode
        // (or vice versa). Require fresh credentials when switching modes,
        // rather than silently retaining keys from the old environment.
        if ($mode !== $configuration->mode()
            && (blank($incoming['secret_key']) || blank($incoming['public_key']) || blank($incoming['webhook_secret']))) {
            throw ValidationException::withMessages([
                'mode' => 'Switching Chapa mode requires a new public key, secret key and webhook secret for that mode.',
            ]);
        }

        foreach ([
            'public_key' => 'CHAPUBK',
            'secret_key' => 'CHASECK',
        ] as $field => $prefix) {
            $key = $incoming[$field];
            if ($key === '') {
                continue;
            }

            if (! preg_match('/^'. $prefix .'[_-](TEST|LIVE)[_-]/i', $key, $matches)) {
                throw ValidationException::withMessages([
                    $field => 'Enter a valid Chapa '.str_replace('_', ' ', $field).' for the selected mode.',
                ]);
            }

            if (strtolower($matches[1]) !== $mode) {
                throw ValidationException::withMessages([
                    $field => 'This key belongs to a different Chapa mode.',
                ]);
            }
        }

        DB::transaction(function () use ($mode, $incoming, $request): void {
            $settings = ChapaPaymentSetting::query()->find(1) ?? new ChapaPaymentSetting();
            $settings->id = 1;
            $settings->mode = $mode;

            foreach ($incoming as $field => $value) {
                if ($value !== '') {
                    $settings->$field = $value;
                }
            }

            $settings->updated_by = $request->user()->id;
            $settings->save();
        });

        return back()->with('success', 'Chapa settings saved. Stored keys remain hidden.');
    }
}
