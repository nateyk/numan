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

    expect($post->fresh()->status)->toBe(FreightPostStatus::Active->value);
});

it('prevents invalid status transition', function () {
    $admin = createSuperAdmin();
    $post = FreightPost::factory()->create(['status' => FreightPostStatus::Draft->value]);

    $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active', 'reason' => 'test'])->assertSessionHasErrors('to_status');
});
