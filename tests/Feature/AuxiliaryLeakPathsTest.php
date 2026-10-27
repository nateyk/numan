<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\FreightInterestSubmitted;
use App\Services\ProfileDocumentStorage;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The leak audit beyond the seven page paths.
 *
 * Contact data must not survive anywhere else either: the audit trail, the
 * shared Inertia props, notification bodies, mail bodies, and the file storage
 * behind profile documents.
 */
uses(RefreshDatabase::class);

const AUDIT_PHONE = '+251944400001';
const AUDIT_EMAIL = 'audit-probe@example.test';
const AUDIT_WHATSAPP = '+251944400002';
const AUDIT_LEGAL = 'AUDIT LEGAL NAME';

// ── activity log ──────────────────────────────────────────────────────────

it('the activity log never stores a profile phone or legal name', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $profile = Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Audited',
        'phone' => AUDIT_PHONE,
        'legal_name' => AUDIT_LEGAL,
    ]);

    $profile->update(['phone' => '+251944400009']);

    $properties = DB::table('activity_log')
        ->where('subject_type', 'like', '%Profile')
        ->pluck('properties')
        ->implode(' ');

    expect($properties)->not->toBeEmpty();
    expect($properties)->not->toContain(AUDIT_PHONE);
    expect($properties)->not->toContain('+251944400009');
    expect($properties)->not->toContain(AUDIT_LEGAL);
});

it('the activity log never stores transport contact details', function () {
    $profile = Profile::factory()->transporter()->create([
        'display_name' => 'Audited Transporter',
        'legal_name' => AUDIT_LEGAL,
        'phone' => AUDIT_PHONE,
        'whatsapp' => AUDIT_WHATSAPP,
        'email' => AUDIT_EMAIL,
        'phone_visible' => true,
        'email_visible' => true,
    ]);

    $profile->update(['status' => 'approved']);

    $properties = DB::table('activity_log')
        ->where('subject_type', 'like', '%Profile')
        ->pluck('properties')
        ->implode(' ');

    expect($properties)->not->toBeEmpty();

    foreach ([AUDIT_PHONE, AUDIT_WHATSAPP, AUDIT_EMAIL, AUDIT_LEGAL] as $needle) {
        expect($properties)->not->toContain($needle);
    }
});

it('the activity log still records the non-sensitive change', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $profile = Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Before',
        'phone' => AUDIT_PHONE,
    ]);

    $profile->update(['display_name' => 'After']);

    $latest = DB::table('activity_log')
        ->where('subject_type', 'like', '%Profile')
        ->orderByDesc('id')
        ->first();

    expect($latest)->not->toBeNull();
    expect($latest->description)->toBe('updated');
    expect($latest->properties)->toContain('After');
});

it('a visibility flag change is still audited', function () {
    $profile = Profile::factory()->transporter()->create([
        'display_name' => 'Audited Transporter',
        'status' => 'approved',
        'phone' => AUDIT_PHONE,
        'phone_visible' => true,
    ]);

    $profile->update(['phone_visible' => false]);

    $properties = DB::table('activity_log')
        ->where('subject_type', 'like', '%Profile')
        ->orderByDesc('id')
        ->value('properties');

    expect($properties)->toContain('phone_visible');
    expect($properties)->not->toContain(AUDIT_PHONE);
});

// ── shared Inertia props ──────────────────────────────────────────────────

/**
 * The shared props are the same on every page, so a leak there leaks
 * everywhere. The viewer must see their own contact data and nobody else's.
 */
function auditViewer(string $tag = 'viewer'): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'name' => 'Audit Viewer '.$tag,
        'email' => 'audit-'.$tag.'-'.Str::lower(Str::random(8)).'@example.test',
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'display_name' => 'Audit Viewer',
        'phone' => '+251944400100',
    ]);

    return $user->fresh();
}

