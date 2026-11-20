<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramNotificationRouting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Notification;

class SendTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $userId,
        public LaravelNotification $notification,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || ! TelegramNotificationRouting::enabled($user)) {
            return;
        }

        Notification::sendNow($user, $this->notification, ['telegram']);
    }
}
