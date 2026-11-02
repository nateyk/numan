<?php

use App\Models\Profile;
use App\Models\User;
use App\Services\ContactDetailsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function contactProfilePayload(array $changes = []): array
{
    return array_replace([
        'display_name' => 'Fleet contact',
        'account_type' => 'personal',
        'posts_freight' => true,
        'provides_transport' => false,
        'phone_numbers' => ['091 123 4567', '+253 77 12 34 56'],
        'whatsapp_numbers' => ['+251711234567', '+25377123456'],
    ], $changes);
}

it('stores multiple normalized contact numbers and keeps a compatible primary number', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/public-profile')->patch('/public-profile', contactProfilePayload())
        ->assertSessionHasNoErrors()->assertRedirect('/public-profile');

    $profile = $user->profile()->firstOrFail();
    expect($profile->phone_numbers)->toBe(['+251911234567', '+25377123456'])
        ->and($profile->whatsapp_numbers)->toBe(['+251711234567', '+25377123456'])
        ->and($profile->phone)->toBe('+251911234567')
        ->and($profile->whatsapp)->toBe('+251711234567');

    $this->get('/public-profile')->assertInertia(fn ($page) => $page
        ->where('profile.phone_numbers', ['+251911234567', '+25377123456'])
        ->where('profile.whatsapp_numbers', ['+251711234567', '+25377123456']));
    expect(Activity::query()->where('subject_type', Profile::class)->get()->toJson())
        ->not->toContain('+25377123456', '+251911234567');
});

it('requires a phone and rejects malformed or duplicate numbers without changing saved contacts', function (array $changes, string $field) {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['phone' => '+251911999999']);
    $this->actingAs($user)->patch('/public-profile', contactProfilePayload($changes))->assertSessionHasErrors($field);
    expect($profile->fresh()->phone)->toBe('+251911999999');
})->with([
    'missing phone' => [['phone_numbers' => []], 'phone_numbers'],
    'invalid phone' => [['phone_numbers' => ['hello']], 'phone_numbers.0'],
    'invalid WhatsApp' => [['whatsapp_numbers' => ['hello']], 'whatsapp_numbers.0'],
    'same normalized number' => [['phone_numbers' => ['0911234567', '+251911234567']], 'phone_numbers.1'],
]);

it('releases all contact numbers only through the privacy-aware resolver', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/public-profile', contactProfilePayload())->assertSessionHasNoErrors();
    $profile = $user->profile()->firstOrFail();
    $profile->update(['phone_visible' => true, 'whatsapp_visible' => true]);
    $contact = app(ContactDetailsResolver::class)->resolve($user);
    expect($contact['phone_numbers'])->toBe(['+251911234567', '+25377123456'])
        ->and($contact['whatsapp_numbers'])->toBe(['+251711234567', '+25377123456']);

    $profile->update(['phone_visible' => false, 'whatsapp_visible' => false]);
    $contact = app(ContactDetailsResolver::class)->resolve($user);
    expect($contact['phone_numbers'])->toBe([])->and($contact['whatsapp_numbers'])->toBe([])
        ->and($contact['phone'])->toBeNull()->and($contact['whatsapp'])->toBeNull();

    $this->get('/p/'.$profile->slug)->assertDontSee('+25377123456');
});

it('preserves additional numbers when a legacy client updates the primary contact', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/public-profile', contactProfilePayload())->assertSessionHasNoErrors();
    $this->patch('/public-profile', [
        'display_name' => 'Fleet contact', 'account_type' => 'personal',
        'posts_freight' => true, 'provides_transport' => false, 'phone' => '0911999999',
    ])->assertSessionHasNoErrors();
    expect($user->profile()->firstOrFail()->phone_numbers)->toBe(['+251911999999', '+25377123456']);
});

it('lets a business finish setup without proof but requires business registration for the verified badge', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/public-profile', contactProfilePayload([
        'account_type' => 'business',
        'legal_name' => 'Fleet PLC',
        'legal_form' => 'Private Limited Company (PLC)',
    ]))->assertSessionHasNoErrors();
    $this->post('/public-profile/verification')->assertUnprocessable();
    expect($user->profile()->firstOrFail()->verification_status)->toBe('unverified');

    $this->post('/public-profile/documents', [
        'type' => 'business_registration',
        'file' => UploadedFile::fake()->create('registration.pdf', 20, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->post('/public-profile/verification')->assertRedirect();
    expect($user->profile()->firstOrFail()->verification_status)->toBe('pending');
});
