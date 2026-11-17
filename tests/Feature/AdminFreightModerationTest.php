<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSuperAdmin(): User
{
    $user = User::factory()->superadmin()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('moderates freight from submitted to active with reason required on reject', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->create(['status' => FreightPostStatus::Submitted->value]);

    $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'rejected'])->assertSessionHasErrors('reason');

    $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active', 'reason' => 'Looks good'])->assertRedirect();

    expect($post->fresh()->status)->toBe(FreightPostStatus::Active->value)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

it('prevents invalid status transition', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->create(['status' => FreightPostStatus::Draft->value]);

    $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active', 'reason' => 'test'])->assertSessionHasErrors('to_status');
});

it('does not treat draft creation as a publication date before approval', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->submitted()->create([
        'created_at' => now()->subDays(5),
        'published_at' => null,
    ]);

    expect($post->fresh()->published_at)->toBeNull();

    $this->actingAs($admin)
        ->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active'])
        ->assertRedirect();

    $post->refresh();
    expect($post->published_at)->not->toBeNull()
        ->and($post->published_at->greaterThan($post->created_at))->toBeTrue()
        ->and($post->published_at->isToday())->toBeTrue();
});

it('does not publish a freight whose pickup date has already passed', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->submitted()->create([
        'pickup_date' => now()->subDay()->toDateString(),
        'published_at' => null,
    ]);

    $this->actingAs($admin)
        ->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active'])
        ->assertSessionHasErrors('to_status');

    expect($post->fresh()->status)->toBe(FreightPostStatus::Submitted->value)
        ->and($post->fresh()->published_at)->toBeNull();
});

it('marks administrator-closed freight as cancelled rather than delivered', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->active()->create();

    $this->actingAs($admin)
        ->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'closed'])
        ->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(FreightPostStatus::Closed->value)
        ->and($post->closure_reason)->toBe('cancelled')
        ->and($post->closed_at)->not->toBeNull()
        ->and($post->arrived_at)->toBeNull();
});
