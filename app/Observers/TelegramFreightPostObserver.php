<?php

namespace App\Observers;

use App\Enums\FreightPostStatus;
use App\Jobs\PublishFreightToTelegram;
use App\Models\FreightPost;

class TelegramFreightPostObserver
{
    public function updated(FreightPost $post): void
    {
        if ($post->status !== FreightPostStatus::Active->value
            || $post->isHidden()
            || $post->telegram_published_at !== null
            || (! $post->wasChanged('status') && ! $post->wasChanged('hidden_at'))
            || ! filled(config('services.telegram.token'))
            || ! filled(config('services.telegram.channel_chat_id'))) {
            return;
        }

        PublishFreightToTelegram::dispatch($post->id)->afterCommit();
    }
}
