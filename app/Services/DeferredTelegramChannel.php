<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;

class DeferredTelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! TelegramNotificationRouting::enabled($notifiable)) {
            return;
        }

        SendTelegramNotification::dispatch($notifiable->id, $notification)->afterCommit();
    }
}
