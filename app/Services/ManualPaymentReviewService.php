<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ManualPaymentConfirmed;
use App\Notifications\ManualPaymentRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The admin's decisions on a manual payment request.
 *
 * Confirm goes through MarketplaceFulfillmentService::fulfill() -- the same
 * single path Chapa uses. Renewal-extends, ends_at, hidden_at restore and the
 * receipt all come from there rather than being reimplemented here.
 *
 * Every transition locks the row and requires it to be open. That is what makes
 * each of them idempotent and mutually exclusive: whichever arrives first closes
 * the row, and the second finds nothing to do.
 */
class ManualPaymentReviewService
{
    public function __construct(
        protected MarketplaceFulfillmentService $fulfill,
        protected ProfileDocumentStorage $documents,
    ) {}

    /**
     * The queue, newest first.
     *
     * @return array{pending: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function queue(): array
    {
        $pending = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_MANUAL)
            ->where('status', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS)
            ->with(['workspace:id,name', 'plan:id,name,slug', 'user:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $recent = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_MANUAL)
            ->where('status', '!=', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS)
            ->with(['workspace:id,name', 'plan:id,name,slug', 'reviewer:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'pending' => $pending->map(fn ($t) => $this->present($t))->all(),
            'recent' => $recent->map(fn ($t) => $this->present($t))->all(),
            'counts' => [
                'pending' => $pending->count(),
                'confirmed' => MarketplacePaymentTransaction::query()
                    ->where('provider', 'manual')
                    ->where('status', MarketplacePaymentTransaction::MANUAL_CONFIRMED)
                    ->count(),
                'rejected' => MarketplacePaymentTransaction::query()
                    ->where('provider', 'manual')
                    ->where('status', MarketplacePaymentTransaction::MANUAL_REJECTED)
                    ->count(),
                'expired' => MarketplacePaymentTransaction::query()
                    ->where('provider', 'manual')
                    ->where('status', MarketplacePaymentTransaction::MANUAL_EXPIRED)
                    ->count(),
            ],
        ];
    }

    /**
     * How many requests are awaiting a decision, for the menu badge.
     */
    public function pendingCount(): int
    {
        return MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_MANUAL)
            ->where('status', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS)
            ->count();
    }

    /**
     * Confirm a request.
     *
     * Idempotent against a double-click: a second confirm of an already
     * confirmed row is a no-op, not an error and not a second grant.
     *
     * @return array{result: 'confirmed'|'already'|'refused', transaction: MarketplacePaymentTransaction, reason?: string}
     */
    public function confirm(MarketplacePaymentTransaction $transaction, User $admin): array
    {
        $outcome = DB::transaction(function () use ($transaction, $admin): array {
            $locked = $this->lockAndCheck($transaction);

            if ($locked->isConfirmedManual()) {
                // Double-click. Already done.
                return ['result' => 'already', 'transaction' => $locked];
            }

            if (! $locked->isOpenManual()) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'Only a request awaiting confirmation can be confirmed.',
                ];
            }

            // An archived/deleted payer or account must never receive a grant.
            if ($locked->user === null || $locked->workspace === null) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'The payer account is no longer active.',
                ];
            }

            // An admin must not approve their own payment. A workspace owner with
            // admin rights could otherwise grant themselves a plan.
            if ($locked->user_id === $admin->id) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'You cannot confirm your own payment request.',
                ];
            }

            $locked->update([
                'manual_reviewed_by' => $admin->id,
                'manual_reviewed_at' => now(),
            ]);

            // The single fulfilment path, shared with Chapa. The synthetic payload
            // carries the reference fulfil() records; the amount, plan and interval
            // are read from the row, not from here, so a price change cannot alter
            // what was agreed.
            $this->fulfill->fulfill($locked->fresh(), [
                'status' => 'success',
                'data' => [
                    'status' => 'success',
                    'reference' => $locked->manual_reference,
                    'amount' => (string) $locked->amount_etb,
                    'currency' => $locked->currency,
                    'provider' => MarketplacePaymentTransaction::PROVIDER_MANUAL,
                ],
            ]);

            $settled = $locked->fresh();

            // Close the open slot. Idempotent if expiry or something else already
            // did it.
            if ($settled->open_manual_workspace_id !== null) {
                $settled->update(['open_manual_workspace_id' => null]);
            }

            return ['result' => 'confirmed', 'transaction' => $settled->fresh()];
        });

        if ($outcome['result'] === 'confirmed') {
            $this->notifyPayerConfirmed($outcome['transaction']);
        }

        return $outcome;
    }
    /**
     * Reject a request. A reason is mandatory.
     *
     * @return array{result: 'rejected'|'already'|'refused', transaction: MarketplacePaymentTransaction, reason?: string}
     */
    public function reject(MarketplacePaymentTransaction $transaction, User $admin, string $reason): array
    {
        $outcome = DB::transaction(function () use ($transaction, $admin, $reason): array {
            if (blank($reason)) {
                throw ValidationException::withMessages([
                    'manual_rejection_reason' => 'Give a reason. The payer sees it.',
                ]);
            }

            $locked = $this->lockAndCheck($transaction);

            if ($locked->status === MarketplacePaymentTransaction::MANUAL_REJECTED) {
                return ['result' => 'already', 'transaction' => $locked];
            }

            if (! $locked->isOpenManual()) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'Only a request awaiting confirmation can be rejected.',
                ];
            }

            $locked->update([
                'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
                'manual_rejection_reason' => $reason,
                'manual_reviewed_by' => $admin->id,
                'manual_reviewed_at' => now(),
                // Frees the workspace's single open slot.
                'open_manual_workspace_id' => null,
            ]);

            $settled = $locked->fresh();

            return ['result' => 'rejected', 'transaction' => $settled];
        });

        if ($outcome['result'] === 'rejected') {
            $this->deleteProof($outcome['transaction']);
            $this->notifyPayerRejected($outcome['transaction']);
        }

        return $outcome;
    }
    /**
     * Put an expired request back in the queue.
     *
     * Not a path to access: it returns the row to pending_confirmation, where a
     * human still has to confirm it.
     *
     * @return array{result: 'reopened'|'already'|'refused', transaction: MarketplacePaymentTransaction, reason?: string}
     */
    public function reopen(MarketplacePaymentTransaction $transaction, User $admin): array
    {
        $outcome = DB::transaction(function () use ($transaction, $admin): array {
            $locked = $this->lockAndCheck($transaction);

            if ($locked->isOpenManual()) {
                return ['result' => 'already', 'transaction' => $locked];
            }

            if ($locked->status !== MarketplacePaymentTransaction::MANUAL_EXPIRED) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'Only an expired request can be reopened.',
                ];
            }

            $workspaceId = $locked->workspace_id;

            // Reopening must not collide with a request the workspace opened since.
            $conflict = MarketplacePaymentTransaction::query()
                ->where('open_manual_workspace_id', $workspaceId)
                ->whereKeyNot($locked->id)
                ->exists();

            if ($conflict) {
                return [
                    'result' => 'refused',
                    'transaction' => $locked,
                    'reason' => 'This workspace already has another request awaiting confirmation.',
                ];
            }

            $locked->update([
                'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
                'open_manual_workspace_id' => $workspaceId,
                // A fresh window: the old one has already run out.
                'manual_expires_at' => app(ManualPaymentExpiryService::class)->expiryFromNow(),
                'manual_reviewed_by' => $admin->id,
                'manual_reviewed_at' => now(),
            ]);

            return ['result' => 'reopened', 'transaction' => $locked->fresh()];
        });

        return $outcome;
    }
    /**
     * Lock the row and hand it back.
     */
    private function lockAndCheck(MarketplacePaymentTransaction $transaction): MarketplacePaymentTransaction
    {
        // All callers hold a transaction for their entire decision. Lock the
        // owning account first, matching fulfilment's lock order, then the
        // payment row so confirm/reject/reopen cannot race expiry or deletion.
        Workspace::query()
            ->whereKey($transaction->workspace_id)
            ->lockForUpdate()
            ->firstOrFail();

        return MarketplacePaymentTransaction::query()
            ->whereKey($transaction->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * A rejected request has no use for its proof file.
     */
    private function deleteProof(MarketplacePaymentTransaction $transaction): void
    {
        if (blank($transaction->manual_proof_path)) {
            return;
        }

        Storage::disk(ProfileDocumentStorage::DISK)->delete($transaction->manual_proof_path);
    }

    private function notifyPayerConfirmed(MarketplacePaymentTransaction $transaction): void
    {
        $transaction->user?->notify(new ManualPaymentConfirmed($transaction));
    }

    private function notifyPayerRejected(MarketplacePaymentTransaction $transaction): void
    {
        $transaction->user?->notify(new ManualPaymentRejected($transaction));
    }

    /**
     * Explicit columns for the queue.
     *
     * @return array<string, mixed>
     */
    public function present(MarketplacePaymentTransaction $t): array
    {
        return [
            'id' => $t->id,
            'tx_ref' => $t->tx_ref,
            'status' => $t->status,
            'method' => $t->manual_method,
            'reference' => $t->manual_reference,
            'amount_etb' => (string) $t->amount_etb,
            'currency' => $t->currency,
            'plan_name' => $t->plan?->name,
            'plan_slug' => $t->plan?->slug,
            'billing_interval' => $t->billing_interval,
            'workspace' => $t->workspace ? ['id' => $t->workspace->id, 'name' => $t->workspace->name] : null,
            'user' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
            'payer_phone' => $t->manual_payer_phone,
            'has_proof' => filled($t->manual_proof_path),
            // A path to a file on a private disk must never be handed to a
            // browser; the admin views it through an authorised route.
            'proof_url' => filled($t->manual_proof_path)
                ? route('admin.marketplace.manual.proof', ['transaction' => $t->id])
                : null,
            'submitted_at' => optional($t->manual_submitted_at)->toIso8601String(),
            'expires_at' => optional($t->manual_expires_at)->toIso8601String(),
            'reviewed_at' => optional($t->manual_reviewed_at)->toIso8601String(),
            'reviewer' => $t->reviewer?->name,
            'rejection_reason' => $t->manual_rejection_reason,
            // The plan was archived between submission and now. The snapshot is
            // still honoured, so this is a warning, not a blocker.
            'plan_archived' => $t->planIsArchived(),
            'can_confirm' => $t->isOpenManual() && $t->user_id !== auth()->id(),
            'can_reject' => $t->isOpenManual(),
            'can_reopen' => $t->status === MarketplacePaymentTransaction::MANUAL_EXPIRED,
        ];
    }
}
