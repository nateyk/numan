<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentReview;
use App\Models\MarketplacePaymentTransaction;
use App\Services\MarketplacePaymentReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplacePaymentReviewController extends Controller
{
    public function store(
        Request $request,
        MarketplacePaymentTransaction $transaction,
        MarketplacePaymentReviewService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', Rule::in(MarketplacePaymentReview::REASONS)],
        ]);

        $review = $service->open($transaction, $request->user(), $data['reason']);

        return back()->with('success', "Chapa review #{$review->id} opened. No refund was issued.");
    }

    public function update(
        Request $request,
        MarketplacePaymentReview $review,
        MarketplacePaymentReviewService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                'investigating',
                'submitted_to_chapa',
                'provider_reported_refunded',
                'closed_no_refund',
            ])],
            'provider_reference' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $service->transition(
            $review,
            $request->user(),
            $data['status'],
            $data['provider_reference'] ?? null,
        );

        return back()->with('success', 'Review status recorded. No provider action was performed.');
    }
}
