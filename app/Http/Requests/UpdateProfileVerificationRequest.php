<?php

namespace App\Http\Requests;

use App\Enums\VerificationStatus;
use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approving, rejecting, or resetting the single verification badge.
 *
 * The badge covers the whole profile, so this decision is not split by
 * capability. A rejection needs a note: the owner reads it before resubmitting.
 */
class UpdateProfileVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderate', Profile::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(VerificationStatus::values())],
            'note' => ['required_if:status,'.VerificationStatus::Rejected->value, 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_if' => 'Tell the owner what to fix before they resubmit verification.',
        ];
    }
}