function auditStranger(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => AUDIT_EMAIL,
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::factory()->for($user)->transporter()->create([
        'display_name' => 'Audit Stranger',
        'legal_name' => AUDIT_LEGAL,
        'status' => 'approved',
        'phone' => AUDIT_PHONE,
        'whatsapp' => AUDIT_WHATSAPP,
        'email' => AUDIT_EMAIL,
        'phone_visible' => true,
        'email_visible' => true,
    ]);

    return $user->fresh();
}

it('the shared props give the viewer their own phone and email', function () {
    $viewer = auditViewer();

    $props = $this->actingAs($viewer)->get(route('dashboard'))->viewData('page')['props'];

    // The viewer's own contact data is present and is theirs, not a fixed
    // fixture string, so assert against the row we just created.
    expect(json_encode($props))->toContain($viewer->email);
    expect($props['auth']['user']['email'])->toBe($viewer->email);
    expect($props['auth']['user']['phone'])->toBe('+251944400100');
});

it('the shared props never carry another user contact data', function (string $routeName) {
    $viewer = auditViewer();
    auditStranger();

    $response = $this->actingAs($viewer)->get(route($routeName));

    $response->assertOk();

    $payload = json_encode($response->viewData('page')['props']);

    foreach ([AUDIT_PHONE, AUDIT_EMAIL, AUDIT_WHATSAPP, AUDIT_LEGAL] as $needle) {
        expect($payload)->not->toContain($needle);
    }
})->with([
    'dashboard' => ['dashboard'],
    'freight discovery' => ['freight.discovery'],
    'transporter directory' => ['transporters.index'],
    'my interests' => ['freight.my-interests'],
]);

// ── notification and mail bodies ──────────────────────────────────────────

it('the interest-submitted notification carries no contact or bank data', function () {
    $consignor = auditViewer();
    $stranger = auditStranger();
    $stranger->update(['name' => 'Audit Stranger']);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $consignor->current_workspace_id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'audit cargo',
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $stranger->id,
        'status' => 'submitted',
        'message' => 'interested',
        'contact_phone' => AUDIT_PHONE,
        'contact_email' => AUDIT_EMAIL,
        'bank_account_name' => 'AUDIT BANK',
        'bank_account_number' => '100077700001',
        'bank_iban' => 'ET0000000099',
        'bank_swift' => 'AUDITSWIFT',
    ]);

    $notification = new FreightInterestSubmitted($interest, $post);

    $payload = json_encode([
        'database' => $notification->toArray($consignor),
        'mail' => (string) $notification->toMail($consignor)->render(),
    ]);

    foreach ([AUDIT_PHONE, AUDIT_EMAIL, AUDIT_WHATSAPP, AUDIT_LEGAL, 'AUDIT BANK', '100077700001', 'ET0000000099', 'AUDITSWIFT'] as $needle) {
        expect($payload)->not->toContain($needle);
    }

    // It still tells the recipient who is interested.
    expect($payload)->toContain('Audit Stranger');
    expect($payload)->toContain('interested');
});

// ── documents are private ─────────────────────────────────────────────────

