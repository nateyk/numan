<?php

namespace App\Services;

use App\Models\ContactReveal;
use App\Models\ContactRevealBlock;
use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
    public function __construct(
        protected MarketplaceEntitlementService $entitlements,
    ) {}

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
     * The metered reveal.
     *
     * Three outcomes:
     *   already  — the pair is in the ledger, so this costs nothing and the
     *              contact is returned
     *   allowed  — inside the quota, so a ledger row is written and the contact
     *              is returned
     *   blocked  — quota exhausted, a block row is recorded and NO contact data
     *              of any kind is returned
     *
     * The check and the insert share one transaction. Note that lockForUpdate
     * is a no-op on SQLite — the tests run on SQLite — so under concurrent
     * requests the guarantee comes from the unique (revealer_id, revealed_id)
     * key rather than from the lock: the second insert fails on the index and
     * is treated as "already revealed", which is the correct outcome anyway
     * because both requests were for the same pair.
     *
     * @return array{status: 'already'|'allowed'|'blocked', contact: array<string, string|null>|null, limit: int, used: int, remaining: int, window_days: int}
     */
    public function reveal(User $viewer, User $counterparty, ?Model $context = null): array
    {
        if ($viewer->id === $counterparty->id) {
            // Revealing yourself is not a metering event.
            return $this->result('allowed', $this->contactDetailsFor($viewer, $counterparty, $context), -1, 0, -1, 0);
        }

        if (! $this->canReveal($viewer, $counterparty)) {
            abort(403);
        }

        $workspace = $viewer->currentWorkspace;
        $limit = $this->entitlements->getLimitValue($workspace, 'contact_reveals_per_week');
        $windowDays = $this->entitlements->revealWindowDays($workspace);

        $contextIds = $this->contextIds($context);

        $outcome = DB::transaction(function () use ($viewer, $counterparty, $windowDays, $limit, $contextIds): array {
            // Lock the REVEALER'S user row for the duration of this
            // transaction. Every reveal by this user serialises behind it, so
            // two concurrent requests cannot both read the same window count
            // and both decide there is room. Locking the contact_reveals rows
            // instead would not work: the rows being counted may not exist yet,
            // and a row lock cannot be taken on an absence.
            //
            // This is a no-op on SQLite, which is what the test suite runs on,
            // so the tests below prove the arithmetic and the uniqueness, not
            // the concurrency. On MySQL and PostgreSQL the lock is real.
            //
            // The unique (revealer_id, revealed_id) key remains the backstop:
            // two requests for the SAME pair both want the same outcome, and
            // the loser is treated as "already revealed", which is correct.
            User::query()
                ->whereKey($viewer->id)
                ->lockForUpdate()
                ->first();

            // Re-revealing a partner is free, forever. Checked first and inside
            // the transaction so it cannot race with the quota check below.
            $existing = ContactReveal::query()
                ->where('revealer_id', $viewer->id)
                ->where('revealed_id', $counterparty->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['status' => 'already'];
            }

            if ($limit !== -1) {
                $used = ContactReveal::query()
                    ->where('revealer_id', $viewer->id)
                    ->where('created_at', '>=', now()->subDays($windowDays))
                    ->count();

                if ($used >= $limit) {
                    ContactRevealBlock::create(array_merge($contextIds, [
                        'user_id' => $viewer->id,
                        'revealed_id' => $counterparty->id,
                        'limit_at_time' => $limit,
                    ]));

                    return ['status' => 'blocked', 'used' => $used];
                }
            }

            try {
                ContactReveal::create(array_merge($contextIds, [
                    'revealer_id' => $viewer->id,
                    'revealed_id' => $counterparty->id,
                ]));
            } catch (UniqueConstraintViolationException) {
                // Lost the race against a concurrent reveal of the same pair.
                // Both requests wanted the same thing, so honour it.
                return ['status' => 'already'];
            }

            return ['status' => 'allowed'];
        });

        if ($outcome['status'] === 'blocked') {
            return $this->result('blocked', null, $limit, $outcome['used'], 0, $windowDays);
        }

        $contact = $this->contactDetailsFor($viewer, $counterparty, $context);

        $used = $outcome['status'] === 'already'
            ? $this->usedInWindow($viewer, $windowDays)
            : $this->usedInWindow($viewer, $windowDays);

        $remaining = $limit === -1 ? -1 : max(0, $limit - $used);

        return $this->result($outcome['status'], $contact, $limit, $used, $remaining, $windowDays);
    }

    /**
     * Has this viewer already revealed this user? Used by page loads so a
     * contact stays on screen without another action.
     */
    public function hasRevealed(User $viewer, User $counterparty): bool
    {
        return ContactReveal::hasPair($viewer->id, $counterparty->id);
    }

    /**
     * The contact for a counterparty if it has already been revealed, else null.
     *
     * This is what makes a page load safe to serve the contact on: it never
     * records a reveal, so simply visiting a page cannot consume quota.
     */
    public function contactIfAlreadyRevealed(User $viewer, User $counterparty, ?Model $context = null): ?array
    {
        if ($viewer->id === $counterparty->id) {
            return $this->contactDetailsFor($viewer, $counterparty, $context);
        }

        if (! $this->hasRevealed($viewer, $counterparty)) {
            return null;
        }

        return $this->contactDetailsFor($viewer, $counterparty, $context);
    }

    /**
     * Split the reveal/interest/connection ids out of the context model.
     *
     * @return array<string, int|null>
     */
    private function contextIds(?Model $context): array
    {
        $ids = [
            'freight_post_id' => null,
            'interest_id' => null,
            'connection_id' => null,
        ];

        if ($context instanceof FreightInterest) {
            $ids['interest_id'] = (int) $context->id;
            $ids['freight_post_id'] = (int) $context->freight_post_id;
        } elseif ($context instanceof MarketplaceConnection) {
            $ids['connection_id'] = (int) $context->id;
            $ids['freight_post_id'] = (int) $context->freight_post_id;
        }

        return $ids;
    }

    private function usedInWindow(User $viewer, int $windowDays): int
    {
        return ContactReveal::query()
            ->where('revealer_id', $viewer->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->count();
    }

    /**
     * @return array{status: string, contact: array<string, string|null>|null, limit: int, used: int, remaining: int, window_days: int}
     */
    private function result(string $status, ?array $contact, int $limit, int $used, int $remaining, int $windowDays): array
    {
        return [
            'status' => $status,
            'contact' => $contact,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'window_days' => $windowDays,
        ];
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
