<?php

use App\Http\Resources\PostOwnerSummary;
use App\Models\Profile;
use App\Models\User;
use App\Services\ContactDetailsResolver;

it('resolves the profile phone and honours a refusal', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Hidden Number',
        'phone' => '+251911000001',
        'phone_visible' => false,
    ]);

    $resolver = app(ContactDetailsResolver::class);

    expect($resolver->resolve($user)['phone'])->toBeNull()
        ->and($resolver->resolve($user)['name'])->toBe('Hidden Number');
});

it('honours WhatsApp and email refusals', function () {
    $user = User::factory()->create(['email' => 'account@example.com']);
    Profile::factory()->for($user)->create([
        'display_name' => 'Private Co',
        'whatsapp' => '+251911000002',
        'email' => 'profile@example.com',
        'whatsapp_visible' => false,
        'email_visible' => false,
    ]);

    $resolved = app(ContactDetailsResolver::class)->resolve($user);

    expect($resolved['whatsapp'])->toBeNull()
        ->and($resolved['email'])->toBeNull()
        ->and($resolved['phone'])->not->toBeNull();
});

it('falls back to account name and email when no profile exists', function () {
    $user = User::factory()->create([
        'name' => 'Account Name',
        'email' => 'account@example.com',
    ]);

    $resolved = app(ContactDetailsResolver::class)->resolve($user);

    expect($resolved['name'])->toBe('Account Name')
        ->and($resolved['email'])->toBe('account@example.com')
        ->and($resolved['phone'])->toBeNull()
        ->and($resolved['whatsapp'])->toBeNull();
});

it('reads verification from verification_status on the owner summary', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Verified Co',
        'verification_status' => 'verified',
    ]);

    expect(PostOwnerSummary::flat($user)['is_verified'])->toBeTrue()
        ->and(PostOwnerSummary::flat($user))->not->toHaveKey('verification_level');
});
