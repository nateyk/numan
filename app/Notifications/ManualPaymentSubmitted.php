<?php

namespace App\Notifications;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to superadmins when a payer submits a manual payment request.
 *
 * Queued, so a worker is a deployment requirement rather than an optimisation.
 * In-app only for now.
 *
 * The proof file path is NOT included. It points at a file on a private disk and
 * belongs behind an authorised route; putting it in a notifications table would
 * hand the path to anyone who can read that row.
 */
class ManualPaymentSubmitted extends Notification implements ShouldQueue
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
            'type' => 'manual_payment_submitted',
            'transaction_id' => $this->transaction->id,
            'tx_ref' => $this->transaction->tx_ref,
            'method' => $this->transaction->manual_method,
            'reference' => $this->transaction->manual_reference,
            'plan_name' => $this->transaction->plan?->name,
            'billing_interval' => $this->transaction->billing_interval,
            'amount_etb' => (string) $this->transaction->amount_etb,
            'currency' => $this->transaction->currency,
            'title' => 'Manual payment awaiting confirmation',
            'message' => sprintf(
                '%s submitted a %s ETB %s payment by %s, reference %s.',
                $this->transaction->workspace?->name ?? 'A workspace',
                (string) $this->transaction->amount_etb,
                $this->transaction->currency,
                strtoupper((string) $this->transaction->manual_method),
                (string) $this->transaction->manual_reference,
            ),
            // Deliberately absent: manual_proof_path. It is a private-disk path
            // and this table is not the right place for it.
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Manual payment awaiting confirmation')
            ->line($this->toArray($notifiable)['message'])
            ->line('Confirm the amount in the banking app, not from the screenshot.');
    }
}
