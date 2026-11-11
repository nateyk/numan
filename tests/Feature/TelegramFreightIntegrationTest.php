<?php

use App\Enums\FreightPostStatus;
use App\Jobs\PublishFreightToTelegram;
use App\Models\FreightPost;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\FreightChannelAnnouncement;
use App\Notifications\FreightInterestSubmitted;
use App\Notifications\FreightPostModerated;
use App\Notifications\MarketplaceConnectionCreated;
use App\Notifications\TelegramBotReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.telegram', [
        'token' => 'fake-telegram-token',
        'bot_username' => 'NumanFreightBot',
        'channel_chat_id' => '-1001234567890',
        'webhook_secret' => 'integration-test-secret',
    ]);
    Notification::fake();
});

it('requires login to create a Telegram connection', function () {
    $this->get('/settings/telegram/connect')->assertRedirect();
    $this->delete('/settings/telegram')->assertRedirect();
});

it('links an authenticated user only after a secret-verified private start message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/telegram/connect');

    $response->assertRedirect();
    $url = $response->headers->get('Location');
    expect($url)->toStartWith('https://t.me/NumanFreightBot?start=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $token = $query['start'];

    $update = [
        'update_id' => 123,
        'message' => [
            'chat' => ['id' => 7654321, 'type' => 'private'],
            'from' => ['id' => 7654321],
            'text' => '/start '.$token,
        ],
    ];

    $this->postJson('/api/telegram/webhook', $update)->assertForbidden();

    expect(TelegramAccount::query()->count())->toBe(0);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'integration-test-secret')
        ->postJson('/api/telegram/webhook', $update)
        ->assertNoContent();

    expect($user->fresh()->telegramAccount?->chat_id)->toBe('7654321');

    Notification::assertSentOnDemand(TelegramBotReply::class);
});

it('refuses group chats and prevents a link from being used twice', function () {
    $user = User::factory()->create();

    $url = $this->actingAs($user)->get('/settings/telegram/connect')->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'integration-test-secret');

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => -100444, 'type' => 'supergroup'],
            'from' => ['id' => 7654321],
            'text' => '/start '.$query['start'],
        ],
    ])->assertNoContent();

    expect(TelegramAccount::query()->count())->toBe(0);

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 111222, 'type' => 'private'],
            'from' => ['id' => 111222],
            'text' => '/start '.$query['start'],
        ],
    ])->assertNoContent();

    $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 333444, 'type' => 'private'],
            'from' => ['id' => 333444],
            'text' => '/start '.$query['start'],
        ],
    ])->assertNoContent();

    expect(TelegramAccount::query()->count())->toBe(1);
    expect($user->fresh()->telegramAccount?->chat_id)->toBe('111222');
});

it('disconnects on the bot stop command or from account settings', function () {
    $user = User::factory()->create();

    TelegramAccount::query()->create(['user_id' => $user->id, 'chat_id' => '778899']);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'integration-test-secret')
        ->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 778899, 'type' => 'private'],
                'from' => ['id' => 778899],
                'text' => '/stop',
            ],
        ])->assertNoContent();

    expect($user->fresh()->telegramAccount)->toBeNull();

    TelegramAccount::query()->create(['user_id' => $user->id, 'chat_id' => '778899']);

    $this->actingAs($user)->delete('/settings/telegram')->assertRedirect();

    expect($user->fresh()->telegramAccount)->toBeNull();
});

it('adds Telegram only for opted-in linked users with a configured bot', function () {
    $user = User::factory()->create();
    $post = FreightPost::factory()->submitted()->create(['user_id' => $user->id]);

    $notification = new FreightPostModerated($post, 'approved');

    expect($notification->via($user))->not->toContain('telegram');

    TelegramAccount::query()->create(['user_id' => $user->id, 'chat_id' => '112233']);

    expect($notification->via($user->fresh()))->toContain('telegram');

    $user->update(['notification_preferences' => [
        'channels' => ['telegram' => false],
    ]]);

    expect($notification->via($user->fresh()))->not->toContain('telegram');

    $user->update(['notification_preferences' => [
        'channels' => ['telegram' => true],
    ]]);
    config()->set('services.telegram.token', null);

    expect($notification->via($user->fresh()))->not->toContain('telegram');
});

it('publishes only visible active freight and never repeats an announcement', function () {
    $post = FreightPost::factory()->active()->create();

    (new PublishFreightToTelegram($post->id))->handle();
    (new PublishFreightToTelegram($post->id))->handle();

    Notification::assertSentOnDemandTimes(FreightChannelAnnouncement::class, 1);
    expect($post->fresh()->telegram_published_at)->not->toBeNull();
});

it('never publishes drafts, submitted posts, hidden freight or expired posts', function () {
    $draft = FreightPost::factory()->draft()->create();
    $submitted = FreightPost::factory()->submitted()->create();
    $hidden = FreightPost::factory()->active()->create(['hidden_at' => now()]);
    $expired = FreightPost::factory()->create(['status' => FreightPostStatus::Expired->value]);

    foreach ([$draft, $submitted, $hidden, $expired] as $post) {
        (new PublishFreightToTelegram($post->id))->handle();
    }

    Notification::assertNothingSent();
});
