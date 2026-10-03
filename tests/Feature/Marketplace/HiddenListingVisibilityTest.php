<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\MarketplaceListingVisibilityService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Hidden listings must be invisible on every public surface, while the owner
 * and a superadmin keep full control.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->visibility = app(MarketplaceListingVisibilityService::class);
});

function visibilityOwner(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return [$user, $workspace];
}

function approvedTransporter(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'T',
        'phone' => '+251900000000',
    ]);

    TransporterProfile::firstOrCreate(['user_id' => $user->id], [
        'slug' => 'tp-'.Str::lower(Str::random(8)),
        'display_name' => 'T',
        'status' => 'approved',
    ]);

    return $user;
}

function hiddenActivePost(): FreightPost
{
    return FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => User::factory()->create()->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'hidden listing',
        'hidden_at' => now(),
    ]);
}

it('a hidden post is absent from public discovery', function () {
    $hidden = hiddenActivePost();
    FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => User::factory()->create()->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'visible listing',
    ]);

    $this->get(route('freight.discovery'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('discover/index')
            // freight is now an explicit resource array, not a paginator.
            ->where('freight', fn ($rows) => collect($rows)->every(fn ($r) => $r['id'] !== $hidden->id))
        );
});

it('a hidden post 404s on its public URL for a stranger', function () {
    $hidden = hiddenActivePost();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/freight/{$hidden->ulid}")->assertNotFound();
});

it('a hidden post 404s on its public URL for a guest', function () {
    $hidden = hiddenActivePost();

    $this->get("/freight/{$hidden->ulid}")->assertNotFound();
});

it('the owner can still open a hidden post', function () {
    [$owner, $workspace] = visibilityOwner();

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'mine',
        'hidden_at' => now(),
    ]);

    $this->actingAs($owner)->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            ->where('isHiddenListing', true)
            ->where('canRestoreListing', true)
        );
});

it('a superadmin can still open a hidden post', function () {
    $hidden = hiddenActivePost();
    $admin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)->get("/freight/{$hidden->ulid}")->assertOk();
});

it('hidden does not grant a stranger contact or interest access', function () {
    $hidden = hiddenActivePost();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/freight/{$hidden->ulid}")->assertNotFound();
});

it('a transporter cannot express interest on a hidden post', function () {
    $transporter = approvedTransporter();

    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'hidden',
        'hidden_at' => now(),
    ]);

    $this->actingAs($transporter)
        ->post(route('freight.interests.store', ['ulid' => $post->ulid]), [
            'contact_name' => 'T',
        ])
        ->assertSessionHasErrors('interest');

    expect(FreightInterest::where('freight_post_id', $post->id)->count())->toBe(0);
});

it('an existing interest on a hidden post still resolves', function () {
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'hidden later',
    ]);

    $transporter = approvedTransporter();

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'still valid',
        'contact_name' => 'T',
        'contact_phone' => '+251900000000',
        'contact_email' => 't@example.com',
    ]);

    $post->update(['hidden_at' => now()]);

    // The interest row is untouched and its post relation still loads.
    $interest->refresh();
    expect($interest->status)->toBe('submitted');
    expect($interest->freightPost->id)->toBe($post->id);
});

it('the owner sees hidden posts in My Freight with a count', function () {
    [$owner, $workspace] = visibilityOwner();

    FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'hidden one',
        'hidden_at' => now(),
    ]);

    $this->actingAs($owner)->get('/my-freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/index')
            ->where('hiddenCount', 1)
        );
});

it('the owner can still edit a hidden post', function () {
    [$owner, $workspace] = visibilityOwner();

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'still mine',
        'hidden_at' => now(),
    ]);

    $this->actingAs($owner)->get("/my-freight/{$post->ulid}/cargo")->assertOk();

    // Editing does not reveal the listing.
    $post->update(['cargo_description' => 'edited while hidden']);

    expect($post->fresh()->hidden_at)->not->toBeNull();
    expect($post->fresh()->cargo_description)->toBe('edited while hidden');
});

it('closing a hidden post frees its slot and leaves it hidden', function () {
    [$owner, $workspace] = visibilityOwner();

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'hidden active',
        'hidden_at' => now(),
    ]);

    $this->visibility->expireLapsedSubscriptions();

    $post->update(['status' => FreightPostStatus::Closed->value]);

    $this->visibility->hideExcessPosts();

    expect($post->fresh()->hidden_at)->not->toBeNull();
});

it('the admin freight list still sees hidden posts', function () {
    $admin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);
    $hidden = hiddenActivePost();

    $this->actingAs($admin)->get('/admin/freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/freight/index'));
});
