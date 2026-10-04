<?php

namespace App\Notifications;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to the payer when their manual payment request lapses.
 *
 * Queued: it may carry an image to render and a worker may not be running on a
 * small deployment. If no worker is running the notification simply sits in the
 * queue, which is why docs/deploy-notes.md calls the queue a deployment
 * requirement rather than an optimisation.
 *
 * In-app now, email later. The mail branch is written but not the point yet.
 */
class ManualPaymentExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MarketplacePaymentTransaction $transaction,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manual_payment_expired',
            'transaction_id' => $this->transaction->id,
            'tx_ref' => $this->transaction->tx_ref,
            'method' => $this->transaction->manual_method,
            'plan_name' => $this->transaction->plan?->name,
            'billing_interval' => $this->transaction->billing_interval,
            'amount_etb' => (string) $this->transaction->amount_etb,
            'currency' => $this->transaction->currency,
            'title' => 'Manual payment request expired',
            // No contact data of any kind, and deliberately no payer phone: this
            // lands in a notifications table.
            'message' => 'We could not confirm your payment in time, so the request was closed. '
                .'Nothing was charged and no plan was activated. You can start a new request whenever you are ready.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your manual payment request expired')
            ->line('We could not confirm your payment in time, so the request was closed.')
            ->line('Nothing was charged and no plan was activated. You can start a new request whenever you are ready.');
    }
}
