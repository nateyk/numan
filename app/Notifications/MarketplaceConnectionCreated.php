<?php

namespace App\Notifications;

use App\Models\MarketplaceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceConnectionCreated extends Notification
{
    use Queueable;

    public function __construct(
        public MarketplaceConnection $marketplaceConnection,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        $emailEnabled = method_exists($notifiable, 'notificationChannelEnabled')
            ? $notifiable->notificationChannelEnabled('email')
            : true;

        $inAppEnabled = method_exists($notifiable, 'notificationChannelEnabled')
            ? $notifiable->notificationChannelEnabled('in_app')
            : true;

        if ($emailEnabled) {
            $channels[] = 'mail';
        }

        if ($inAppEnabled) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Connection established — contact details available')
            ->greeting('Hello!')
            ->line('A connection has been established for your freight post.')
            ->line('You can now view contact details in your connections.')
            ->action('View Connection', url('/connections/'.$this->marketplaceConnection->public_id))
            ->line('Thank you for using NumanFreight!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'marketplace_connection_created',
            'connection_id' => $this->marketplaceConnection->id,
            'connection_public_id' => $this->marketplaceConnection->public_id,
            'freight_post_id' => $this->marketplaceConnection->freight_post_id,
            'freight_post_ulid' => $this->marketplaceConnection->freightPost->ulid ?? '',
            'title' => 'Connection Established',
            'message' => 'A connection has been established. Contact details are now available.',
        ];
    }
}
