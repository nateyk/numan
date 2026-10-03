<?php

namespace App\Services;

use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether one user may see another user's contact details.
 *
 * In 4a this is unmetered but already gated on a real relationship: a live
 * interest or an existing connection between the two users. Metering is layered
 * on top in 4b without changing the gate.
 *
 * Contact details are read from the person's profile and transporter profile.
 * They are never read from per-interest fields, which are no longer collected.
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
     * The counterparty's contact details, from their profile.
     *
     * @return array<string, string|null>
     */
    public function contactDetailsFor(User $viewer, User $counterparty): ?array
    {
        if (! $this->canReveal($viewer, $counterparty)) {
            return null;
        }

        $profile = $counterparty->profile()->first();

        if (! $profile) {
            return null;
        }

        $transporter = $counterparty->transporterProfile()->first();

        // Respect the transporter's own visibility flags.
        if ($transporter !== null) {
            $phone = $transporter->phone_visible ? ($transporter->phone ?: $profile->phone) : null;
            $email = $transporter->email_visible ? ($transporter->email ?: $counterparty->email) : null;
            $whatsapp = $transporter->whatsapp_visible ? $transporter->whatsapp : null;
        } else {
            // A consignor publishes their profile phone; their email is always
            // available to an accepted counterparty.
            $phone = $profile->phone;
            $email = $counterparty->email;
            $whatsapp = null;
        }

        return [
            'name' => $profile->display_name ?? $counterparty->name,
            'phone' => $phone,
            'email' => $email,
            'whatsapp' => $whatsapp,
        ];
    }
}
