<?php

use App\Enums\FreightInterestStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\FreightPostRoute;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ContactRevealService;
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

// ── the consignor card contract ───────────────────────────────────────────
//
// The card had three bugs that all looked like working UI: it read user.name,
// user.email and a hardcoded "Verified consignor" string, while the resource
// sends display_name, nothing at all, and verification_level. So it rendered
// "Unknown consignor", a blank line, and a green verified tick on every post
// including unverified ones.
//
// These assert on the resource contract rather than on rendered pixels, because
// a component change must not be able to silently reintroduce a leak.

it('sends the owner under the owner key, as a display name and nothing else', function () {
    $owner = User::factory()->create(['name' => 'Real Name On The Account']);
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    // Flat dotted paths rather than a nested closure: a closure-scoped has()
    // resolves against a fresh scope in this version of the assertion helper, so
    // ->has('post.owner', fn ($o) => $o->has('id')) fails on a key that is present.
    // where() asserts the value, not just the key: etc(['post.owner.id']) passed
    // while the resource nested the owner under {data: {...}}, which made the
    // page's isOwnPost check compare an auth id against undefined and read every
    // owner as a visitor. post.owner.id must equal the owner's id, flat.
    $this->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            ->where('post.owner.id', $owner->id)
            ->where('post.owner.display_name', 'Real Name On The Account')
            // None of these has ever been sent. The page read all five.
            ->missing('post.owner.user')
            ->missing('post.owner.name')
            ->missing('post.owner.email')
            ->missing('post.owner.phone')
            ->missing('post.owner.avatar_url')
        );
});

it('sends the stored route so a map can be drawn and a distance quoted', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    FreightPostRoute::create([
        'freight_post_id' => $post->id,
        'distance_meters' => 312000,
        'duration_seconds' => 14400,
        'geometry' => ['type' => 'LineString', 'coordinates' => [[38.7, 9.0], [39.2, 8.5]]],
        'routing_provider' => 'osrm',
    ]);

    $this->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Absent until this change: the page read route_snapshot, the resource
            // never sent it, so the map had no geometry and no distance was quoted.
            ->has('post.route_snapshot', fn ($route) => $route
                ->where('distance_meters', 312000)
                ->where('duration_seconds', 14400)
                ->where('routing_provider', 'osrm')
                ->has('geometry')
            )
        );
});

it('sends a null route rather than omitting the key when a post has no route', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $this->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Present-and-null rather than missing, so the page does not have to
            // tell "no route" apart from "the resource forgot again".
            ->has('post')
            ->where('post.route_snapshot', null)
        );
});

it('never puts the consignor phone number in the payload for a guest', function () {
    $owner = User::factory()->create();
    $phone = '+251911000042';
    $owner->profile()->create(['display_name' => 'Leaky Consignor', 'phone' => $phone]);

    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $json = $this->get("/freight/{$post->ulid}")->assertOk()->getContent();

    // The whole rendered payload, not just the owner key, so a leak anywhere in
    // the page fails this.
    expect($json)->not->toContain($phone);
});

it('never puts the consignor email in the payload for a signed-in viewer with no interest', function () {
    $owner = User::factory()->create(['email' => 'consignor.private@example.test']);
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $viewer->id]);
    $workspace->users()->attach($viewer->id, ['role' => 'owner']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $json = $this->actingAs($viewer)->get("/freight/{$post->ulid}")->assertOk()->getContent();

    expect($json)->not->toContain('consignor.private@example.test');
});

it('does not offer a reveal to someone with no interest', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $stranger = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
    $workspace->users()->attach($stranger->id, ['role' => 'owner']);
    $stranger->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($stranger)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            // No interest id to POST a reveal against, so the page cannot offer one
            // even if it wanted to.
            ->where('interestId', null)
            ->where('canReveal', false)
            ->where('revealedContacts', null)
        );
});

it('passes the interest id and canReveal once an interest exists', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $transporter = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $transporter->id]);
    $workspace->users()->attach($transporter->id, ['role' => 'owner']);
    $transporter->update(['current_workspace_id' => $workspace->id]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'Available truck',
    ]);

    $this->actingAs($transporter)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            ->where('interestId', $interest->id)
            ->where('canReveal', true)
            // Reading the page must not consume quota or perform a reveal.
            ->where('revealedContacts', null)
        );
});

it('sends the status a rejected carrier needs to stop being told they are awaiting review', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $transporter = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $transporter->id]);
    $workspace->users()->attach($transporter->id, ['role' => 'owner']);
    $transporter->update(['current_workspace_id' => $workspace->id]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => FreightInterestStatus::Rejected->value,
        'message' => 'Available truck',
    ]);

    // The page renders five interest outcomes from these three props. Drop any
    // of them and a rejected carrier is back to "awaiting consignor" — the lie
    // the status mapping replaced. A characterization test: the controller has
    // always sent these; the page only just started reading them.
    $this->actingAs($transporter)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            ->where('hasInterest', true)
            ->where('interestStatus', 'rejected')
            ->where('interestId', $interest->id)
        );
});

it('does not offer the owner a reveal of their own contact', function () {
    // ContactRevealService::canReveal() returns true when the viewer and the
    // counterparty are the same person. On this page that would render a
    // "Reveal contact" button to somebody revealing themselves, which is
    // nonsense — the owner gets Edit instead.
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->users()->attach($owner->id, ['role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canReveal', false));
});

it('returns the contact only once the pair is already in the ledger', function () {
    $owner = User::factory()->create();
    $phone = '+251911000077';
    $owner->profile()->create(['display_name' => 'Revealed Consignor', 'phone' => $phone]);

    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $transporter = User::factory()->create();
    Profile::factory()->create([
        'user_id' => $transporter->id,
        'account_type' => 'business',
        'display_name' => $transporter->name,
        'phone' => '+251911000088',
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $transporter->id]);
    $workspace->users()->attach($transporter->id, ['role' => 'owner']);
    $transporter->update(['current_workspace_id' => $workspace->id]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
    ]);

    // Not yet revealed: still absent.
    $this->actingAs($transporter)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('revealedContacts', null));

    // The reveal itself, through the same service the action uses.
    app(ContactRevealService::class)->reveal($transporter->fresh(), $owner, $interest);

    $this->actingAs($transporter)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canReveal', true)
            ->where('revealedContacts.phone', $phone)
        );
});

// ── the hidden-listing banner ─────────────────────────────────────────────
//
// The controller has passed isHiddenListing and canRestoreListing to this page
// since the expiry sweep landed, with a comment saying the owner needs the badge
// here. The page read neither, so an owner whose paid plan lapsed opened their own
// hidden post and saw a perfectly normal-looking freight post with no explanation
// of why nobody else can see it.
//
// The wording is deliberately the same as the My Freight banner. Two screens
// describing the same fact differently is how a support ticket gets written.

it('tells the owner that a hidden post is hidden', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create([
        'status' => 'active',
        'hidden_at' => now(),
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->users()->attach($owner->id, ['role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freight/show')
            ->where('isHiddenListing', true)
            // The owner may put it back, so the page offers the route out.
            ->where('canRestoreListing', true)
        );
});

it('does not claim a visible post is hidden', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => 'active']);

    $this->get("/freight/{$post->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isHiddenListing', false)
            ->where('canRestoreListing', false)
        );
});

it('still 404s a hidden post for the public, banner or not', function () {
    // The banner is for the owner. It must not become a way to confirm that a
    // post exists.
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create([
        'status' => 'active',
        'hidden_at' => now(),
    ]);

    $this->get("/freight/{$post->ulid}")->assertNotFound();
});
