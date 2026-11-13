<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'loginStreak' => [
                'current' => $user->currentLoginStreak(),
                'longest' => $user->longestLoginStreak(),
            ],
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'bio' => $validated['bio'] ?? null,
            'timezone' => $validated['timezone'],
            'date_format' => $validated['date_format'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->getRawOriginal('avatar_url')) {
                Storage::disk('public')->delete($user->getRawOriginal('avatar_url'));
            }
            $user->avatar_url = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar') && $user->getRawOriginal('avatar_url')) {
            Storage::disk('public')->delete($user->getRawOriginal('avatar_url'));
            $user->avatar_url = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Update the user's locale preference.
     */
    public function updateLocale(UpdateLocaleRequest $request): RedirectResponse
    {
        $request->user()->update([
            'locale' => $request->validated()['locale'],
        ]);

        return back();
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Check for workspaces where the user is the owner
        $ownedWorkspaces = $user->ownedWorkspaces;

        foreach ($ownedWorkspaces as $workspace) {
            // If it's a shared workspace with other members, prevent deletion
            if ($workspace->personal_workspace !== true && $workspace->users()->count() > 1) {
                return back()->withErrors([
                    'account' => __('You cannot delete your account while you own a workspace with other members. Please transfer ownership or remove all members first.'),
                ]);
            }
        }

        // Cancel recurring Stripe billing before deactivating the account.
        // If the provider refuses, do not delete the account while charges
        // could continue; let the user retry or contact support.
        foreach ($ownedWorkspaces as $workspace) {
            if ($workspace->subscribed()) {
                $workspace->subscription()->cancelNow();
            }
        }

        DB::transaction(function () use ($ownedWorkspaces, $user): void {
            // Retain paid receipts and transactions for reconciliation, but
            // never leave active entitlements, pending payments or publicly
            // visible freight associated with a deleted single profile.
            MarketplacePaymentTransaction::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'initialized', 'pending_confirmation'])
                ->update([
                    'status' => 'canceled',
                    'open_manual_workspace_id' => null,
                ]);

            MarketplaceSubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'canceled', 'ends_at' => now()]);

            foreach ($ownedWorkspaces as $workspace) {
                MarketplaceSubscription::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('status', ['active', 'trialing'])
                    ->update(['status' => 'canceled', 'ends_at' => now()]);

                $workspace->delete();
            }

            FreightPost::query()
                ->where('user_id', $user->id)
                ->whereNull('hidden_at')
                ->update(['hidden_at' => now()]);

            // Revoke API tokens immediately; soft deletion preserves financial
            // history and audit evidence rather than deleting accounting rows.
            $user->tokens()->delete();
            $user->delete();
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
