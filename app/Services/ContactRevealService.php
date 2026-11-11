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
 * Contact details come from the person's single public profile, with the legacy
 * per-interest phone as a last resort. Bank columns are never read.
 */
class ContactRevealService
{
    public function __construct(
        protected MarketplaceEntitlementService $entitlements,
        protected ContactDetailsResolver $contacts,
    ) {}

    /**
     * Interest statuses that do not entitle either party to contact details.
     *
     * @var list<string>
     */
    public const BLOCKED_INTEREST_STATUSES = ['rejected', 'withdrawn'];

    /**
     * Does a live interest connect these two users?
     *
     * Both halves matter: one of them expressed the interest, and the other
     * owns the freight it was expressed on. Asking only whether either user
     * appears on any live interest is true for every stranger as soon as the
     * counterparty has ever expressed interest in anybody, which is how a
     * stranger could reveal a contact.
     */
    public function hasLiveInterest(int $aUserId, int $bUserId): bool
    {
        if (! Schema::hasTable('freight_interests')) {
            return false;
        }

        return FreightInterest::query()
            ->whereNotIn('status', self::BLOCKED_INTEREST_STATUSES)
            ->where(function ($query) use ($aUserId, $bUserId): void {
                $query->where(function ($inner) use ($aUserId, $bUserId): void {
                    $inner->where('user_id', $aUserId)
                        ->whereHas('freightPost', fn ($post) => $post->where('user_id', $bUserId));
                })->orWhere(function ($inner) use ($aUserId, $bUserId): void {
                    $inner->where('user_id', $bUserId)
                        ->whereHas('freightPost', fn ($post) => $post->where('user_id', $aUserId));
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
        $contextIds = $this->contextIds($context);

        $outcome = DB::transaction(function () use ($viewer, $counterparty, $workspace, $contextIds): array {
            // Serialize decisions on the shared workspace row so parallel
            // reveals by different members cannot each spend the last slot.
            // SQLite ignores row locks; MySQL and PostgreSQL enforce them.
            // The user lock is a fallback for legacy accounts without a workspace.
            // The unique ledger pair remains the last line of defence for
            // duplicate requests against the same person.
            if ($workspace !== null) {
                // All members spending the same workspace allowance must wait
                // on the same row, not their individual user rows.
                $workspace->newQuery()->whereKey($workspace->id)->lockForUpdate()->first();
            } else {
                User::query()->whereKey($viewer->id)->lockForUpdate()->first();
            }

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

            $block = $this->newContactBlockFor($viewer);

            if ($block !== null) {
                ContactRevealBlock::create(array_merge($contextIds, [
                    'user_id' => $viewer->id,
                    'revealed_id' => $counterparty->id,
                    'limit_at_time' => $block['limit'],
                    'reason' => $block['reason'],
                ]));

                return array_merge(['status' => 'blocked'], $block);
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
            return array_merge(
                $this->result('blocked', null, $outcome['limit'], $outcome['used'], 0),
                ['reason' => $outcome['reason']],
            );
        }

        $limit = $this->entitlements->getLimitValue($workspace, 'contact_reveals');
        $contact = $this->contactDetailsFor($viewer, $counterparty, $context);

        $used = $workspace !== null
            ? $this->entitlements->revealCount($workspace)
            : $this->usedReveals($viewer);

        $remaining = $limit === -1 ? -1 : max(0, $limit - $used);

        return $this->result($outcome['status'], $contact, $limit, $used, $remaining);
    }

    /**
     * Read-only quota state used by the public listing page. Revisiting an
     * already-revealed contact is always free, even after either limit is spent.
     *
     * @return array{reason: 'post_limit'|'contact_limit', limit: int, used: int}|null
     */
    public function revealBlockFor(User $viewer, User $counterparty): ?array
    {
        if ($viewer->id === $counterparty->id || $this->hasRevealed($viewer, $counterparty)) {
            return null;
        }

        return $this->newContactBlockFor($viewer);
    }

    /**
     * A new reveal needs capacity in both the posting and contact allowances.
     * Public freight remains browseable; this restriction applies only when
     * unlocking a new person's private details.
     *
     * @return array{reason: 'post_limit'|'contact_limit', limit: int, used: int}|null
     */
    private function newContactBlockFor(User $viewer): ?array
    {
        $workspace = $viewer->currentWorkspace;
        $postLimit = $this->entitlements->getLimitValue($workspace, 'active_freight_posts');

        if ($workspace !== null && $postLimit !== -1) {
            $postsUsed = $this->entitlements->livePostCount($workspace);

            if ($postsUsed >= $postLimit) {
                return ['reason' => 'post_limit', 'limit' => $postLimit, 'used' => $postsUsed];
            }
        }

        $contactLimit = $this->entitlements->getLimitValue($workspace, 'contact_reveals');
        $contactsUsed = $workspace !== null
            ? $this->entitlements->revealCount($workspace)
            : $this->usedReveals($viewer);

        if ($contactLimit !== -1 && $contactsUsed >= $contactLimit) {
            return ['reason' => 'contact_limit', 'limit' => $contactLimit, 'used' => $contactsUsed];
        }

        return null;
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
     * The counterparty's contact details, once the relationship gate is open.
     *
     * The gate is checked here because this is the method every caller uses.
     * Resolution itself lives in ContactDetailsResolver so the connection pages
     * and the reveal endpoint cannot drift apart again.
     *
     * @return array{name: string, phone: ?string, email: ?string, whatsapp: ?string}|null
     */
    public function contactDetailsFor(User $viewer, User $counterparty, ?Model $context = null): ?array
    {
        if (! $this->canReveal($viewer, $counterparty, $context)) {
            return null;
        }

        return $this->contacts->resolve($counterparty, $context);
    }
}
