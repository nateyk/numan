<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function lifecycleConsignor(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('allows a consignor to close a listed freight without deleting it', function () {
    $owner = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->active()->create();

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/close")->assertRedirect();
    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/close")->assertRedirect();

    $post->refresh();

    expect($post->status)->toBe(FreightPostStatus::Closed->value)
        ->and($post->closed_at)->not->toBeNull()
        ->and($post->arrived_at)->toBeNull()
        ->and($post->moderationLogs()->where('reason', 'Listing closed by consignor')->count())->toBe(1);
});

it('can close a submitted listing before an administrator approves it', function () {
    $owner = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->submitted()->create();

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/close")->assertRedirect();

    expect($post->fresh()->status)->toBe(FreightPostStatus::Closed->value);
});

it('only the consignor can close or mark a listing as arrived', function () {
    $owner = lifecycleConsignor();
    $other = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->active()->create();

    $this->actingAs($other)->post("/my-freight/{$post->ulid}/close")->assertForbidden();

    $post->update(['status' => FreightPostStatus::Closed->value]);

    $this->actingAs($other)->post("/my-freight/{$post->ulid}/arrive")->assertForbidden();

    expect($post->fresh()->arrived_at)->toBeNull();
});

it('records cargo arrival only after a freight listing is closed', function () {
    $owner = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->active()->create();

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/arrive")->assertForbidden();

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/close")->assertRedirect();
    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/arrive")->assertRedirect();
    $firstArrival = $post->fresh()->arrived_at;

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/arrive")->assertRedirect();

    expect($post->fresh()->status)->toBe(FreightPostStatus::Closed->value)
        ->and($firstArrival)->not->toBeNull()
        ->and($post->fresh()->arrived_at->equalTo($firstArrival))->toBeTrue()
        ->and($post->moderationLogs()->where('reason', 'Cargo arrival confirmed by consignor')->count())->toBe(1);
});

it('keeps expired pickup windows off the public board even before the scheduler runs', function () {
    FreightPost::factory()->active()->create([
        'pickup_date' => now()->subDays(5)->toDateString(),
        'pickup_date_flexibility_days' => 1,
    ]);
    FreightPost::factory()->active()->create([
        'pickup_date' => now()->subDay()->toDateString(),
        'pickup_date_flexibility_days' => 2,
    ]);

    $this->get('/freight')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('discover/index')
        ->where('freight.total', 1)
        ->etc());
});

it('refuses to submit a draft with a past pickup date', function () {
    $owner = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->draft()->create([
        'pickup_date' => now()->subDay()->toDateString(),
        'pickup_date_flexibility_days' => 2,
    ]);

    $this->actingAs($owner)->post("/my-freight/{$post->ulid}/submit")
        ->assertSessionHasErrors('pickup_date');

    expect($post->fresh()->status)->toBe(FreightPostStatus::Draft->value);
});

it('exposes a clear end-of-window label to the consignor', function () {
    $owner = lifecycleConsignor();
    FreightPost::factory()->for($owner)->active()->create([
        'pickup_date' => now()->addDay()->toDateString(),
        'pickup_date_flexibility_days' => 3,
    ]);

    $this->actingAs($owner)->get('/my-freight')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('freight/index')
        ->where('posts.data.0.expires_on', now()->addDays(4)->toDateString())
        ->etc());
});

it('keeps freight creation wizard pages private to the draft editor', function () {
    $owner = lifecycleConsignor();
    $stranger = lifecycleConsignor();
    $post = FreightPost::factory()->for($owner)->draft()->create();

    foreach (['route', 'cargo', 'schedule', 'review'] as $step) {
        $this->actingAs($stranger)
            ->get("/my-freight/{$post->ulid}/{$step}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->get("/my-freight/{$post->ulid}/{$step}")
            ->assertOk();
    }

    $post->update(['status' => FreightPostStatus::Active->value]);

    $this->actingAs($owner)
        ->get("/my-freight/{$post->ulid}/schedule")
        ->assertForbidden();
});
