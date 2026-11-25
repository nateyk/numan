<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramConnectionController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        $username = ltrim((string) config('services.telegram.bot_username'), '@');

        abort_unless(
            filled(config('services.telegram.token')) && preg_match('/^[A-Za-z0-9_]{5,32}$/D', $username) === 1,
            503,
            'Telegram is not configured.'
        );

        $token = Str::random(48);

        Cache::put(
            'telegram:link:'.hash('sha256', $token),
            $request->user()->id,
            now()->addMinutes(10)
        );

        return redirect()->away('https://t.me/'.$username.'?start='.$token);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->telegramAccount()->delete();

        return back()->with('success', 'Telegram notifications disconnected.');
    }
}
