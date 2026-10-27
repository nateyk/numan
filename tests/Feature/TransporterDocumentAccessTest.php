<?php

use App\Models\Profile;
use App\Models\ProfileDocument;
use App\Models\User;
use App\Services\ProfileDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Profile documents are identity material: a licence, an ID.
 *
 * They live on a disk that cannot be linked to, are readable only by the owner
 * and a superadmin, and are never referenced by a URL. The same storage service
 * writes and reads them, so an upload that succeeds can always be downloaded.
 */
uses(RefreshDatabase::class);

const DOC_BYTES = 'pretend this is a scanned licence';

/**
 * @return array{0: User, 1: Profile, 2: ProfileDocument}
 */
function docFixture(): array
{
    Storage::fake(ProfileDocumentStorage::DISK);

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->transporter()->create([
        'display_name' => 'Doc Owner Transport',
        'status' => 'approved',
    ]);

    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'my-licence.pdf',
        'stored_path' => ProfileDocumentStorage::DIRECTORY.'/'.$profile->id.'/licence.pdf',
        'mime' => 'application/pdf',
        'size' => strlen(DOC_BYTES),
        'status' => 'pending',
    ]);

    Storage::disk(ProfileDocumentStorage::DISK)->put($document->stored_path, DOC_BYTES);

    return [$owner->fresh(), $profile, $document];
}

/**
 * A signed-in user who owns nothing.
 */
function docBystander(): User
{
    $user = User::factory()->create();

    Profile::factory()->for($user)->consignor()->create([
        'display_name' => 'Bystander',
    ]);

    return $user->fresh();
}

// ── the disk ──────────────────────────────────────────────────────────────

it('stores documents on a disk that is not tied to FILESYSTEM_DISK', function () {
    // Changing FILESYSTEM_DISK must not be able to move identity documents
    // onto a web-served path.
    $before = config('filesystems.disks.'.ProfileDocumentStorage::DISK);

    config(['filesystems.default' => 'public']);

    expect(config('filesystems.disks.'.ProfileDocumentStorage::DISK))->toBe($before);
    expect(config('filesystems.disks.'.ProfileDocumentStorage::DISK.'.root'))
        ->toBe(storage_path('app/private'));
});

it('the private disk exposes no url and no public visibility', function () {
    $disk = config('filesystems.disks.'.ProfileDocumentStorage::DISK);

    // Without a url, Storage::url() cannot produce a direct link at all.
    expect($disk)->not->toHaveKey('url');
    expect($disk['visibility'] ?? null)->not->toBe('public');
    expect($disk['serve'])->toBeFalse();
});

it('a document is not reachable through the public disk', function () {
    [, , $document] = docFixture();

    expect(Storage::disk(ProfileDocumentStorage::DISK)->exists($document->stored_path))->toBeTrue();
    expect(Storage::disk('public')->exists($document->stored_path))->toBeFalse();
});

it('the public disk does not contain any document', function () {
    Storage::fake('public');

    docFixture();

    expect(Storage::disk('public')->allFiles(ProfileDocumentStorage::DIRECTORY))->toBe([]);
});

// ── who may read one ──────────────────────────────────────────────────────

it('lets the owner download their own document', function () {
    [$owner, , $document] = docFixture();

    $response = $this->actingAs($owner)
        ->get(route('public-profile.documents.download', ['document' => $document->id]));

    $response->assertOk();
    expect($response->streamedContent())->toBe(DOC_BYTES);
});

it('refuses another signed-in user', function () {
    [, , $document] = docFixture();

    $this->actingAs(docBystander())
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('refuses a guest by redirecting to login', function () {
    [, , $document] = docFixture();

    $this->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertRedirect(route('login'));
});

it('refuses the owner of a different profile', function () {
    [, , $document] = docFixture();

    // Another user who does have their own approved carrier profile. An
    // approved profile is publicly viewable, which must not extend to the
    // document behind it.
    $other = docBystander();
    $other->profile()->firstOrFail()->update(['provides_transport' => true, 'status' => 'approved']);

    $this->actingAs($other)
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('lets a superadmin download through the admin route', function () {
    [, , $document] = docFixture();

    $admin = User::factory()->superadmin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]));

    $response->assertOk();
    expect($response->streamedContent())->toBe(DOC_BYTES);
});

it('refuses a non-superadmin on the admin route', function () {
    [, , $document] = docFixture();

    // Even the document's own owner, because the admin group is superadmin
    // only. Self-service goes through the owner route instead.
    [$owner] = docFixture();

    $this->actingAs(docBystander())
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

it('sends the owner to the self-service route instead of the admin one', function () {
    // The owner-or-superadmin branch inside the admin controller is unreachable
    // for an ordinary owner. That is fine now: the owner has their own route,
    // and this pins that the admin surface stays closed to them.
    [$owner, , $document] = docFixture();

    $this->actingAs($owner)
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('admin.profiles.documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

// ── filenames ─────────────────────────────────────────────────────────────

it('neutralises a spreadsheet formula in a filename', function () {
    $storage = app(ProfileDocumentStorage::class);

    expect($storage->sanitizeFilename('=1+1.pdf'))->toBe("'=1+1.pdf");
    expect($storage->sanitizeFilename('+cmd.pdf'))->toBe("'+cmd.pdf");
    expect($storage->sanitizeFilename('-2.pdf'))->toBe("'-2.pdf");
    expect($storage->sanitizeFilename('@sum.pdf'))->toBe("'@sum.pdf");
    expect($storage->sanitizeFilename('licence.pdf'))->toBe('licence.pdf');
});

it('reports a missing file instead of erroring', function () {
    [$owner, , $document] = docFixture();

    Storage::disk(ProfileDocumentStorage::DISK)->delete($document->stored_path);

    $this->actingAs($owner)
        ->from(route('public-profile.edit'))
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertRedirect(route('public-profile.edit'))
        ->assertSessionHasErrors('document');
});

// ── upload and download agree on the disk ─────────────────────────────────

it('an uploaded document is immediately readable', function () {
    Storage::fake(ProfileDocumentStorage::DISK);

    $owner = User::factory()->create();
    Profile::factory()->for($owner)->transporter()->create();

    $this->actingAs($owner)->post('/public-profile/documents', [
        'type' => 'license',
        'file' => UploadedFile::fake()->create('licence.pdf', 8, 'application/pdf'),
    ])->assertRedirect();

    $document = $owner->profile()->firstOrFail()->documents()->firstOrFail();

    // Upload and download must agree, which they did not before.
    expect(app(ProfileDocumentStorage::class)->exists($document))->toBeTrue();
    expect(Storage::disk('public')->exists($document->stored_path))->toBeFalse();

    $this->actingAs($owner)
        ->get(route('public-profile.documents.download', ['document' => $document->id]))
        ->assertOk();
});
