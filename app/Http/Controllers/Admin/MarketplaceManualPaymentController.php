<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ManualPaymentReviewService;
use App\Services\TransporterDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The admin queue for manual payment requests.
 *
 * The confirm dialog states the amount, the method, the reference and the payer
 * phone, and says in as many words to verify the amount in the banking app rather
 * than from the screenshot. A screenshot is whatever the payer chose to upload;
 * the bank statement is the truth.
 */
class MarketplaceManualPaymentController extends Controller
{
    public function __construct(
        protected ManualPaymentReviewService $review,
    ) {}

    public function index(): InertiaResponse
    {
        $queue = $this->review->queue();

        return Inertia::render('admin/marketplace-manual-payments', [
            'pending' => $queue['pending'],
            'recent' => $queue['recent'],
            'counts' => $queue['counts'],
        ]);
    }

    /**
     * Confirm, granting the plan through the shared fulfilment path.
     */
    public function confirm(Request $request, MarketplacePaymentTransaction $transaction): RedirectResponse
    {
        $outcome = $this->review->confirm($transaction, $request->user());

        if ($outcome['result'] === 'refused') {
            return back()->withErrors(['manual_payment' => $outcome['reason']]);
        }

        if ($outcome['result'] === 'already') {
            // Double-click. Say so plainly rather than pretending it just
            // happened.
            return back()->with('info', 'That payment was already confirmed.');
        }

        return back()->with('success', 'Payment confirmed and the plan activated.');
    }

    /**
     * Reject. The reason is required, because the payer sees it.
     */
    public function reject(Request $request, MarketplacePaymentTransaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'manual_rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $outcome = $this->review->reject(
            $transaction,
            $request->user(),
            $validated['manual_rejection_reason'],
        );

        if ($outcome['result'] === 'refused') {
            return back()->withErrors(['manual_payment' => $outcome['reason']]);
        }

        if ($outcome['result'] === 'already') {
            return back()->with('info', 'That payment was already rejected.');
        }

        return back()->with('success', 'Payment rejected and the payer notified.');
    }

    /**
     * Put an expired request back in the queue.
     */
    public function reopen(Request $request, MarketplacePaymentTransaction $transaction): RedirectResponse
    {
        $outcome = $this->review->reopen($transaction, $request->user());

        if ($outcome['result'] === 'refused') {
            return back()->withErrors(['manual_payment' => $outcome['reason']]);
        }

        if ($outcome['result'] === 'already') {
            return back()->with('info', 'That request is already awaiting confirmation.');
        }

        return back()->with('success', 'Request reopened with a fresh review window.');
    }

    /**
     * View the proof file.
     *
     * Owner or superadmin, the same rule as a transporter document. Images are
     * served inline so an admin can read them in the dialog; PDFs are an
     * attachment. nosniff is set because the file is whatever the payer uploaded,
     * and a browser that sniffed it as HTML would execute our page in their
     * session.
     */
    public function proof(Request $request, MarketplacePaymentTransaction $transaction): Response|BinaryFileResponse|RedirectResponse
    {
        $path = $transaction->manual_proof_path;

        abort_if(blank($path), 404);

        if (! Storage::disk(TransporterDocumentStorage::DISK)->exists($path)) {
            return back()->withErrors(['proof' => 'File not found.']);
        }

        $mime = $transaction->mime ?: $this->guessMime($path);

        $isImage = str_starts_with((string) $mime, 'image/');

        $response = response()->file(
            Storage::disk(TransporterDocumentStorage::DISK)->path($path),
            [
                // Inline for images so they render in the dialog; an attachment
                // for anything else, so a PDF never navigates away from the
                // queue.
                'Content-Disposition' => $isImage ? 'inline' : 'attachment',
                'Content-Type' => $isImage ? $mime : 'application/octet-stream',
                // The whole point: a payer-chosen file must never be sniffed.
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
                'Cache-Control' => 'private, no-store',
            ],
        );

        return $response;
    }

    private function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}
