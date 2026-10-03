<?php

use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->createPersonalWorkspace($this->owner);
    $this->workspace->addUser($this->owner, 'owner');
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->owner->refresh();
});

/**
 * There is a single paid plan with three durations, so the comparison table
 * has nothing to show. /billing/compare redirects to the plans page and the
 * legacy Stripe comparison is kept on disk but unreachable.
 */
it('redirects the comparison page to the plans page', function () {
    $this->actingAs($this->owner)
        ->get('/billing/compare')
        ->assertRedirect(route('billing.plans'));
});

it('redirects for workspace members too', function () {
    $member = User::factory()->create();
    $this->workspace->addUser($member, 'member');
    $member->update(['current_workspace_id' => $this->workspace->id]);
    $member->refresh();

    $this->actingAs($member)
        ->get('/billing/compare')
        ->assertRedirect(route('billing.plans'));
});

it('no longer exposes a multi plan comparison', function () {
    // The Stripe plan list is not sent to user facing pages any more.
    $this->actingAs($this->owner)
        ->get('/billing/plans')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/plans')
            ->where('plans', [])
            ->has('freeLimits')
        );
});
