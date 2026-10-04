<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment attempt, by any rail.
 *
 * Chapa and manual share this table on purpose. Confirming a manual payment
 * must go through MarketplaceFulfillmentService::fulfill(), the same single
 * path Chapa uses, so renewal-extends, ends_at, hidden_at restore and the
 * receipt all behave identically. A separate table would mean a second
 * fulfilment path, which is the thing this codebase deliberately does not have.
 *
 * plan_id, billing_interval and amount_etb are real columns, so what was agreed
 * at submission survives a later price change.
 *
 * Not activity-logged. See ActivityPropertyScrubber::SENSITIVE_KEYS.
 */
class MarketplacePaymentTransaction extends Model
{
    use HasFactory;

    public const PROVIDER_CHAPA = 'chapa';

    public const PROVIDER_MANUAL = 'manual';

    /**
     * Statuses in which a manual request is still open.
     */
    public const MANUAL_OPEN_STATUS = 'pending_confirmation';

    public const MANUAL_CONFIRMED = 'confirmed';

    public const MANUAL_REJECTED = 'rejected';

    public const MANUAL_EXPIRED = 'expired';

    /**
     * Manual methods, each backed by one admin-configured account.
     */
    public const MANUAL_METHODS = ['telebirr', 'cbe_birr', 'bank_transfer'];

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'user_id',
        'tx_ref',
        'chapa_ref',
        'amount_etb',
        'currency',
        'billing_interval',
        'status',
        'mode',
        'provider',
        'provider_response',
        'verified_at',

        'manual_method',
        'manual_reference',
        'manual_payer_phone',
        'manual_proof_path',
        'manual_submitted_at',
        'manual_reviewed_by',
        'manual_reviewed_at',
        'manual_rejection_reason',
        'manual_expires_at',
        'open_manual_workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
            'provider_response' => 'array',
            'verified_at' => 'datetime',
            'manual_submitted_at' => 'datetime',
            'manual_reviewed_at' => 'datetime',
            'manual_expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketplacePlan::class, 'plan_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_reviewed_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(MarketplacePaymentReceipt::class, 'transaction_id');
    }

    public function isManual(): bool
    {
        return $this->provider === self::PROVIDER_MANUAL;
    }

    public function isChapa(): bool
    {
        return $this->provider === self::PROVIDER_CHAPA;
    }

    /**
     * Is this manual request still awaiting a decision?
     */
    public function isOpenManual(): bool
    {
        return $this->isManual() && $this->status === self::MANUAL_OPEN_STATUS;
    }

    /**
     * Is it still open past its expiry window?
     */
    public function isExpiredManual(): bool
    {
        return $this->isOpenManual()
            && $this->manual_expires_at !== null
            && $this->manual_expires_at->isPast();
    }

    /**
     * Was this request made against a plan that has since been archived?
     *
     * The snapshotted plan is still honoured on confirm, so this is a warning
     * for the admin rather than a blocker -- but they should know they are
     * approving a plan nobody can buy today.
     */
    public function planIsArchived(): bool
    {
        return $this->plan !== null && ! $this->plan->is_active;
    }
}
