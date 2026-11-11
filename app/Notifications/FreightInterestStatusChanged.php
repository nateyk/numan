<?php

namespace App\Notifications;

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Services\DeferredTelegramChannel;
use App\Services\TelegramNotificationRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class FreightInterestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public FreightInterest $interest,
        public FreightPost $post,
        public string $oldStatus,
        public string $newStatus,
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

        if (TelegramNotificationRouting::enabled($notifiable)) {
            $channels[] = DeferredTelegramChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabels = [
            'shortlisted' => 'shortlisted',
            'rejected' => 'rejected',
            'selected' => 'selected',
            'withdrawn' => 'withdrawn',
        ];

        $label = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return (new MailMessage)
            ->subject('Interest status updated: '.ucfirst($label))
            ->greeting('Hello!')
            ->line('Your interest on freight post '.$this->post->ulid.' has been '.$label.'.')
            ->action('View Interest', url('/my-interests'))
            ->line('Thank you for using NumanFreight!');
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->normal()
            ->content("Your freight interest status changed.\nFreight: {$this->post->ulid}\nStatus: {$this->newStatus}")
            ->button('View your interests', url('/my-interests'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'freight_interest_status_changed',
            'freight_post_id' => $this->post->id,
            'freight_post_ulid' => $this->post->ulid,
            'interest_id' => $this->interest->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'title' => 'Interest Status Updated',
            'message' => 'Your interest has been '.($this->newStatus === 'selected' ? 'accepted' : $this->newStatus).'.',
        ];
    }
}
