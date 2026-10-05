<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reminder decision: sent, skipped, or claimed and not yet delivered.
 *
 * The unique (subscription, reminder_type, channel) key on this table is the
 * idempotency guarantee. Everything else here exists to make a queue outage
 * survivable -- see the migration docblock.
 */
class MarketplaceReminderLog extends Model
{
    /** The reminder went out. */
    public const STATUS_SENT = 'sent';

    /**
     * A less urgent window was already open when a more urgent one was reached.
     *
     * Recorded so it cannot fire later. Without the row, a subscription that
     * first gets checked at T-20h would get renewal_1d now and renewal_7d at
     * T-7d, which is the wrong order and reads as nagging.
     */
    public const STATUS_SKIPPED = 'skipped';

    /** Written before the send. sent_at null means the send has not completed. */
    public const STATUS_CLAIMED = 'claimed';

    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_MAIL = 'mail';

    protected $fillable = [
        'marketplace_subscription_id',
        'workspace_id',
        'user_id',
        'reminder_type',
        'channel',
        'status',
        'sent_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSubscription::class, 'marketplace_subscription_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Claimed but not yet delivered.
     */
    public function isPending(): bool
    {
        return $this->sent_at === null;
    }
}
