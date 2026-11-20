<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\TelegramBotReply;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.telegram.webhook_secret');

        abort_unless(filled($secret), 503);
        abort_unless(hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')), 403);

        $message = $request->input('message');

        if (! is_array($message)
            || data_get($message, 'chat.type') !== 'private'
            || ! is_numeric(data_get($message, 'chat.id'))
            || (string) data_get($message, 'chat.id') !== (string) data_get($message, 'from.id')) {
            return response()->noContent();
        }

        $chatId = (string) data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if (preg_match('/^\/stop(?:@\w+)?$/D', $text) === 1) {
            TelegramAccount::query()->where('chat_id', $chatId)->delete();
            $this->reply($chatId, 'Your Numan Freight Telegram alerts are turned off. Reconnect from your notification settings.');

            return response()->noContent();
        }

        if (preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9]{48}))?$/D', $text, $matches) !== 1) {
            return response()->noContent();
        }

        if (empty($matches[1])) {
            $this->reply($chatId, 'Welcome to Numan Freight! Sign in and connect Telegram at '.route('notifications.show'));

            return response()->noContent();
        }

        $userId = Cache::pull('telegram:link:'.hash('sha256', $matches[1]));

        if ($userId === null || ! User::query()->whereKey($userId)->exists()) {
            $this->reply($chatId, 'This connection link has expired or was already used. Generate a new one from your notification settings.');

            return response()->noContent();
        }

        if (TelegramAccount::query()->where('chat_id', $chatId)->where('user_id', '!=', $userId)->exists()) {
            $this->reply($chatId, 'This Telegram account is already connected to another Numan Freight account. Send /stop first.');

            return response()->noContent();
        }

        TelegramAccount::query()->updateOrCreate(
            ['user_id' => $userId],
            ['chat_id' => $chatId]
        );

        $this->reply($chatId, 'Connected! You will receive your freight approvals, interest updates and connection alerts here. Send /stop to disconnect.');

        return response()->noContent();
    }

    private function reply(string $chatId, string $message): void
    {
        try {
            Notification::route('telegram', $chatId)->notify(new TelegramBotReply($message));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
