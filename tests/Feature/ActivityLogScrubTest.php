<?php

use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\ActivityPropertyScrubber;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The audit trail must not retain contact data or credential material.
 *
 * Three defences, tested separately because they fail independently:
 *   1. models no longer write it (logOnly allowlists)
 *   2. the scrub migration removed what history already held
 *   3. the workspace activity feed strips it again on the way out
 */
uses(RefreshDatabase::class);

const AUDIT_PHONE_KEY = '+251966600001';
const AUDIT_EMAIL_KEY = 'audit-key@example.test';

// ── 1. nothing new is written ─────────────────────────────────────────────

it('writes no phone, email or legal name when a profile is created', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Scrubbed',
        'phone' => AUDIT_PHONE_KEY,
        'legal_name' => 'SCRUBBED LEGAL',
    ]);

    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain(AUDIT_PHONE_KEY);
    expect($properties)->not->toContain('SCRUBBED LEGAL');
    expect($properties)->not->toContain($user->email);
});

it('writes no phone, whatsapp, email or legal name when a transporter profile changes', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $transporter = TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'scrub-'.Str::lower(Str::random(8)),
        'display_name' => 'Scrubbed Transporter',
        'legal_name' => 'SCRUBBED LEGAL',
        'status' => 'pending',
        'phone' => AUDIT_PHONE_KEY,
        'whatsapp' => '+251966600002',
        'email' => AUDIT_EMAIL_KEY,
    ]);

    $transporter->update(['phone' => '+251966600009']);

    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain(AUDIT_PHONE_KEY);
    expect($properties)->not->toContain('+251966600002');
    expect($properties)->not->toContain('+251966600009');
    expect($properties)->not->toContain(AUDIT_EMAIL_KEY);
    expect($properties)->not->toContain('SCRUBBED LEGAL');
});

it('writes no email or password hash when a user is created', function () {
    $user = User::factory()->create([
        'email' => AUDIT_EMAIL_KEY,
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $user->update(['name' => 'Renamed Person']);

    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain(AUDIT_EMAIL_KEY);
    expect($properties)->not->toContain('$2y$');
    expect($properties)->toContain('Renamed Person');
});

it('still records a non-sensitive change on a user', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $user->update(['locale' => 'am']);

    $latest = DB::table('activity_log')->orderByDesc('id')->first();

    expect($latest->description)->toBe('updated');
    expect($latest->properties)->toContain('am');
});

it('still records a visibility flag change on a transporter profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $transporter = TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'scrub-'.Str::lower(Str::random(8)),
        'display_name' => 'Flag Transporter',
        'status' => 'approved',
        'phone' => AUDIT_PHONE_KEY,
        'phone_visible' => true,
    ]);

    $transporter->update(['phone_visible' => false]);

    $latest = DB::table('activity_log')
        ->where('subject_type', 'like', '%TransporterProfile')
        ->orderByDesc('id')
        ->first();

    expect($latest->properties)->toContain('phone_visible');
    expect($latest->properties)->not->toContain(AUDIT_PHONE_KEY);
});

// ── 2. the scrubber removes what history held ─────────────────────────────

it('removes sensitive keys from both value bags', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    [$cleaned, $removed] = $scrubber->scrub([
        'attributes' => [
            'display_name' => 'Kept',
            'phone' => '+251966600001',
            'legal_name' => 'REMOVED',
        ],
        'old' => [
            'phone' => '+251966600002',
            'bank_account_number' => '10001112222',
            'bank_iban' => 'ET001',
            'bio' => 'Kept too',
        ],
        'causer_id' => 5,
    ]);

    expect($removed)->toBe(5);
    expect($cleaned['attributes'])->toBe(['display_name' => 'Kept']);
    expect($cleaned['old'])->toBe(['bio' => 'Kept too']);
    // Non-attribute bags are untouched.
    expect($cleaned['causer_id'])->toBe(5);
});

it('removes a password hash', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    [$cleaned, $removed] = $scrubber->scrub([
        'attributes' => ['name' => 'Kept', 'password' => '$2y$12$abcdefghijklmnop'],
    ]);

    expect($removed)->toBe(1);
    expect($cleaned['attributes'])->toBe(['name' => 'Kept']);
});

it('leaves a row with nothing sensitive untouched', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    [$cleaned, $removed] = $scrubber->scrubJson('{"attributes":{"display_name":"Kept"}}');

    expect($removed)->toBe(0);
    expect($cleaned)->toBeNull();
});

it('does not destroy a row whose properties are not valid JSON', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    [$cleaned, $removed] = $scrubber->scrubJson('not json at all');

    expect($cleaned)->toBeNull();
    expect($removed)->toBe(0);
});

it('treats an empty properties payload as nothing to do', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    expect($scrubber->scrubJson(null)[0])->toBeNull();
    expect($scrubber->scrubJson('')[0])->toBeNull();
});

