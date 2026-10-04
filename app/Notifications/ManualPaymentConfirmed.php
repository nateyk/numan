<?php

namespace App\Notifications;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to the payer when an administrator confirms their manual payment.
 *
 * In-app now, email later. The mail branch is written so switching it on is a
 * one-line change, but a queue worker is a deployment requirement and this is not
 * the moment to start depending on one for the happy path.
 */
class ManualPaymentConfirmed extends Notification implements ShouldQueue
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
            'type' => 'manual_payment_confirmed',
            'transaction_id' => $this->transaction->id,
            'tx_ref' => $this->transaction->tx_ref,
            'plan_name' => $this->transaction->plan?->name,
            'billing_interval' => $this->transaction->billing_interval,
            'amount_etb' => (string) $this->transaction->amount_etb,
            'currency' => $this->transaction->currency,
            'title' => 'Payment confirmed',
            'message' => sprintf(
                'Your %s ETB payment for %s (%s) is confirmed. Your plan is now active.',
                (string) $this->transaction->amount_etb,
                $this->transaction->plan?->name ?? 'your plan',
                $this->transaction->billing_interval ?? '',
            ),
            // No payer phone, no proof path: this lands in a notifications table.
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your payment is confirmed')
            ->line($this->toArray($notifiable)['message']);
    }
}
