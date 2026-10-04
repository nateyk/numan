<?php

namespace App\Services;

use App\Models\TransporterDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The only place a transporter document is ever read from disk.
 *
 * Centralised so the disk name cannot drift between the code that writes a
 * document and the code that reads it. It already had: uploads wrote to
 * Storage::disk('private') while downloads read from Storage::disk('local'),
 * and 'private' was not a configured disk at all, so uploading a licence threw
 * and downloading one reported the file missing.
 *
 * Never returns a path, a URL or a disk id. Only a streamed download or a
 * redirect, so a caller cannot accidentally hand one out.
 */
class TransporterDocumentStorage
{
    /**
     * The disk, named explicitly and never resolved through FILESYSTEM_DISK.
     */
    public const DISK = 'private';

    /**
     * Where documents live within the disk.
     */
    public const DIRECTORY = 'transporter-documents';

    public function exists(TransporterDocument $document): bool
    {
        return Storage::disk(self::DISK)->exists($document->stored_path);
    }

    /**
     * Stream the file to an authorised owner or admin.
     */
    public function download(TransporterDocument $document): BinaryFileResponse|RedirectResponse
    {
        if (! $this->exists($document)) {
            return back()->withErrors(['document' => 'File not found.']);
        }

        $safeName = $this->sanitizeFilename($document->original_name);

        return response()->download(
            Storage::disk(self::DISK)->path($document->stored_path),
            $safeName,
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
