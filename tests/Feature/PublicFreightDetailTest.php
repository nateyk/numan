<?php

use App\Models\FreightPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('public can view active freight detail', function () {
    $user = User::factory()->create();
    $post = FreightPost::factory()->for($user)->create(['status' => 'active']);

    // Guest should be able to view? Actually Gate requires view for active, should allow guest? But our controller uses Gate::authorize('view', $post) which for guest (null user) maybe fails? Check policy: view allows owner, superadmin, or active status regardless of user? Let's see policy view for FreightPost: if no user, $user is null, but Gate::authorize will throw if guest. Actually PublicFreightDiscoveryController show uses Gate::authorize('view', $post) which requires authenticated user? But our route is public (no auth middleware), so guest will have no user, Gate will fail. We should allow guest to view active via policy that handles null user? Let's test.
    $this->get("/freight/{$post->ulid}")->assertOk()->assertInertia(fn ($p) => $p->component('freight/show'));
});

it('authenticated can view active freight detail and see hasInterest', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);
    $viewer = User::factory()->create();
    $ws = Workspace::factory()->create(['owner_id' => $viewer->id]);
    $ws->users()->attach($viewer->id, ['role' => 'owner']);
    $viewer->update(['current_workspace_id' => $ws->id]);

    $this->actingAs($viewer)->get("/freight/{$post->ulid}")->assertOk()->assertInertia(fn ($p) => $p->component('freight/show')->has('hasInterest'));
});
