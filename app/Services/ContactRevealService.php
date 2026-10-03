<?php

namespace App\Services;

use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether one user may see another user's contact details.
 *
 * Gated on a real relationship: a live interest or an existing connection
 * between the two users. Metering is layered on top without changing the gate.
 *
 * Contact details come from the person's profile and transporter profile, with
 * the legacy per-interest phone as a last resort. Bank columns are never read.
 */
class ContactRevealService
{
    /**
     * Interest statuses that do not entitle either party to contact details.
     *
     * @var list<string>
     */
    public const BLOCKED_INTEREST_STATUSES = ['rejected', 'withdrawn'];

    /**
     * Does a live interest exist between these two users?
     */
    public function hasLiveInterest(int $aUserId, int $bUserId): bool
    {
        if (! Schema::hasTable('freight_interests')) {
            return false;
        }

        return FreightInterest::query()
            ->whereIn('user_id', [$aUserId, $bUserId])
            ->whereNotIn('status', self::BLOCKED_INTEREST_STATUSES)
            ->where(function ($query) use ($aUserId, $bUserId): void {
                $query->where(function ($inner) use ($aUserId, $bUserId): void {
                    $inner->whereIn('user_id', [$aUserId, $bUserId]);
                })->orWhereIn('freight_post_id', function ($postQuery) use ($aUserId, $bUserId): void {
                    $postQuery->select('id')
                        ->from('freight_posts')
                        ->whereIn('user_id', [$aUserId, $bUserId]);
                });
            })
            ->exists();
    }

    public function hasConnection(int $aUserId, int $bUserId): bool
    {
        if (! Schema::hasTable('marketplace_connections')) {
            return false;
        }

        return MarketplaceConnection::query()
            ->where(function ($query) use ($aUserId, $bUserId): void {
                $query
                    ->where(function ($inner) use ($aUserId, $bUserId): void {
                        $inner->where('consignor_user_id', $aUserId)
                            ->where('transporter_user_id', $bUserId);
                    })
                    ->orWhere(function ($inner) use ($aUserId, $bUserId): void {
                        $inner->where('consignor_user_id', $bUserId)
                            ->where('transporter_user_id', $aUserId);
                    });
            })
            ->exists();
    }

    /**
     * Is this user entitled to see the other's contact details?
     */
    public function canReveal(User $viewer, User $counterparty): bool
    {
        if ($viewer->id === $counterparty->id) {
            return true;
        }

        return $this->hasConnection($viewer->id, $counterparty->id)
            || $this->hasLiveInterest($viewer->id, $counterparty->id);
    }

    /**
     * The counterparty's contact details.
     *
     * Resolution order, exactly:
     *   1. the counterparty's profile phone
     *   2. their transporter profile phone
     *   3. the legacy FreightInterest.contact_phone — transporter side only,
     *      because that column was only ever filled in by the transporter
     *      submitting an interest
     *   4. their email address
     *
     * Bank columns are never read. NumanFreight handles no payments, so
     * freight_interests.bank_* has no consumer at all and must not become one.
     *
     * $context is the interest or connection the reveal came from, used only
     * for the legacy step-3 fallback. Without it the fallback is skipped.
     *
     * @return array<string, string|null>|null
     */
    public function contactDetailsFor(User $viewer, User $counterparty, ?Model $context = null): ?array
    {
        if (! $this->canReveal($viewer, $counterparty)) {
            return null;
        }

        $profile = $counterparty->profile()->first();

        if (! $profile) {
            return null;
        }

        $transporter = $counterparty->transporterProfile()->first();

        // A transporter who turned phone visibility off has said "do not
        // publish my number". That is an explicit refusal, not a gap to fill,
        // so it suppresses the number outright rather than falling through to
        // the legacy per-interest phone below.
        $phoneRefused = $transporter !== null && ! $transporter->phone_visible;

        $phone = $phoneRefused
            ? null
            : (filled($profile->phone)
                ? $profile->phone
                : $transporter?->phone);

        // Only fall back when the number was merely absent, never when it was
        // explicitly refused.
        if (blank($phone) && ! $phoneRefused) {
            $phone = $this->legacyInterestPhone($counterparty, $context);
        }

        if ($transporter !== null) {
            $email = $transporter->email_visible ? ($transporter->email ?: $counterparty->email) : null;
            $whatsapp = $transporter->whatsapp_visible ? $transporter->whatsapp : null;
        } else {
            $email = $counterparty->email;
            $whatsapp = null;
        }

        return [
            'name' => $profile->display_name ?? $counterparty->name,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'whatsapp' => $whatsapp ?: null,
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
        if ($context->user_id !== $counterparty->id) {
            return null;
        }

        return filled($context->contact_phone) ? $context->contact_phone : null;
    }
}