it('is idempotent: scrubbing twice changes nothing the second time', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    $first = $scrubber->scrubJson(json_encode([
        'attributes' => ['phone' => '+251966600001', 'display_name' => 'Kept'],
    ]));

    expect($first[1])->toBe(1);

    $second = $scrubber->scrubJson($first[0]);

    expect($second[0])->toBeNull();
    expect($second[1])->toBe(0);
});

it('the migration scrubs pre-existing rows, keeps the row, and is idempotent', function () {
    // A row as logFillable() would have written it, before the allowlists.
    DB::table('activity_log')->insert([
        'log_name' => 'profile',
        'description' => 'updated',
        'subject_type' => 'App\\Models\\Profile',
        'subject_id' => 1,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => 1,
        'properties' => json_encode([
            'attributes' => ['display_name' => 'Before', 'phone' => '+251966600001'],
            'old' => ['phone' => '+251966600002'],
        ]),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    DB::table('activity_log')->insert([
        'log_name' => 'user',
        'description' => 'created',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => 1,
        'properties' => json_encode([
            'attributes' => ['name' => 'Kept', 'email' => AUDIT_EMAIL_KEY, 'password' => '$2y$12$abc'],
        ]),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    // Run the migration body directly, twice.
    $migration = require database_path('migrations/2026_10_04_000001_scrub_contact_data_from_activity_log.php');

    $migration->up();
    $afterFirst = DB::table('activity_log')->orderBy('id')->first();

    $migration->up();
    $afterSecond = DB::table('activity_log')->orderBy('id')->first();

    // The row survives.
    expect(DB::table('activity_log')->count())->toBe(2);
    expect($afterSecond->description)->toBe('updated');

    // The values are gone, the audit fact is not.
    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain('+251966600001');
    expect($properties)->not->toContain('+251966600002');
    expect($properties)->not->toContain(AUDIT_EMAIL_KEY);
    expect($properties)->not->toContain('$2y$');
    expect($properties)->toContain('Before');
    expect($properties)->toContain('Kept');

    // A second run is a no-op.
    expect($afterSecond->properties)->toBe($afterFirst->properties);
});

// ── 3. the read path strips it again ──────────────────────────────────────

it('the workspace activity page never returns sensitive properties to a member', function () {
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);
    $owner->update(['current_workspace_id' => $workspace->id]);

    // A row that predates the allowlists, scoped to this workspace.
    DB::table('activity_log')->insert([
        'log_name' => 'profile',
        'description' => 'updated',
        'subject_type' => 'App\\Models\\Profile',
        'subject_id' => 1,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $owner->id,
        'properties' => json_encode([
            'attributes' => ['display_name' => 'Before', 'phone' => AUDIT_PHONE_KEY],
            'old' => ['phone' => '+251966600002'],
            'workspace_id' => $workspace->id,
        ]),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner->fresh())
        ->get(route('workspaces.activity', ['workspace' => $workspace->id]));

    $response->assertOk();

    $payload = json_encode($response->viewData('page')['props']);

    expect($payload)->not->toContain(AUDIT_PHONE_KEY);
    expect($payload)->not->toContain('+251966600002');
    expect($payload)->toContain('Before');
});

it('the activity CSV export never contains properties at all', function () {
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);
    $owner->update(['current_workspace_id' => $workspace->id]);

    DB::table('activity_log')->insert([
        'log_name' => 'profile',
        'description' => 'updated',
        'subject_type' => 'App\\Models\\Profile',
        'subject_id' => 1,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $owner->id,
        'properties' => json_encode([
            'attributes' => ['display_name' => 'Before', 'phone' => AUDIT_PHONE_KEY],
            'workspace_id' => $workspace->id,
        ]),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $csv = (string) $this->actingAs($owner->fresh())
        ->get(route('workspaces.activity.export', ['workspace' => $workspace->id]))
        ->streamedContent();

    expect($csv)->toContain('Description');
    expect($csv)->not->toContain(AUDIT_PHONE_KEY);
    expect($csv)->not->toContain('phone');
});

it('the activity feed never contains properties at all', function () {
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);
    $owner->update(['current_workspace_id' => $workspace->id]);

    DB::table('activity_log')->insert([
        'log_name' => 'profile',
        'description' => 'updated',
        'subject_type' => 'App\\Models\\Profile',
        'subject_id' => 1,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $owner->id,
        'properties' => json_encode([
            'attributes' => ['phone' => AUDIT_PHONE_KEY],
            'workspace_id' => $workspace->id,
        ]),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $body = json_encode($this->actingAs($owner->fresh())
        ->getJson(route('workspace-activity-feed'))
        ->json());

    expect($body)->not->toContain(AUDIT_PHONE_KEY);
    expect($body)->not->toContain('properties');
});
