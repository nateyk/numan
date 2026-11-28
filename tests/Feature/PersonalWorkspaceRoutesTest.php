<?php

use App\Models\User;
use App\Services\WorkspaceService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($this->user);
    $this->user->switchWorkspace($workspace);
});

it('routes obsolete personal account pages to account settings without removing billing', function () {
    config()->set('features.workspace.ui', false);

    $this->actingAs($this->user)
        ->get('/workspaces/settings')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)
        ->get('/workspaces/create')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)
        ->get('/team')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)
        ->get('/workspaces/api-keys')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)
        ->get('/settings/api-tokens')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)
        ->get('/settings/workspace-security')->assertRedirect('/settings/profile');

    $this->actingAs($this->user)->get('/billing')->assertOk();
    $this->actingAs($this->user)->get('/settings/profile')->assertOk();
});

it('does not expose team invitations or token mutations through hidden URLs', function () {
    config()->set('features.workspace.ui', false);

    $this->actingAs($this->user)->post('/team/invite', [])->assertNotFound();
    $this->actingAs($this->user)->post('/settings/api-tokens', [])->assertNotFound();
    $this->actingAs($this->user)->post('/workspaces', [])->assertNotFound();
});

it('keeps legacy workspace routes available when teams are enabled', function () {
    config()->set('features.workspace.ui', true);

    $this->actingAs($this->user)->get('/workspaces/settings')->assertOk();
    $this->actingAs($this->user)->get('/team')->assertOk();
    $this->actingAs($this->user)->get('/settings/api-tokens')->assertOk();
});
