<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approving or rejecting a carrier listing.
 *
 * A listing belongs to a profile that provides transport. Rejecting one always
 * needs a reason, because the carrier is told to fix it and resubmit.
 */
class ModerateProfileListingRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in([ProfileStatus::Approved->value, ProfileStatus::Rejected->value]),
            ],
            'reason' => ['required_if:status,'.ProfileStatus::Rejected->value, 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Give the carrier a reason when rejecting the listing.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $profile = $this->route('profile');

            if ($profile instanceof Profile && ! $profile->provides_transport) {
                $validator->errors()->add(
                    'status',
                    'This profile does not provide transport, so it has no listing to moderate.',
                );
            }
        });
    }
}
