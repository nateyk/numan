<?php

namespace App\Notifications;

use App\Models\FreightPost;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceReminderWindow;
use App\Services\ReminderTimezone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A renewal heads-up, or the news that the plan has ended.
 *
 * Two channels on purpose. The in-app notification is not contingent on mail
 * working, which matters because a mailer misconfigured to `log` fails silently
 * and the reminder is then simply never seen.
 *
 * The expiry copy computes whether listings would actually be hidden and only
 * says so when they would. Asserting "your posts are hidden" to a workspace
 * that is comfortably inside its free limit is worse than saying nothing, and it
 * would contradict the banner, which only appears when the count is above zero.
 */
class MarketplacePlanReminder extends Notification
{
    use Queueable;

    /**
     * @param  string  $reminderType  one of MarketplaceReminderWindow::types()
     */
    public function __construct(
        public readonly MarketplaceSubscription $subscription,
        public readonly string $reminderType,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'marketplace_plan_reminder',
            'reminder_type' => $this->reminderType,
            'subscription_id' => $this->subscription->id,
            'title' => $this->title(),

            // No payer phone, no bank reference, no proof path: this lands in a
            // notifications table and in somebody's inbox. All three are in
            // ActivityPropertyScrubber::SENSITIVE_KEYS for exactly this reason.
            'message' => $this->message(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('marketplace.reminder.greeting', ['name' => $notifiable->name]))
            ->line($this->message());

        $hidden = $this->postsThatWouldBeHidden();

        // Only when it is actually true. See the class docblock.
        if ($hidden > 0) {
            $mail->line(__('marketplace.reminder.hidden_note'));
        }

        return $mail
            ->action(__('marketplace.reminder.cta'), route('billing.plans'))
            ->line(__('marketplace.reminder.footer', [
                'workspace' => $this->subscription->workspace?->name ?? '',
            ]));
    }

    private function title(): string
    {
        return __("marketplace.reminder.subject.{$this->reminderType}");
    }

    /**
     * The body, with every placeholder filled.
     *
     * Includes the price sentence, which the in-app notification needs as much as
     * the email does: somebody glancing at a notification is deciding whether to
     * open the plans page, and "ends tomorrow" without a price does not tell them
     * whether this is affordable.
     */
    private function message(): string
    {
        $workspace = $this->subscription->workspace;
        $plan = $this->subscription->plan;

        $body = __("marketplace.reminder.body.{$this->reminderType}", [
            'plan' => $plan?->name ?? 'Standard',
            'interval' => __("marketplace.reminder.interval.{$this->subscription->billing_interval}"),
            'date' => ReminderTimezone::format(
                $this->subscription->ends_at ?? now(),
                $workspace?->owner?->timezone,
            ),
        ]);

        if ($price = $this->currentPrice()) {
            $body .= ' '.__('marketplace.reminder.price', $price);
        }

        return $body;
    }

    /**
     * Live posts this workspace has above the free limit.
     *
     * Zero is a real answer and the common one. Saying "your posts may be hidden"
     * to a workspace with four posts and a limit of one is true; saying it to one
     * with no posts at all is noise.
     */
    private function postsThatWouldBeHidden(): int
    {
        $workspace = $this->subscription->workspace;

        if ($workspace === null) {
            return 0;
        }

        $entitlement = app(MarketplaceEntitlementService::class);
        $limit = $entitlement->getLimitValue($workspace, 'active_freight_posts');

        if ($limit === -1) {
            return 0;
        }

        // Two things count, and which one is non-zero depends on whether the
        // expiry sweep has run yet.
        //
        // Before the sweep, the excess posts are still visible and livePostCount
        // is over the limit. After it, those same posts carry hidden_at and are
        // excluded from livePostCount -- so counting only the overage would report
        // zero for a workspace whose listings are demonstrably hidden, and the
        // reminder would contradict the banner on the same screen.
        //
        // Both together is the honest number under either ordering, and it is the
        // one the owner cares about: how many of my posts are not being seen.
        $alreadyHidden = FreightPost::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', MarketplaceEntitlementService::LIVE_POST_STATUSES)
            ->hidden()
            ->count();

        $stillOverLimit = max(0, $entitlement->livePostCount($workspace) - $limit);

        return $alreadyHidden + $stillOverLimit;
    }

    /**
     * Today's price for the same duration, or null when there is no plan to price.
     *
     * Read at send time, not from the snapshot: the point of the sentence is
     * "renew for what it costs now", and quoting the price the last holder paid
     * would be actively misleading if it has changed since.
     *
     * @return array{interval: string, price: string, currency: string}|null
     */
    private function currentPrice(): ?array
    {
        $plan = $this->subscription->plan;

        if ($plan === null) {
            return null;
        }

        $column = match ($this->subscription->billing_interval) {
            'weekly' => 'price_weekly_etb',
            'yearly' => 'price_yearly_etb',
            default => 'price_monthly_etb',
        };

        return [
            'interval' => __("marketplace.reminder.interval.{$this->subscription->billing_interval}"),
            'price' => number_format((float) $plan->{$column}),
            'currency' => 'ETB',
        ];
    }

    /**
     * The reminder types this notification can carry.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return MarketplaceReminderWindow::types();
    }
}
