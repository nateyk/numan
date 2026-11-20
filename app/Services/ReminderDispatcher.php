<?php

namespace App\Services;

use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Notifications\MarketplacePlanReminder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Claims a reminder, sends it, and marks the claim delivered.
 *
 * Claim before send, never the other way round. The row is written first so that
 * two concurrent command runs cannot both decide a reminder is due and both send
 * it -- the second insert hits the unique key and is dropped. The cost of that
 * ordering is a claim with a null sent_at if the process dies or synchronous
 * notification delivery throws; reclaim() exists to recover those claims.
 *
 * The reminder claim stores subscription identifiers, not payer contact data.
 * MarketplacePlanReminder does not implement ShouldQueue: mail and database
 * notifications are dispatched inline, not through a queued notification job.
 */
class ReminderDispatcher
{
    /**
     * Claim a reminder for one subscription and one reminder type.
     *
     * @return MarketplaceReminderLog|null null when it was already decided
     */
    public function claim(MarketplaceSubscription $subscription, string $reminderType): ?MarketplaceReminderLog
    {
        try {
            return MarketplaceReminderLog::create([
                'marketplace_subscription_id' => $subscription->id,
                'workspace_id' => $subscription->workspace_id,
                'reminder_type' => $reminderType,
                'channel' => MarketplaceReminderLog::CHANNEL_DATABASE,
                'status' => MarketplaceReminderLog::STATUS_CLAIMED,
                'attempts' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already claimed, already sent, or deliberately skipped. All three mean
            // "not now", and all three are the correct outcome.
            return null;
        }
    }

    /**
     * Record a window that was passed over, so it cannot fire later.
     *
     * Recorded as skipped rather than simply absent: without a row, a
     * seven-day warning would arrive a few days after the one-day warning that
     * replaced it, which reads as nagging rather than as a heads-up.
     *
     * @param  list<string>  $reminderTypes
     * @return int how many were newly recorded
     */
    public function skip(MarketplaceSubscription $subscription, array $reminderTypes): int
    {
        $recorded = 0;

        foreach ($reminderTypes as $type) {
            try {
                MarketplaceReminderLog::create([
                    'marketplace_subscription_id' => $subscription->id,
                    'workspace_id' => $subscription->workspace_id,
                    'reminder_type' => $type,
                    'channel' => MarketplaceReminderLog::CHANNEL_DATABASE,
                    'status' => MarketplaceReminderLog::STATUS_SKIPPED,
                    'sent_at' => now(),
                ]);

                $recorded++;
            } catch (UniqueConstraintViolationException) {
                // Already recorded. Nothing to do.
            }
        }

        return $recorded;
    }

    /**
     * Dispatch a claimed reminder to everybody who manages billing.
     *
     * @return int recipients notified
     */
    public function dispatch(MarketplaceReminderLog $log): int
    {
        $subscription = $log->subscription()->with(['plan', 'workspace.owner'])->first();

        if ($subscription === null) {
            // The workspace or subscription is gone. Mark it so it stops being
            // reclaimed rather than being retried until it runs out of attempts.
            $this->markSent($log);

            return 0;
        }

        // Do not replay a claim after the customer renewed, canceled an
        // upcoming reminder, or passed the intended delivery window. In
        // particular, an old "plan expired" claim can outlive a new paid term.
        if (! app(MarketplaceReminderEligibility::class)->isStillDue($subscription, $log->reminder_type)) {
            $this->markSkipped($log);

            return 0;
        }

        $recipients = ReminderRecipients::forWorkspace($subscription->workspace);

        if ($recipients->isEmpty()) {
            $this->markSent($log);

            return 0;
        }

        $log->increment('attempts');

        // MarketplacePlanReminder is dispatched inline to the database and
        // mail channels. A failure leaves the claim to be reclaimed, rather than
        // marking an undelivered reminder as sent.
        Notification::send(
            $recipients,
            new MarketplacePlanReminder($subscription, $log->reminder_type)
        );

        // Mark delivered after the synchronous dispatch completes, not when the
        // recipient opens it. Read receipts must never cause repeat sends.
        $this->markSent($log);

        return $recipients->count();
    }

    /**
     * Re-dispatch claims that never completed.
     *
     * A claim is written before the synchronous dispatch. A process crash or
     * thrown notification error can leave sent_at null; reclaiming after the
     * timeout prevents the claim from swallowing a reminder forever.
     *
     * @return array{reclaimed: int, abandoned: int}
     */
    public function reclaim(): array
    {
        $timeout = (int) config('marketplace.reminder_claim_timeout_minutes', 60);
        $maxAttempts = (int) config('marketplace.reminder_max_attempts', 3);

        $cutoff = now()->subMinutes($timeout);

        $stale = MarketplaceReminderLog::query()
            ->where('status', MarketplaceReminderLog::STATUS_CLAIMED)
            ->whereNull('sent_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $reclaimed = 0;
        $abandoned = 0;

        foreach ($stale as $log) {
            if ($log->attempts >= $maxAttempts) {
                $abandoned++;
                $this->abandon($log);

                continue;
            }

            $this->dispatch($log->fresh());
            $reclaimed++;
        }

        return ['reclaimed' => $reclaimed, 'abandoned' => $abandoned];
    }

    private function markSent(MarketplaceReminderLog $log): void
    {
        $log->forceFill([
            'status' => MarketplaceReminderLog::STATUS_SENT,
            'sent_at' => now(),
        ])->save();
    }

    private function markSkipped(MarketplaceReminderLog $log): void
    {
        $log->forceFill([
            'status' => MarketplaceReminderLog::STATUS_SKIPPED,
            'sent_at' => now(),
        ])->save();
    }

    /**
     * Give up on a reminder that keeps failing.
     *
     * The warning carries the subscription id and nothing else. Which workspace
     * it belongs to is one query away for whoever has to chase it, and a warning
     * that arrives by email does not need to also carry the customer's name.
     */
    private function abandon(MarketplaceReminderLog $log): void
    {
        $log->forceFill([
            'status' => MarketplaceReminderLog::STATUS_SKIPPED,
            'sent_at' => now(),
        ])->save();

        Log::warning('Marketplace reminder abandoned after repeated failures', [
            'subscription_id' => $log->marketplace_subscription_id,
            'reminder_type' => $log->reminder_type,
            'attempts' => $log->attempts,
        ]);
    }
}