it('profile documents are stored off the public disk', function () {
    $profile = Profile::factory()->transporter()->create([
        'display_name' => 'Docs Transporter',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    Storage::disk(ProfileDocumentStorage::DISK)->put($document->stored_path, 'secret document bytes');

    // Not reachable through the public disk, which is what public/storage
    // symlinks to.
    expect(Storage::disk('public')->exists($document->stored_path))->toBeFalse();
    expect(Storage::disk(ProfileDocumentStorage::DISK)->exists($document->stored_path))->toBeTrue();
});

it('an unauthenticated request for a document download never serves the file', function () {
    $profile = Profile::factory()->transporter()->create([
        'display_name' => 'Docs Transporter',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    $response = $this->get(route('admin.profiles.documents.download', ['document' => $document->id]));

    // Redirected to login. Never the file itself.
    $response->assertRedirect(route('login'));
    expect($response->getContent())->not->toContain('secret document bytes');
});

it('a signed-in non-owner cannot download a document', function () {
    $profile = Profile::factory()->transporter()->create([
        'display_name' => 'Docs Transporter',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    // A normal onboarded user, not a superadmin.
    $other = auditViewer();

    $this->actingAs($other)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('a superadmin can download a document', function () {
    $owner = User::factory()->superadmin()->create();

    $profile = Profile::factory()->for($owner)->transporter()->create([
        'display_name' => 'Docs Transporter',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/licence.pdf',
        'mime' => 'application/pdf',
        'size' => strlen('secret document bytes'),
        'status' => 'pending',
    ]);

    Storage::disk(ProfileDocumentStorage::DISK)->put($document->stored_path, 'secret document bytes');

    $this->actingAs($owner)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertOk();
});

it('a non-superadmin owner reaches their document through self-service only', function () {
    // The admin group is superadmin-only. The owner branch in the controller
    // used to be unreachable for an ordinary owner; the self-service route is
    // what makes their own document downloadable now.
    $owner = User::factory()->create();

    $profile = Profile::factory()->for($owner)->transporter()->create([
        'display_name' => 'Docs Transporter',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    Storage::disk(ProfileDocumentStorage::DISK)->put($document->stored_path, 'secret document bytes');

    $this->actingAs($owner)
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

// ── admin surface stays admin-only ────────────────────────────────────────

it('a normal user cannot reach the admin profile pages', function () {
    $user = auditViewer();
    $stranger = auditStranger();

    $this->actingAs($user)
        ->get(route('admin.profiles.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.profiles.show', [
            'profile' => $stranger->profile()->firstOrFail(),
        ]))
        ->assertForbidden();
});

it('the public profile page never offers a document file url', function () {
    $viewer = auditViewer();
    $stranger = auditStranger();

    $profile = $stranger->profile()->firstOrFail();

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'secret-licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/audit-test/secret.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('profiles.show', ['profile' => $profile]));

    $response->assertOk();

    $payload = json_encode($response->viewData('page')['props']);

    expect($payload)->not->toContain($document->stored_path);
    expect($payload)->not->toContain('secret-licence.pdf');
    expect($payload)->not->toContain(AUDIT_PHONE);
});

// ── connections index and show carry nothing ──────────────────────────────

it('a connection between other people leaks nothing to a third party', function () {
    $viewer = auditViewer('viewer');
    $consignor = auditStranger();
    $transporter = auditViewer('transporter');

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $consignor->current_workspace_id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'third party cargo',
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'selected',
        'contact_phone' => AUDIT_PHONE,
        'contact_email' => AUDIT_EMAIL,
    ]);

    $connection = MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('connections.show', ['public_id' => $connection->public_id]))
        ->assertForbidden();

    // The viewer's own connections list must not carry the other party's data.
    $own = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $viewer->id,
        'workspace_id' => $viewer->current_workspace_id,
        'status' => FreightPostStatus::Active->value,
    ]);

    $ownInterest = FreightInterest::create([
        'freight_post_id' => $own->id,
        'user_id' => $consignor->id,
        'status' => 'selected',
        'contact_phone' => AUDIT_PHONE,
    ]);

    MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $own->id,
        'freight_interest_id' => $ownInterest->id,
        'consignor_user_id' => $viewer->id,
        'transporter_user_id' => $consignor->id,
    ]);

    $payload = json_encode(
        $this->actingAs($viewer)->get(route('connections.index'))->viewData('page')['props']
    );

    foreach ([AUDIT_PHONE, AUDIT_EMAIL, AUDIT_WHATSAPP, AUDIT_LEGAL] as $needle) {
        expect($payload)->not->toContain($needle);
    }
});

// ── a location seeder sanity check, so the fixtures are real ──────────────

it('a Location factory row is not required for any assertion above', function () {
    // Guards against Location::factory() being accidentally load-bearing.
    expect(Location::count())->toBe(0);
});
