<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a user can decline to publish their phone, email and whatsapp.
 *
 * The gap this covers: the refusal flags lived only on transporter_profiles, and
 * ContactRevealService read them as
 *
 *     $transporter !== null && ! $transporter->phone_visible
 *
 * A consignor has no transporter row, so that was always false, so their number was
 * always released — and the reveal ledger, which logs every release, recorded it
 * as though it had been agreed. A consignor was not able to say no at all.
 *
 * The flags now live on profiles, which every kind of user has. A refusal from
 * either the profile or the not-yet-dropped transporter column is honoured.
 */
uses(RefreshDatabase::class);

const CONSIGNOR_PHONE = '+251911440001';
const CONSIGNOR_EMAIL = 'opt-out-consignor@example.test';

/**
 * A consignor with a profile phone and no transporter profile — the shape that
 * could never refuse.
 *
 * @return array{0: User, 1: Workspace}
 */
function refusingConsignor(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => CONSIGNOR_EMAIL,
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Opt Out Consignor',
        'phone' => CONSIGNOR_PHONE,
    ]);

    return [$user, $workspace];
}

/**
 * The interested transporter doing the revealing.
 *
 * Needs its own phone: MarketplaceConnectionController refuses a reveal from a
 * viewer who has none, which is a separate rule from the one under test.
 */
function revealingViewer(): User
{
    $user = User::factory()->create();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Viewer',
        'phone' => '+251966660009',
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->refresh();
}

function interestAgainst(User $viewer, User $consignor): FreightInterest
{
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    return FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $viewer->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);
}

// ── the consignor who could not say no ─────────────────────────────────────

it('lets a consignor refuse to publish their phone', function () {
    [$consignor, $workspace] = refusingConsignor();

    Profile::where('user_id', $consignor->id)->update(['phone_visible' => false]);

    $viewer = revealingViewer();

    $interest = interestAgainst($viewer, $consignor);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBeNull();
});

it('still releases a consignor phone by default', function () {
    // The migration defaults the flags to true on purpose. Hiding every existing
    // number on deploy would be a worse outcome than the gap it closes, so this
    // pins that nothing changed for people who never expressed a preference.
    [$consignor] = refusingConsignor();

    $viewer = revealingViewer();

    $interest = interestAgainst($viewer, $consignor);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe(CONSIGNOR_PHONE);
});

it('honours a refusal recorded on the profile even when the transporter copy says visible', function () {
    // The transporter column is a stale copy until it is dropped. The profile is
    // the record every kind of user has and the one the resolver reads. A stale
    // "yes" beside a profile "no" must not publish a number somebody asked to
    // keep private.
    $viewer = revealingViewer();

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);
    $counterparty = User::factory()->create();

    Profile::firstOrCreate(['user_id' => $counterparty->id], [
        'display_name' => 'Stale Refusal',
        'phone' => '+251977770005',
        'phone_visible' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    TransporterProfile::create([
        'user_id' => $counterparty->id,
        'slug' => 'stale-refusal-'.uniqid(),
        'display_name' => 'Stale Refusal',
        'phone' => '+251977770005',
        'primary_location_id' => $base->id,
        'status' => 'approved',
        'phone_visible' => true,
    ]);

    $interest = interestAgainst($viewer, $counterparty);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBeNull();
});

it('lets a consignor refuse email and whatsapp too', function () {
    [$consignor] = refusingConsignor();

    Profile::where('user_id', $consignor->id)->update([
        'email_visible' => false,
        'whatsapp_visible' => false,
    ]);

    $viewer = revealingViewer();

    $interest = interestAgainst($viewer, $consignor);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.email'))->toBeNull()
        ->and($response->json('contact.whatsapp'))->toBeNull();
});

// ── the migration does not undo a choice already made ──────────────────────

it('carries an existing transporter refusal onto the profile', function () {
    $user = User::factory()->create();
    Profile::firstOrCreate(['user_id' => $user->id], ['display_name' => 'Already Hid']);

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);

    TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'already-hid-'.uniqid(),
        'display_name' => 'Already Hid',
        'phone' => '+251955440003',
        'primary_location_id' => $base->id,
        'status' => 'approved',
        'phone_visible' => false,
        'whatsapp_visible' => true,
        'email_visible' => true,
    ]);

    // Reproduce the shape the backfill was written for. RefreshDatabase has
    // already applied the migration, so the columns have to come off again
    // before up() can be run against a database that has never seen it.
    Schema::table('public_profiles', function (Blueprint $table): void {
        $table->dropColumn(['phone_visible', 'whatsapp_visible', 'email_visible']);
    });

    $migration = require database_path('migrations/2026_10_14_110853_add_contact_visibility_to_profiles_table.php');
    $migration->up();

    $profile = Profile::where('user_id', $user->id)->first();

    expect((bool) $profile->phone_visible)->toBeFalse()
        ->and((bool) $profile->whatsapp_visible)->toBeTrue()
        ->and((bool) $profile->email_visible)->toBeTrue();
});
