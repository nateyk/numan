<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileDocumentRequest extends FormRequest
{
    /**
     * The document types a public profile accepts.
     *
     * @var list<string>
     */
    public const TYPES = ['license', 'insurance', 'business_registration', 'id', 'other'];

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
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Documents must be PDF, JPG or PNG.',
            'file.max' => 'Each document must not exceed 5MB.',
        ];
    }
}
