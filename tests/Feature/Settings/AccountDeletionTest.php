<?php

use App\Models\FreightPost;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketplaceFulfillmentService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
});

it('can delete account with correct password', function () {
    $response = $this->actingAs($this->user)
        ->delete(route('profile.destroy'), [
            'password' => 'password123',
        ]);

    $response->assertRedirect('/');

    $this->assertSoftDeleted('users', [
        'id' => $this->user->id,
    ]);

    $this->assertGuest();
});

it('cannot delete account with incorrect password', function () {
    $response = $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHasErrors('password');

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'deleted_at' => null,
    ]);
});

it('deletes owned personal workspaces when user is deleted', function () {
    $workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'personal_workspace' => true,
    ]);
    $workspace->users()->attach($this->user->id, ['role' => 'owner']);

    $this->actingAs($this->user)
        ->delete(route('profile.destroy'), [
            'password' => 'password123',
        ]);

    $this->assertSoftDeleted('workspaces', [
        'id' => $workspace->id,
    ]);
});

it('prevents deletion if user owns a shared workspace with other members', function () {
    $workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'personal_workspace' => false,
    ]);
    $workspace->users()->attach($this->user->id, ['role' => 'owner']);

    $otherUser = User::factory()->create();
    $workspace->users()->attach($otherUser->id, ['role' => 'member']);

    $response = $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'password123',
        ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHasErrors('account');

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'deleted_at' => null,
    ]);
});

it('closes freight listings, local paid access and pending payments when a profile is deleted', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'personal_workspace' => true,
    ]);
    $workspace->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $workspace->id]);

    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $this->user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'ends_at' => now()->addMonth(),
        'provider' => 'chapa',
    ]);

    $chapa = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $this->user->id,
        'tx_ref' => 'NF-delete-chapa-pending',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    $manual = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $this->user->id,
        'tx_ref' => 'NF-delete-manual-pending',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
        'mode' => 'test',
        'provider' => 'manual',
        'open_manual_workspace_id' => null,
    ]);

    $post = FreightPost::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $workspace->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->user)->delete(route('profile.destroy'), [
        'password' => 'password123',
    ])->assertRedirect('/');

    $this->assertSoftDeleted('users', ['id' => $this->user->id]);
    $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    expect($subscription->fresh()->status)->toBe('canceled');
    expect($subscription->fresh()->ends_at->lte(now()))->toBeTrue();
    expect($chapa->fresh()->status)->toBe('canceled');
    expect($manual->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_REJECTED);
    expect($manual->fresh()->open_manual_workspace_id)->toBeNull();
    expect($post->fresh()->hidden_at)->not->toBeNull();

    // A delayed success webhook must never resurrect an account that has
    // already requested deletion. Preserve the transaction for support.
    app(MarketplaceFulfillmentService::class)->fulfill($chapa->fresh(), [
        'status' => 'success',
        'data' => ['status' => 'paid', 'amount' => 449, 'currency' => 'ETB'],
    ]);

    expect($chapa->fresh()->status)->toBe('canceled');
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('does not delete a single profile while a submitted manual transfer awaits review', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'personal_workspace' => true,
    ]);
    $workspace->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $workspace->id]);

    $payment = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $this->user->id,
        'tx_ref' => 'NF-deletion-block-pending-transfer',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'manual',
        'provider' => MarketplacePaymentTransaction::PROVIDER_MANUAL,
        'manual_method' => 'telebirr',
        'manual_reference' => 'TRANSFER-12345',
        'manual_submitted_at' => now(),
        'manual_expires_at' => now()->addWeek(),
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password123'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('account');

    $this->assertDatabaseHas('users', ['id' => $this->user->id, 'deleted_at' => null]);
    expect($workspace->fresh()->deleted_at)->toBeNull();
    expect($payment->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_OPEN_STATUS);
    expect($payment->fresh()->open_manual_workspace_id)->toBe($workspace->id);
});

it('allows account deletion after a manual transfer is explicitly rejected', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'personal_workspace' => true,
    ]);
    $workspace->users()->attach($this->user->id, ['role' => 'owner']);

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $this->user->id,
        'tx_ref' => 'NF-deletion-rejected-transfer',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
        'mode' => 'manual',
        'provider' => MarketplacePaymentTransaction::PROVIDER_MANUAL,
    ]);

    $this->actingAs($this->user)
        ->delete(route('profile.destroy'), ['password' => 'password123'])
        ->assertRedirect('/');

    $this->assertSoftDeleted('users', ['id' => $this->user->id]);
});
