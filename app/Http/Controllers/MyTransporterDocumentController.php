<?php

namespace App\Http\Controllers;

use App\Models\TransporterDocument;
use App\Services\TransporterDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Lets a transporter download the documents they uploaded themselves.
 *
 * The admin route is inside the superadmin middleware, so without this a
 * transporter can never see their own licence after uploading it. The owner
 * branch in the admin controller was unreachable for exactly that reason.
 *
 * This lives outside the admin group on purpose: it is a self-service action,
 * not an administrative one, and it authorises on ownership rather than on
 * privilege.
 */
class MyTransporterDocumentController extends Controller
{
    public function __construct(
        protected TransporterDocumentStorage $storage,
    ) {}

    public function download(Request $request, TransporterDocument $document): BinaryFileResponse|RedirectResponse
    {
        $profile = $document->profile;

        abort_unless($profile !== null, 404);

        // downloadDocument, not view. The view policy deliberately lets anyone
        // see an approved profile, which is right for a directory listing and
        // far too permissive for somebody's licence.
        Gate::forUser($request->user())->authorize('downloadDocument', $profile);

        // Defence in depth: the policy says owner-or-superadmin, so an
        // approved-but-not-mine profile must still be refused.
        if ($profile->user_id !== $request->user()->id && ! $request->user()->is_superadmin) {
            abort(403);
        }

        return $this->storage->download($document);
    }
}
