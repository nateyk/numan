<?php

use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\User;
use App\Services\ContactRevealService;
use App\Services\WorkspaceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * The seam between being authenticated and being able to trade.
 *
 * The Fortify tests beside this one cover logging in, resetting a password and
 * verifying an email as isolated features. None of them asks the question a user
 * actually has: I have signed up — what can I do now, and what stops me?
 *
 * That seam is where the marketplace's bugs live. isProfileOnboarded() trusted a
 * loaded relation and reported a finished user as not onboarded. The reveal
 * refused everyone without a public_profiles row. Every one of those is invisible
 * to a test that stops at "the user is logged in".
 *
 * So this file walks the boundary. Every case is a question about what an
 * authenticated user of a particular kind can reach, and what they are told when
 * they cannot.
 */
uses(RefreshDatabase::class);

/**
 * Register through the real Fortify endpoint, not a factory.
 *
 * @return array{0: User}
 */
function registerViaTheForm(array $attributes = []): array
{
    $password = 'correct-horse-battery';

    $response = test()->post('/register', array_merge([
        'name' => 'New Signup',
        'email' => 'newcomer@example.test',
        'password' => $password,
        'password_confirmation' => $password,
    ], $attributes));

    $response->assertRedirect();

    return [User::where('email', $attributes['email'] ?? 'newcomer@example.test')->firstOrFail()];
}

// ── what a brand new account can reach ─────────────────────────────────────

it('asks a new registration to verify their email before anything else', function () {
    // The order is verified, then onboarded — not the other way round. A user who
    // is sent to onboarding first, and only afterwards told their email was never
    // confirmed, may post a load and express interest as somebody the platform
    // cannot reach if they mistype an address. Verification is the gate that has
    // to come first, and nothing tested that it did.
    Event::fake([Registered::class]);

    [$user] = registerViaTheForm();

    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->toBeNull();

    $this->actingAs($user)
        ->get('/my-freight')
        ->assertRedirect(route('verification.notice'));
});

it('keeps an unverified registration out of every marketplace screen', function () {
    [$user] = registerViaTheForm();

    // The marketplace actions, not the settings area. /settings/* is behind
    // auth alone in this application, as it always has been, so the settings page
    // being reachable is the existing convention rather than a new hole — and it
    // can only write to the visitor's own record.
    foreach (['/my-freight', '/my-transporter-profile'] as $path) {
        $this->actingAs($user)
            ->get($path)
            ->assertRedirect(route('verification.notice'), $path.' should require a verified email');
    }
});

it('sends a verified but unonboarded user to onboarding', function () {
    // Past verification, the next gate is onboarding. Every marketplace screen
    // redirects there, because the dashboard is useless to somebody with no
    // profile: they cannot post, cannot express interest and have nothing to see.
    [$user] = registerViaTheForm();
    $user->forceFill(['email_verified_at' => now()])->save();

    foreach (['/my-freight', '/my-transporter-profile'] as $path) {
        $this->actingAs($user->refresh())
            ->get($path)
            ->assertRedirect(route('onboarding.index'), $path.' should redirect an unonboarded user');
    }
});

it('refuses a guest every marketplace screen', function () {
    foreach (['/my-freight', '/my-transporter-profile', '/settings/marketplace-details', '/onboarding-checklist'] as $path) {
        $this->get($path)->assertRedirect(route('login'), $path.' should not be public');
    }
});

it('lets a verified, onboarded user through to the marketplace', function () {
    [$user] = registerViaTheForm();
    $user->forceFill(['email_verified_at' => now()])->save();

    // Onboarding is what creates the workspace. Marking the profile complete by
    // hand is not the same state a user ends up in, and the workspace middleware
    // answers 403 for a user who has an onboarded profile and no workspace.
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'New Signup',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user->fresh())->get('/my-freight')->assertOk();
});

// ── email verification is not optional here ───────────────────────────────

it('refuses to post freight for an unverified email', function () {
    // Contact details are released through a metered reveal. Releasing them to
    // somebody whose email was never confirmed is how an address gets sold on.
    $user = User::factory()->create(['email_verified_at' => null]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Unverified',
        'phone' => '+251911000002',
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user->refresh())
        ->post('/my-freight')
        ->assertRedirect(route('verification.notice'));
});

it('lets a verified email post freight', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Verified',
        'phone' => '+251911000003',
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user->refresh())->post('/my-freight')->assertRedirect();

    $this->assertDatabaseHas('freight_posts', ['user_id' => $user->id]);
});

it('requires a phone number before a draft is created', function () {
    // The draft is the thing the rest of the wizard is built on. Creating it and
    // then refusing to save every step would strand the user mid-flow.
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'No Phone',
        'phone' => null,
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user->refresh())
        ->post('/my-freight')
        ->assertSessionHasErrors('freight');

    $this->assertDatabaseMissing('freight_posts', ['user_id' => $user->id]);
});

// ── session lifecycle ─────────────────────────────────────────────────────

it('ends the session on logout', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->refresh())->get('/my-freight')->assertOk();

    $this->post('/logout')->assertRedirect();

    $this->assertGuest();
});

it('does not let a logged out user reach the marketplace', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->refresh());
    $this->post('/logout');

    $this->get('/my-freight')->assertRedirect(route('login'));
});

// ── one account, two capabilities ─────────────────────────────────────────

it('does not let a signed-in user post as somebody else', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Mine',
        'phone' => '+251911000004',
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user->refresh())->post('/my-freight');

    $mine = FreightPost::where('user_id', $user->id)->firstOrFail();

    $other = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($other)
        ->delete("/my-freight/{$mine->ulid}")
        ->assertForbidden();
});

it('refuses to express interest on your own load', function () {
    // The one marketplace rule that authentication alone cannot enforce.
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Both Sides',
        'phone' => '+251911000005',
        'posts_freight' => true,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $post = FreightPost::factory()->for($user)->create(['status' => 'active']);

    $this->actingAs($user->refresh())
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'mine'])
        ->assertSessionHasErrors('interest');
});

it('cannot reveal a contact without a conversation to reveal it in', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $stranger = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $profile = Profile::firstOrCreate(['user_id' => $stranger->id], [
        'display_name' => 'Stranger',
        'phone' => '+251911000006',
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    expect(app(ContactRevealService::class)
        ->contactDetailsFor($user->refresh(), $stranger->fresh()))->toBeNull();
});
