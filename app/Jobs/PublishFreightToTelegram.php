<?php

namespace App\Jobs;

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Notifications\FreightChannelAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PublishFreightToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $freightPostId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $chatId = config('services.telegram.channel_chat_id');

        if (! filled(config('services.telegram.token')) || ! filled($chatId)) {
            return;
        }

        DB::transaction(function () use ($chatId): void {
            $post = FreightPost::query()->lockForUpdate()->find($this->freightPostId);

            if ($post === null
                || $post->status !== FreightPostStatus::Active->value
                || $post->isHidden()
                || $post->telegram_published_at !== null) {
                return;
            }

            Notification::route('telegram', (string) $chatId)
                ->notify(new FreightChannelAnnouncement($post));

            $post->forceFill(['telegram_published_at' => now()])->saveQuietly();
        });
    }
}
