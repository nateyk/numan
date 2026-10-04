<?php

use App\Models\Profile;
use App\Models\TransporterDocument;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\TransporterDocumentStorage;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Transporter documents are identity material: a licence, an ID.
 *
 * They live on a disk that cannot be linked to, are readable only by the owner
 * and a superadmin, and are never referenced by a URL.
 */
uses(RefreshDatabase::class);

const DOC_BYTES = 'pretend this is a scanned licence';

/**
 * @return array{0: User, 1: TransporterProfile, 2: TransporterDocument}
 */
function docFixture(): array
{
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($owner);
    Profile::firstOrCreate(['user_id' => $owner->id], [
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'display_name' => 'Doc Owner',
        'phone' => '+251955500001',
    ]);

    $profile = TransporterProfile::create([
        'user_id' => $owner->id,
        'slug' => 'doc-'.Str::lower(Str::random(8)),
        'display_name' => 'Doc Owner Transport',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'my-licence.pdf',
        'stored_path' => TransporterDocumentStorage::DIRECTORY.'/'.$profile->id.'/licence.pdf',
        'mime' => 'application/pdf',
        'size' => strlen(DOC_BYTES),
        'status' => 'pending',
    ]);

    Storage::disk(TransporterDocumentStorage::DISK)->put($document->stored_path, DOC_BYTES);

    return [$owner->fresh(), $profile, $document];
}

/**
 * A signed-in user with a workspace who owns nothing.
 */
function docBystander(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'onboarding_step' => 'completed',
        'display_name' => 'Bystander',
        'phone' => '+251955500002',
    ]);

    return $user->fresh();
}

// ── the disk ──────────────────────────────────────────────────────────────

it('stores documents on a disk that is not tied to FILESYSTEM_DISK', function () {
    // Changing FILESYSTEM_DISK must not be able to move identity documents
    // onto a web-served path.
    $before = config('filesystems.disks.'.TransporterDocumentStorage::DISK);

    config(['filesystems.default' => 'public']);

    expect(config('filesystems.disks.'.TransporterDocumentStorage::DISK))->toBe($before);
    expect(config('filesystems.disks.'.TransporterDocumentStorage::DISK.'.root'))
        ->toBe(storage_path('app/private'));
});

it('the private disk exposes no url and no public visibility', function () {
    $disk = config('filesystems.disks.'.TransporterDocumentStorage::DISK);

    // Without a url, Storage::url() cannot produce a direct link at all.
    expect($disk)->not->toHaveKey('url');
    expect($disk['visibility'] ?? null)->not->toBe('public');
    expect($disk['serve'])->toBeFalse();
});

it('a document is not reachable through the public disk', function () {
    [, , $document] = docFixture();

    expect(Storage::disk(TransporterDocumentStorage::DISK)->exists($document->stored_path))->toBeTrue();
    expect(Storage::disk('public')->exists($document->stored_path))->toBeFalse();
});

it('the public disk symlink target does not contain any document', function () {
    $files = Storage::disk('public')->allFiles(TransporterDocumentStorage::DIRECTORY);

    expect($files)->toBe([]);
});

// ── who may read one ──────────────────────────────────────────────────────

it('lets the owner download their own document', function () {
    [$owner, , $document] = docFixture();

    $response = $this->actingAs($owner)
        ->get(route('transporter.documents.download', ['document' => $document->id]));

    $response->assertOk();
    expect($response->streamedContent())->toBe(DOC_BYTES);
});

it('refuses another signed-in user', function () {
    [, , $document] = docFixture();

    $this->actingAs(docBystander())
        ->get(route('transporter.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('refuses a guest by redirecting to login', function () {
    [, , $document] = docFixture();

    $this->get(route('transporter.documents.download', ['document' => $document->id]))
        ->assertRedirect(route('login'));
});

it('refuses the owner of a different transporter profile', function () {
    [, , $document] = docFixture();

    // Another user who does have their own approved transporter profile. An
    // approved profile is publicly viewable, which must not extend to the
    // document behind it.
    $other = docBystander();

    TransporterProfile::create([
        'user_id' => $other->id,
        'slug' => 'other-'.Str::lower(Str::random(8)),
        'display_name' => 'Other Transport',
        'status' => 'approved',
    ]);

    $this->actingAs($other)
        ->get(route('transporter.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('lets a superadmin download through the admin route', function () {
    [, , $document] = docFixture();

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    app(WorkspaceService::class)->createPersonalWorkspace($admin);

    $this->actingAs($admin)
        ->get(route('admin.transporters.documents.download', ['document' => $document->id]))
        ->assertOk();
});

it('refuses a non-superadmin on the admin route', function () {
    [, , $document] = docFixture();

    // Even the document's own owner, because the admin group is superadmin
    // only. Self-service goes through the owner route instead.
    [$owner] = docFixture();

    $this->actingAs(docBystander())
        ->get(route('admin.transporters.documents.download', ['document' => $document->id]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.transporters.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

// ── filenames ─────────────────────────────────────────────────────────────

it('neutralises a spreadsheet formula in a filename', function () {
    $storage = app(TransporterDocumentStorage::class);

    expect($storage->sanitizeFilename('=1+1.pdf'))->toBe("'=1+1.pdf");
    expect($storage->sanitizeFilename('+cmd.pdf'))->toBe("'+cmd.pdf");
    expect($storage->sanitizeFilename('-2.pdf'))->toBe("'-2.pdf");
    expect($storage->sanitizeFilename('@sum.pdf'))->toBe("'@sum.pdf");
    expect($storage->sanitizeFilename('licence.pdf'))->toBe('licence.pdf');
});

it('reports a missing file instead of erroring', function () {
    [$owner, , $document] = docFixture();

    Storage::disk(TransporterDocumentStorage::DISK)->delete($document->stored_path);

    $this->actingAs($owner)
        ->from(route('transporter.profile.index'))
        ->get(route('transporter.documents.download', ['document' => $document->id]))
        ->assertRedirect(route('transporter.profile.index'))
        ->assertSessionHasErrors('document');
});

// ── upload and download agree on the disk ─────────────────────────────────

it('an uploaded document is immediately readable', function () {
    $owner = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($owner);
    Profile::firstOrCreate(['user_id' => $owner->id], [
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'display_name' => 'Uploader',
        'phone' => '+251955500003',
    ]);

    $profile = TransporterProfile::create([
        'user_id' => $owner->id,
        'slug' => 'upload-'.Str::lower(Str::random(8)),
        'display_name' => 'Uploader Transport',
        'status' => 'pending',
    ]);

    // Exactly what TransporterProfileController does on upload.
    $path = UploadedFile::fake()
        ->create('licence.pdf', 8, 'application/pdf')
        ->storeAs(
            TransporterDocumentStorage::DIRECTORY.'/'.$profile->id,
            Str::uuid().'.pdf',
            TransporterDocumentStorage::DISK,
        );

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => $path,
        'mime' => 'application/pdf',
        'size' => 8,
        'status' => 'pending',
    ]);

    // Upload and download must agree, which they did not before.
    expect(app(TransporterDocumentStorage::class)->exists($document))->toBeTrue();
    expect(Storage::disk('public')->exists($path))->toBeFalse();
});
