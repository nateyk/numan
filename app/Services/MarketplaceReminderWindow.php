<?php

namespace App\Services;

use App\Models\MarketplaceSubscription;
use Carbon\CarbonInterface;

/**
 * Decides which reminders a subscription is due.
 *
 * Two rules do the real work here.
 *
 * Most urgent window only. A yearly subscription first examined with twenty hours
 * left is inside renewal_1d and nothing else, and that is the only reminder it
 * gets. Without this it would get renewal_1d now and renewal_7d seven days from
 * now, which is backwards: the seven-day warning arrives after the one-day
 * warning, and the second one reads as nagging rather than as a heads-up. The
 * caller records the windows it passed over as 'skipped' so they cannot fire
 * later.
 *
 * Expiry has a window. "Your plan expired" only applies for three days after
 * ends_at. Without that, the first run after enabling would email every lapsed
 * row in the database, including subscriptions that expired months ago and whose
 * owner has since paid elsewhere. A backlog is not a reminder.
 *
 * Windows are inclusive at both ends so an hourly run cannot fall between two
 * ticks and miss a boundary.
 */
class MarketplaceReminderWindow
{
    /**
     * Renewal windows in days before ends_at, most urgent first.
     *
     * Weekly is deliberately absent from the 7-day and 3-day rows: a weekly
     * subscription renewed more than a day early is not a thing anybody does, and
     * a "7 days left" email on a 7-day plan reads as broken.
     *
     * @var array<string, list<string>>
     */
    public const RENEWAL_WINDOWS = [
        'renewal_1d' => ['weekly', 'monthly', 'yearly'],
        'renewal_3d' => ['monthly', 'yearly'],
        'renewal_7d' => ['yearly'],
    ];

    public const EXPIRED = 'plan_expired';

    /**
     * Every reminder type this class can produce, most urgent first.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::EXPIRED, 'renewal_1d', 'renewal_3d', 'renewal_7d'];
    }

    /**
     * The reminders due for one subscription right now.
     *
     * @return array{types: list<string>, skipped: list<string>}
     */
    public static function due(MarketplaceSubscription $subscription, CarbonInterface $now): array
    {
        // Superseded rows are excluded by status alone. A renewal marks the prior
        // row superseded and creates a new one, so this needs no date arithmetic
        // to avoid reminding a workspace about a period it has already renewed.
        if (! in_array($subscription->status, ['active', 'trialing'], true)) {
            return ['types' => [], 'skipped' => []];
        }

        if ($subscription->ends_at === null) {
            return ['types' => [], 'skipped' => []];
        }

        $endsAt = $subscription->ends_at;
        $interval = (string) ($subscription->billing_interval ?? '');

        if ($endsAt->lessThanOrEqualTo($now)) {
            return self::expiredDue($endsAt, $now);
        }

        return self::renewalDue($endsAt, $interval, $now);
    }

    /**
     * Expired: only inside the window, and there is no urgency ordering to do.
     *
     * @return array{types: list<string>, skipped: list<string>}
     */
    private static function expiredDue(CarbonInterface $endsAt, CarbonInterface $now): array
    {
        $window = (int) config('marketplace.reminder_expired_window_days', 3);

        // Compared as instants, not as a number of days. diffInDays returns a
        // float, so a subscription that ended exactly three days ago comes back
        // as 3.0000001 and fails a "> 3" test -- which would silently disable the
        // reminder on the boundary it matters most.
        if ($endsAt->lessThan($now->copy()->subDays($window))) {
            return ['types' => [], 'skipped' => []];
        }

        return ['types' => [self::EXPIRED], 'skipped' => []];
    }

    /**
     * Renewal: the most urgent window reached, and everything looser as skipped.
     *
     * @return array{types: list<string>, skipped: list<string>}
     */
    private static function renewalDue(CarbonInterface $endsAt, string $interval, CarbonInterface $now): array
    {
        $reached = [];

        foreach (self::RENEWAL_WINDOWS as $type => $intervals) {
            if (! in_array($interval, $intervals, true)) {
                continue;
            }

            // "Inside the window" as an instant comparison: has the end date come
            // within N days of now. Inclusive, so an hourly run landing exactly on
            // a boundary still fires.
            if ($endsAt->lessThanOrEqualTo($now->copy()->addDays(self::daysFor($type)))) {
                $reached[] = $type;
            }
        }

        if ($reached === []) {
            return ['types' => [], 'skipped' => []];
        }

        // RENEWAL_WINDOWS is ordered most urgent first, so the first entry is the
        // one that gets sent and the rest are recorded as passed over.
        return [
            'types' => [$reached[0]],
            'skipped' => array_values(array_slice($reached, 1)),
        ];
    }

    /**
     * The day threshold for a window name.
     */
    private static function daysFor(string $type): int
    {
        return (int) str_replace('renewal_', '', $type);
    }
}
