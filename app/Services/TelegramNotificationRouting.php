<?php

namespace App\Services;

use App\Models\User;

final class TelegramNotificationRouting
{
    public static function enabled(object $notifiable): bool
    {
        return $notifiable instanceof User
            && filled(config('services.telegram.token'))
            && filled($notifiable->telegramAccount?->chat_id)
            && $notifiable->notificationChannelEnabled('telegram');
    }
}
