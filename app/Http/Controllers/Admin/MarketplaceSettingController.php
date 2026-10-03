<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceSettingController extends Controller
{
    public function __construct(
        protected MarketplaceSettingsService $settings,
    ) {}

    /**
     * Update the Free limits. These apply immediately: the entitlement
     * service reads them on every check.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'free_active_posts' => ['required', 'integer', 'min:-1'],
            'free_interests_per_week' => ['required', 'integer', 'min:-1'],
            'free_contact_reveals_per_week' => ['required', 'integer', 'min:-1'],
        ]);

        $this->settings->setMany($data);

        return back()->with('success', 'Free plan limits updated.');
    }
}
