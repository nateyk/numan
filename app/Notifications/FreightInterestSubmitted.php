<?php

namespace App\Notifications;

use App\Models\FreightInterest;
use App\Models\FreightPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreightInterestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public FreightInterest $interest,
        public FreightPost $post,
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
            ->subject('New interest on your freight post')
            ->greeting('Hello!')
            ->line('A transporter has expressed interest in your freight post.')
            ->line('Freight: '.$this->post->ulid)
            ->line('Transporter: '.$this->interest->user->name)
            ->line('Message: '.($this->interest->message ?? 'No message'))
            ->action('View Interest', url("/my-freight/{$this->post->ulid}"))
            ->line('Thank you for using NumanFreight!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'freight_interest_submitted',
            'freight_post_id' => $this->post->id,
            'freight_post_ulid' => $this->post->ulid,
            'interest_id' => $this->interest->id,
            'transporter_name' => $this->interest->user->name,
            'transporter_message' => $this->interest->message,
            'title' => 'New Interest Received',
            'message' => 'A transporter has expressed interest in your freight post.',
        ];
    }
}
