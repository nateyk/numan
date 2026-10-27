<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileDocumentRequest;
use App\Models\ProfileDocument;
use App\Services\ProfileCompletionService;
use App\Services\ProfileDocumentStorage;
use App\Services\SavePublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Private verification documents on the consolidated public profile.
 *
 * The file never leaves through a URL: only an authorised owner or superadmin
 * can stream it.
 */
class ProfileDocumentController extends Controller
{
    public function __construct(
        protected SavePublicProfile $profiles,
        protected ProfileDocumentStorage $documents,
        protected ProfileCompletionService $completion,
    ) {}

    public function store(StoreProfileDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profiles->save($user, []);
        Gate::authorize('update', $profile);

        $this->documents->store(
            $profile,
            $request->file('file'),
            $request->validated('type'),
        );

        $this->completion->update($profile);

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(ProfileDocument $document): RedirectResponse
    {
        Gate::authorize('update', $document->profile);

        $this->documents->delete($document);
        $this->completion->update($document->profile);

        return back()->with('success', 'Document removed.');
    }

    public function download(ProfileDocument $document): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('downloadDocument', $document->profile);

        return $this->documents->download($document);
    }
}
