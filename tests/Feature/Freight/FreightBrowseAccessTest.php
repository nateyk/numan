<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Carbon\CarbonInterface;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/** @return array{User, Workspace} */
function browsingMember(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$user->fresh(), $workspace];
}

function browsingSubscription(User $user, Workspace $workspace, string $status, CarbonInterface $endsAt): MarketplaceSubscription
{
    return MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'status' => $status,
        'provider' => 'chapa',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subMonth(),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
    ]);
}

it('redirects guests from both discovery URLs and freight details to sign in', function () {
    $post = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Private discovery cargo',
    ]);

    $this->get('/freight')->assertRedirect(route('login'));
    $this->get('/freight?page=2&search=Private')->assertRedirect(route('login'));
    $this->get('/discover?search=Private')->assertRedirect(route('login'));
    $this->get("/freight/{$post->ulid}")->assertRedirect(route('login'));
});

it('keeps the existing free-plan discovery experience for signed-in members', function () {
    [$viewer] = browsingMember();
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);

    $this->actingAs($viewer)->get('/freight')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('discover/index')
        ->where('freight.total', 1)
        ->has('freight.data', 1)
        ->etc());
});

it('blocks an expired paid member without returning freight, counts or pagination', function () {
    [$viewer, $workspace] = browsingMember();
    browsingSubscription($viewer, $workspace, 'active', now()->subMinute());
    $post = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Restricted freight cargo',
    ]);

    foreach (['/freight', '/freight?page=3&search=Restricted', '/discover?sort=latest'] as $url) {
        $this->actingAs($viewer)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('discover/subscription-required')
            ->missing('freight')
            ->missing('total')
            ->missing('links')
            ->etc());
    }

    $this->actingAs($viewer)
        ->get("/freight/{$post->ulid}")
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('freight.reveal', $post->ulid))
        ->assertForbidden();
});

it('blocks a subscription already marked expired without relying on the hourly job', function () {
    [$viewer, $workspace] = browsingMember();
    browsingSubscription($viewer, $workspace, 'expired', now()->subDay());

    $this->actingAs($viewer)->get('/freight')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('discover/subscription-required')->missing('freight')->etc());
});

it('honors an unexpired cancellation grace period and blocks after it ends', function () {
    [$viewer, $workspace] = browsingMember();
    $subscription = browsingSubscription($viewer, $workspace, 'canceled', now()->addDay());

    $this->actingAs($viewer)->get('/freight')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('discover/index')->etc());

    $subscription->update(['ends_at' => now()->subMinute()]);

    $this->actingAs($viewer)->get('/freight')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('discover/subscription-required')->missing('freight')->etc());
});

it('restores access after renewal even with an older expired record', function () {
    [$viewer, $workspace] = browsingMember();
    browsingSubscription($viewer, $workspace, 'expired', now()->subDay());
    browsingSubscription($viewer, $workspace, 'active', now()->addMonth());

    $this->actingAs($viewer)->get('/freight')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('discover/index')->etc());
});

it('lets an expired consignor view their own post without opening the browse board', function () {
    [$owner, $workspace] = browsingMember();
    browsingSubscription($owner, $workspace, 'expired', now()->subDay());
    $post = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Active->value]);

    $this->actingAs($owner)->get("/freight/{$post->ulid}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('freight/show')->etc());

    $this->actingAs($owner)->get('/freight')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('discover/subscription-required')->missing('freight')->etc());
});
