<?php

namespace App\Console\Commands;

use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Services\MarketplaceReminderWindow;
use App\Services\ReminderDispatcher;
use Illuminate\Console\Command;

/**
 * Tells workspaces their plan is ending, or has ended.
 *
 * Hourly, and disabled by default behind MARKETPLACE_REMINDERS_ENABLED. The flag
 * gates the scheduler registration only -- --dry-run works with it off, which is
 * the whole point of a dry run on a command that has never been run before.
 *
 * Hourly rather than daily because the windows are day-granular but the moment a
 * subscription enters one is not. Every run re-asks the same question, and the
 * reminder log is what makes asking it cheap and safe.
 *
 * Sequential on purpose. The idempotency guarantee is the unique key on the log
 * table, not anything held in memory, so two runs racing each other produce one
 * send between them -- there is nothing here that a lock would improve.
 */
class SendMarketplaceReminders extends Command
{
    protected $signature = 'marketplace:send-reminders
                            {--dry-run : Report what would be sent, and write nothing}';

    protected $description = 'Send marketplace renewal and expiry reminders';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('DRY RUN — nothing will be written and nothing will be sent.');
            $this->newLine();
        }

        // Claims that never completed come first, so a queue outage is recovered
        // before new work is added to it.
        $reclaimed = ['reclaimed' => 0, 'abandoned' => 0];

        if (! $dryRun) {
            $reclaimed = $dispatcher->reclaim();

            if ($reclaimed['reclaimed'] > 0 || $reclaimed['abandoned'] > 0) {
                $this->components->twoColumnDetail(
                    'Reclaimed stale claims',
                    "{$reclaimed['reclaimed']} re-sent, {$reclaimed['abandoned']} abandoned"
                );
            }
        }

        $subscriptions = $this->dueSubscriptions();

        if ($subscriptions->isEmpty()) {
            $this->components->info('Nothing due.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $alreadySent = 0;
        $nothingDue = 0;

        foreach ($subscriptions as $subscription) {
            $due = MarketplaceReminderWindow::due($subscription, now());

            if ($due['types'] === []) {
                $nothingDue++;

                continue;
            }

            foreach ($due['types'] as $type) {
                $isNew = ! $this->alreadyDecided($subscription, $type);

                if ($dryRun) {
                    $this->report($subscription, $type, $isNew);
                    $sent++;

                    continue;
                }

                // Passed-over windows are recorded even when the urgent one was
                // already claimed, otherwise they would fire later.
                $skipped += $dispatcher->skip($subscription, $due['skipped']);

                if ($dispatcher->claim($subscription, $type) === null) {
                    $alreadySent++;

                    continue;
                }

                $dispatcher->dispatch(
                    MarketplaceReminderLog::where('marketplace_subscription_id', $subscription->id)
                        ->where('reminder_type', $type)
                        ->where('status', MarketplaceReminderLog::STATUS_CLAIMED)
                        ->latest('id')
                        ->first()
                );

                $sent++;
            }
        }

        $this->newLine();

        if (! $dryRun && $skipped > 0) {
            $this->components->twoColumnDetail('Windows passed over', "{$skipped} recorded as skipped");
        }

        $this->components->twoColumnDetail(
            $dryRun ? 'Would send' : 'Sent',
            "{$sent} reminder(s)"
        );

        $this->components->twoColumnDetail('Already decided', "{$alreadySent} reminder(s)");
        $this->components->twoColumnDetail('Nothing due', "{$nothingDue} subscription(s)");

        return self::SUCCESS;
    }

    /**
     * Subscriptions worth asking about at all.
     *
     * A generous lower bound on time remaining, then the window rules decide. The
     * widest window is seven days and the widest expiry window is configurable,
     * so anything outside both cannot be due and is not worth loading.
     */
    private function dueSubscriptions()
    {
        $renewalHorizon = max(array_map(
            fn (string $type): int => (int) str_replace('renewal_', '', $type),
            array_keys(MarketplaceReminderWindow::RENEWAL_WINDOWS)
        ));

        $expiryHorizon = (int) config('marketplace.reminder_expired_window_days', 3);

        return MarketplaceSubscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays($renewalHorizon))
            ->where('ends_at', '>=', now()->subDays($expiryHorizon))
            ->with(['workspace', 'plan'])
            ->orderBy('ends_at')
            ->get();
    }

    /**
     * Whether this reminder has already been claimed, sent or skipped.
     */
    private function alreadyDecided(MarketplaceSubscription $subscription, string $type): bool
    {
        return MarketplaceReminderLog::query()
            ->where('marketplace_subscription_id', $subscription->id)
            ->where('reminder_type', $type)
            ->exists();
    }

    /**
     * One plain line per reminder.
     *
     * Deliberately not components->twoColumnDetail: that truncates the right-hand
     * column to fit the terminal, which silently eats the end of the string --
     * and the end is where "new" versus "already decided" lives. A dry run whose
     * output gets quietly truncated is worse than no dry run.
     *
     * The wording also avoids brackets and parentheses, because a test asserting
     * on this output passes the string to a regex, where "(new)" is a capture
     * group rather than two literal characters.
     */
    private function report(MarketplaceSubscription $subscription, string $type, bool $isNew): void
    {
        $this->line(sprintf(
            '  %s  %s / %s  -> %s',
            $type,
            (string) $subscription->billing_interval,
            $subscription->workspace?->name ?? 'workspace #'.$subscription->workspace_id,
            $isNew ? 'would send, new' : 'would send, already decided'
        ));
    }
}
