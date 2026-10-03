<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFreightInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Contact and bank fields were removed.
     *
     * Contact details are read from the user's profile and released through
     * the reveal action, so an interest carries only a message. NumanFreight
     * handles no payments, so bank details are never collected at all. The
     * corresponding columns remain in the database but are never written.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
