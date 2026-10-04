<?php

namespace App\Http\Requests;

use App\Models\MarketplacePaymentTransaction;
use App\Services\ManualPaymentService;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Proof of a manual transfer.
 *
 * Two references, and the distinction matters:
 *   - the NF code is ours, generated, and already in the instructions token
 *   - the bank or Telebirr transaction id is theirs, and is what dedupes
 */
class SubmitManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller applies manageBilling, which is the real gate. This
        // request has no opinion of its own.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4000'],

            'method' => [
                'required',
                'string',
                Rule::in(MarketplacePaymentTransaction::MANUAL_METHODS),
            ],

            // What they typed into the banking app.
            'reference' => ['required', 'string', 'min:4', 'max:120'],

            'payer_phone' => $this->phones()->rules(required: true),

            'proof' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:'.ManualPaymentService::PROOF_MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => 'Enter the transaction reference from your transfer.',
            'payer_phone.required' => PhoneNumberService::INVALID_MESSAGE,
            'proof.mimetypes' => 'The screenshot must be a JPG, PNG or PDF.',
            'proof.max' => 'The screenshot must be 5 MB or smaller.',
            'method.in' => 'Choose one of the available payment methods.',
        ];
    }

    private function phones(): PhoneNumberService
    {
        return app(PhoneNumberService::class);
    }
}
