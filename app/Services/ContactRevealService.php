<?php

namespace App\Services;

use App\Models\ContactReveal;
use App\Models\ContactRevealBlock;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether one user may see another user's contact details.
 *
 * Gated on a real relationship: a live interest, an existing connection, or a
 * freight listing owned by the counterparty. Metering is layered on top without
 * changing the gate. The listing branch is context-scoped, so it cannot widen
 * the interest or connection endpoints.
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
     *
     * $context is the listing, interest or connection the reveal came from. A
     * listing opens the gate only when it belongs to the counterparty; passing
     * somebody else's post must not reveal a third party.
     */
    public function canReveal(User $viewer, User $counterparty, ?Model $context = null): bool
    {
        if ($viewer->id === $counterparty->id) {
            return true;
        }

        if ($context instanceof FreightPost && (int) $context->user_id === (int) $counterparty->id) {
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
     * @return array{status: 'already'|'allowed'|'blocked', contact: array<string, string|null>|null, limit: int, used: int, remaining: int}
     */
    public function reveal(User $viewer, User $counterparty, ?Model $context = null): array
    {
        if ($viewer->id === $counterparty->id) {
            // Revealing yourself is not a metering event.
            return $this->result('allowed', $this->contactDetailsFor($viewer, $counterparty, $context), -1, 0, -1);
        }

        if (! $this->canReveal($viewer, $counterparty, $context)) {
            abort(403);
        }

        $workspace = $viewer->currentWorkspace;
        $limit = $this->entitlements->getLimitValue($workspace, 'contact_reveals');

        $contextIds = $this->contextIds($context);

        $outcome = DB::transaction(function () use ($viewer, $counterparty, $limit, $contextIds): array {
            // Lock the REVEALER'S user row for the duration of this
            // transaction. Every reveal by this user serialises behind it, so
            // two concurrent requests cannot both read the same count
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
            // the transaction so it cannot race with the allowance check below.
            $existing = ContactReveal::query()
                ->where('revealer_id', $viewer->id)
                ->where('revealed_id', $counterparty->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['status' => 'already'];
            }

            if ($limit !== -1) {
                // All-time, deliberately. The free allowance is five reveals
                // ever, not five per week. No time-based reset exists.
                $used = ContactReveal::query()
                    ->where('revealer_id', $viewer->id)
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
            return $this->result('blocked', null, $limit, $outcome['used'], 0);
        }

        $contact = $this->contactDetailsFor($viewer, $counterparty, $context);

        $used = $this->usedReveals($viewer);

        $remaining = $limit === -1 ? -1 : max(0, $limit - $used);

        return $this->result($outcome['status'], $contact, $limit, $used, $remaining);
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
        } elseif ($context instanceof FreightPost) {
            $ids['freight_post_id'] = (int) $context->id;
        }

        return $ids;
    }

    private function usedReveals(User $viewer): int
    {
        return ContactReveal::query()
            ->where('revealer_id', $viewer->id)
            ->count();
    }

    /**
     * @return array{status: string, contact: array<string, string|null>|null, limit: int, used: int, remaining: int}
     */
    private function result(string $status, ?array $contact, int $limit, int $used, int $remaining): array
    {
        return [
            'status' => $status,
            'contact' => $contact,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
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
     * $context is the listing, interest or connection the reveal came from. It
     * decides whether a listing opens the gate, and supplies the legacy step-3
     * fallback. Without it the fallback is skipped.
     *
     * @return array<string, string|null>|null
     */
    public function contactDetailsFor(User $viewer, User $counterparty, ?Model $context = null): ?array
    {
        if (! $this->canReveal($viewer, $counterparty, $context)) {
            return null;
        }

        // A missing profile row is not a reason to withhold contact details.
        //
        // This used to read `if (! $profile) { return null; }`, which returned
        // nothing for every user registered before public_profiles existed. On
        // production that is 18 of 21 accounts — and all 11 live freight
        // interests involve one. The marketplace's core mechanic had therefore
        // never once succeeded on real data: a consignor and a carrier could
        // express interest to each other and then neither could see the other's
        // number, with no error to say why.
        //
        // It hid cleanly because every other test built its profiles by hand.
        // The identity falls back to the user record and, for a carrier, to the
        // carrier record — which is where all four production carriers keep
        // their names and numbers, since none of them has a profile row.
        $profile = $counterparty->profile()->first();

        $transporter = $counterparty->transporterProfile()->first();

        // Refusal is read from the profile, which every kind of user has.
        //
        // This used to be `$transporter !== null && ! $transporter->phone_visible`,
        // which meant a consignor — no transporter row — could never refuse, and
        // the reveal ledger logged the release of their number as though it had
        // been agreed. Silently publishing somebody who has no way to decline is
        // worse than not publishing.
        //
        // Both sources are honoured and a refusal from either wins. The profile is
        // where the choice is made now; the transporter column still carries it
        // until it is dropped, and honouring a stale "no" is free while honouring a
        // stale "yes" would publish a number somebody asked to keep private.
        $phoneRefused = $this->refusesContact(
            $profile?->phone_visible,
            $transporter?->phone_visible,
        );

        // Falls through profile, then carrier, then the user record. All three
        // hold real numbers for real people; none of them is reliably present.
        $phone = $phoneRefused
            ? null
            : (filled($profile?->phone)
                ? $profile->phone
                : (filled($transporter?->phone)
                    ? $transporter->phone
                    : $counterparty->phone));

        // Only fall back when the number was merely absent, never when it was
        // explicitly refused.
        if (blank($phone) && ! $phoneRefused) {
            $phone = $this->legacyInterestPhone($counterparty, $context);
        }

        $emailRefused = $this->refusesContact(
            $profile?->email_visible,
            $transporter?->email_visible,
        );
        $whatsappRefused = $this->refusesContact(
            $profile?->whatsapp_visible,
            $transporter?->whatsapp_visible,
        );

        $email = $emailRefused ? null : ($transporter?->email ?: $counterparty->email);
        $whatsapp = $whatsappRefused
            ? null
            : (filled($profile?->whatsapp) ? $profile->whatsapp : $transporter?->whatsapp);

        return [
            // The carrier record is the only name all four production carriers
            // have, so it is consulted before falling back to the account name.
            'name' => filled($profile?->display_name)
                ? $profile->display_name
                : (filled($transporter?->display_name)
                    ? $transporter->display_name
                    : $counterparty->name),
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
    /**
     * Whether either record says "do not publish this".
     *
     * The profile is the current home of the choice; the transporter column is a
     * copy kept in step until it is dropped. Both are consulted and a refusal from
     * either one stands.
     *
     * Asymmetric on purpose. A stale "no" costs a transporter one phone number
     * being releasable through the legacy fallback, which they can change in a
     * form. A stale "yes" — which is what honouring a stale refusal incorrectly in
     * the other direction would produce — publishes a number somebody asked to
     * keep private, and there is no way for them to learn that happened.
     *
     * A null from either side means the column is absent rather than set, which is
     * not a refusal.
     *
     * Compared as truthiness rather than `=== false` because the value comes back
     * from the database driver as an int 0 or the strings '0' and 'false'. Only
     * 'false' is a false positive — those three all mean "no".
     */
    private function refusesContact(mixed $profileFlag, mixed $transporterFlag): bool
    {
        foreach ([$profileFlag, $transporterFlag] as $flag) {
            if ($flag === null) {
                continue;
            }

            if (! $flag) {
                return true;
            }
        }

        return false;
    }

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
