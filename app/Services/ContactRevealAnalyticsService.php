<?php

namespace App\Services;

use App\Models\ContactReveal;
use App\Models\ContactRevealBlock;
use App\Models\MarketplaceSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Counts for the reveal paywall.
 *
 * The question this answers is one of three: how much is the feature being used,
 * how often does somebody hit the wall, and does hitting the wall lead to a
 * subscription. The third is the only one that justifies the feature existing.
 */
class ContactRevealAnalyticsService
{
    /**
     * Reveals and blocked attempts per day over the trailing window.
     *
     * Zero-filled so a day with no activity is a zero rather than a gap — a
     * chart that skips days lies about the trend.
     *
     * @return array{days: list<array{date: string, reveals: int, blocks: int}>, totals: array{reveals: int, blocks: int, days: int}}
     */
    public function daily(int $days = 30): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $reveals = $this->bucket(ContactReveal::query()
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->get(['created_at']), $days);

        $blocks = $this->bucket(ContactRevealBlock::query()
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->get(['created_at']), $days);

        $series = [];
        $revealTotal = 0;
        $blockTotal = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $r = $reveals[$date] ?? 0;
            $b = $blocks[$date] ?? 0;

            $series[] = ['date' => $date, 'reveals' => $r, 'blocks' => $b];
            $revealTotal += $r;
            $blockTotal += $b;
        }

        return [
            'days' => $series,
            'totals' => ['reveals' => $revealTotal, 'blocks' => $blockTotal, 'days' => $days],
        ];
    }

    /**
     * How many distinct users hit the wall, and how many of them subscribed
     * within the conversion window afterwards.
     *
     * "Subscribed" means a paid marketplace subscription created after the first
     * block, inside the window. Counting users rather than events, because one
     * person retrying twenty times is one person, not twenty conversions.
     *
     * @return array{
     *     blocked_users: int,
     *     converted_users: int,
     *     conversion_rate: float,
     *     window_days: int,
     *     converted: list<array{user_id: int, blocked_at: string, subscribed_at: string|null}>
     * }
     */
    public function conversion(int $windowDays = 7): array
    {
        $from = Carbon::today()->subDays(29)->startOfDay();

        $firstBlock = ContactRevealBlock::query()
            ->where('created_at', '>=', $from)
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get(['user_id', 'created_at'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->first());

        if ($firstBlock->isEmpty()) {
            return [
                'blocked_users' => 0,
                'converted_users' => 0,
                'conversion_rate' => 0.0,
                'window_days' => $windowDays,
                'converted' => [],
            ];
        }

        $userIds = $firstBlock->keys()->all();

        $subscriptions = MarketplaceSubscription::query()
            ->whereIn('workspace_id', DB::table('workspace_user')
                ->whereIn('user_id', $userIds)
                ->select('workspace_id'))
            ->where('created_at', '>=', $from)
            ->get(['workspace_id', 'created_at'])
            ->groupBy('workspace_id')
            ->map(fn ($rows) => $rows->min('created_at'));

        $converted = [];

        foreach ($firstBlock as $userId => $block) {
            $blockedAt = Carbon::parse($block->created_at);
            $deadline = $blockedAt->copy()->addDays($windowDays);

            $userWorkspaces = DB::table('workspace_user')->where('user_id', $userId)->pluck('workspace_id');
            $earliest = $subscriptions
                ->only($userWorkspaces->all())
                ->filter(fn ($createdAt) => Carbon::parse($createdAt)->between($blockedAt, $deadline))
                ->sort()
                ->first();

            $converted[] = [
                'user_id' => (int) $userId,
                'blocked_at' => $blockedAt->toIso8601String(),
                'subscribed_at' => $earliest ? Carbon::parse($earliest)->toIso8601String() : null,
            ];
        }

        $convertedUsers = count(array_filter($converted, fn ($row) => $row['subscribed_at'] !== null));
        $blockedUsers = count($converted);

        return [
            'blocked_users' => $blockedUsers,
            'converted_users' => $convertedUsers,
            'conversion_rate' => $blockedUsers > 0 ? round($convertedUsers / $blockedUsers, 4) : 0.0,
            'window_days' => $windowDays,
            'converted' => $converted,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function bucket($rows, int $days): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->created_at)->toDateString();
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }
}
