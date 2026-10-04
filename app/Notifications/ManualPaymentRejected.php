<?php

namespace App\Notifications;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to the payer when an administrator does not confirm their payment.
 *
 * Carries the reason, because the payer needs to know what to do next. An admin
 * who rejects without a reason is stopped by validation, so this is never an
 * unexplained rejection.
 */
class ManualPaymentRejected extends Notification implements ShouldQueue
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
            'type' => 'manual_payment_rejected',
            'transaction_id' => $this->transaction->id,
            'tx_ref' => $this->transaction->tx_ref,
            'plan_name' => $this->transaction->plan?->name,
            'billing_interval' => $this->transaction->billing_interval,
            'amount_etb' => (string) $this->transaction->amount_etb,
            'currency' => $this->transaction->currency,
            'title' => 'Payment not confirmed',
            'reason' => $this->transaction->manual_rejection_reason,
            'message' => 'We could not confirm your payment of '
                .(string) $this->transaction->amount_etb.' '.$this->transaction->currency.'. '
                .($this->transaction->manual_rejection_reason ?? ''),
            'no_payer_phone' => true,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We could not confirm your payment')
            ->line($this->toArray($notifiable)['message'])
            ->line('If you think this is a mistake, reply with your transfer reference.');
    }
}
