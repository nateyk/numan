<?php

namespace App\Services;

use App\Models\FreightInterest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The one contact-resolution path.
 *
 * Every caller that releases a counterparty's contact details goes through
 * here: the metered reveal service and the connection pages. When the
 * transporter record and the public profile both held a phone, each caller read
 * a different one, so the same person could appear reachable on a listing and
 * unreachable on a connection.
 *
 * The profile is the only source now. The legacy per-interest phone remains as a
 * last resort for rows created before contact collection moved to the profile.
 * Bank columns are never read.
 *
 * This class does not decide whether a viewer is entitled to the contact — that
 * is the relationship gate in the caller. It decides what may be released once
 * the gate has opened, and a refusal is absolute: a hidden channel stays hidden
 * even when another source has a value for it.
 */
class ContactDetailsResolver
{
    /**
     * @return array{name: string, phone: ?string, email: ?string, whatsapp: ?string, phone_numbers: list<string>, whatsapp_numbers: list<string>}
     */
    public function resolve(User $counterparty, ?Model $context = null): array
    {
        $profile = $counterparty->profile()->first();

        $phoneRefused = $this->refuses($profile?->phone_visible);
        $emailRefused = $this->refuses($profile?->email_visible);
        $whatsappRefused = $this->refuses($profile?->whatsapp_visible);

        // A missing profile is not a refusal. Users registered before profiles
        // existed still have a name and an email on the account, and withholding
        // those would make every legacy relationship unreachable.
        $phone = $phoneRefused
            ? null
            : ($profile?->phone ?: $this->legacyInterestPhone($counterparty, $context));

        $email = $emailRefused
            ? null
            : ($profile?->email ?: $counterparty->email);

        return [
            'name' => filled($profile?->display_name)
                ? $profile->display_name
                : $counterparty->name,
            'phone' => filled($phone) ? $phone : null,
            'phone_numbers' => $phoneRefused ? [] : array_values(array_unique(array_filter([$phone, ...array_slice($profile?->phone_numbers ?? [], 1)]))),
            'whatsapp_numbers' => $whatsappRefused ? [] : array_values(array_unique(array_filter([$profile?->whatsapp, ...array_slice($profile?->whatsapp_numbers ?? [], 1)]))),
            'email' => filled($email) ? $email : null,
            'whatsapp' => $whatsappRefused || blank($profile?->whatsapp)
                ? null
                : $profile->whatsapp,
        ];
    }

    /**
     * The legacy per-interest phone, for the transporter side only.
     *
     * freight_interests.contact_phone used to be filled in by whoever submitted
     * the interest, so it only ever holds a transporter's number. It is a
     * last-resort fallback for legacy rows where the profile was never filled
     * in, and is not consulted for a consignor.
     */
    private function legacyInterestPhone(User $counterparty, ?Model $context): ?string
    {
        if (! $context instanceof FreightInterest || ! Schema::hasColumn('freight_interests', 'contact_phone')) {
            return null;
        }

        // Only the interest's own submitter has a legacy phone.
        if ((int) $context->user_id !== (int) $counterparty->id) {
            return null;
        }

        return filled($context->contact_phone) ? $context->contact_phone : null;
    }

    /**
     * Whether a visibility flag says "do not publish this".
     *
     * A null flag means the column is absent rather than set, which is not a
     * refusal. The value can come back from the database as a boolean, an int 0,
     * or the strings '0' and 'false', and all of those mean no.
     */
    private function refuses(mixed $flag): bool
    {
        if ($flag === null) {
            return false;
        }

        if (is_bool($flag)) {
            return ! $flag;
        }

        if (is_int($flag) || is_float($flag)) {
            return $flag == 0;
        }

        if (is_string($flag)) {
            return in_array(strtolower(trim($flag)), ['', '0', 'false'], true);
        }

        return ! $flag;
    }
}
