<?php

namespace App\Notifications;

use App\Models\FreightPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
