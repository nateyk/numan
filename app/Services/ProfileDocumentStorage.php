<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The only place a public profile document is written to or read from disk.
 *
 * A single disk constant is the point: uploads and downloads drifted apart once
 * already, and a document that saves successfully but cannot be downloaded is
 * worse than one that fails to save.
 *
 * Never returns a path, a URL or a disk id. Only a streamed download or a
 * redirect, so a caller cannot accidentally hand one out.
 */
class ProfileDocumentStorage
{
    public const DISK = 'private';

    public const DIRECTORY = 'profile-documents';

    public function store(Profile $profile, UploadedFile $file, string $type): ProfileDocument
    {
        $path = $file->store(self::DIRECTORY.'/'.$profile->id, self::DISK);

        return $profile->documents()->create([
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
        ]);
    }

    public function delete(ProfileDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->stored_path);
        $document->delete();
    }

    public function exists(ProfileDocument $document): bool
    {
        return Storage::disk(self::DISK)->exists($document->stored_path);
    }

    /**
     * Stream the file to an authorised owner or admin.
     */
    public function download(ProfileDocument $document): BinaryFileResponse|RedirectResponse
    {
        if (! $this->exists($document)) {
            return back()->withErrors(['document' => 'File not found.']);
        }

        return response()->download(
            Storage::disk(self::DISK)->path($document->stored_path),
            $this->sanitizeFilename($document->original_name),
        );
    }

    /**
     * Neutralise a leading spreadsheet formula character.
     *
     * The filename is user-supplied and ends up in a Content-Disposition
     * header, where a leading =, +, - or @ is interpreted by Excel and Sheets.
     */
    public function sanitizeFilename(string $name): string
    {
        if (preg_match('/^[=+\-@]/', $name)) {
            return "'".$name;
        }

        return $name;
    }
}
