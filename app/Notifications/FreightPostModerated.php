<?php

namespace App\Notifications;

use App\Models\FreightPost;
use App\Services\DeferredTelegramChannel;
use App\Services\TelegramNotificationRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class FreightPostModerated extends Notification
{
    use Queueable;

    public function __construct(
        public FreightPost $post,
        public string $action,
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
        $actionLabel = $this->action === 'approved' ? 'approved' : ($this->action === 'rejected' ? 'rejected' : 'updated');

        return (new MailMessage)
            ->subject('Freight post '.$actionLabel)
            ->greeting('Hello!')
            ->line('Your freight post '.$this->post->ulid.' has been '.$actionLabel.'.')
            ->action('View Post', url('/my-freight/'.$this->post->ulid))
            ->line('Thank you for using NumanFreight!');
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->normal()
            ->content("Freight post {$this->action}\nReference: {$this->post->ulid}\nStatus: {$this->post->status}")
            ->button('View your freight', url('/my-freight/'.$this->post->ulid));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'freight_post_moderated',
            'freight_post_id' => $this->post->id,
            'freight_post_ulid' => $this->post->ulid,
            'action' => $this->action,
            'title' => 'Freight Post '.ucfirst($this->action),
            'message' => 'Your freight post has been '.$this->action.'.',
        ];
    }
}
