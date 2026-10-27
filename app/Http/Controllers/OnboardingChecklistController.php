<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingChecklistController extends Controller
{
    /**
     * Return the current checklist step completion state for the authenticated user.
     *
     * Two kinds of step live here. The account steps — bio, two-factor, billing,
     * team — are unchanged and still apply to everybody. The marketplace steps
     * appear only for somebody who has picked up a capability, and come first,
     * because "what can I do here and what is stopping me" is the question this
     * widget exists to answer, and a profile photo is not the answer to it.
     */
    public function index(Request $request, MarketplaceReadiness $readiness): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if ($user->onboarding_checklist_dismissed_at) {
            return response()->json(['dismissed' => true, 'steps' => []]);
        }

        $steps = [
            ...$this->marketplaceSteps($readiness->for($user)),
            [
                'id' => 'complete_profile',
                'label' => 'Complete your profile',
                'description' => 'Add a bio and profile photo',
                'completed' => ! empty($user->bio),
                'href' => '/settings/profile',
            ],
            [
                'id' => 'enable_2fa',
                'label' => 'Enable two-factor authentication',
                'description' => 'Secure your account with 2FA',
                'completed' => $user->two_factor_confirmed_at !== null,
                'href' => '/settings/two-factor',
            ],
            [
                'id' => 'invite_member',
                'label' => 'Invite a team member',
                'description' => 'Grow your workspace team',
                'completed' => $workspace && $workspace->users()->count() > 1,
                'href' => '/team',
            ],
            [
                'id' => 'connect_billing',
                'label' => 'Connect billing',
                'description' => 'Subscribe to a plan for full access',
                'completed' => $workspace && $workspace->subscribed('default'),
                'href' => '/billing',
            ],
        ];

        return response()->json([
            'dismissed' => false,
            'steps' => $steps,
            'completed' => collect($steps)->where('completed', true)->count(),
            'total' => count($steps),
        ]);
    }

    /**
     * One step per capability the user has chosen but is not yet ready for.
     *
     * A capability already satisfied is not listed at all. Telling somebody who
     * can already post freight to go and make sure they can post freight is noise,
     * and noise is what makes people dismiss a checklist for good.
     *
     * @param  array{capabilities: array<string, bool>, ready: array<string, bool>, missing: array<string, list<string>>}  $state
     * @return list<array<string, mixed>>
     */
    private function marketplaceSteps(array $state): array
    {
        $labels = [
            'posts_freight' => 'Ready to post freight',
            'provides_transport' => 'Ready to carry freight',
        ];

        $steps = [];

        foreach ($state['ready'] as $capability => $ready) {
            // Only capabilities the user has actually chosen. Iterating readiness
            // alone reports "not ready" for a capability they never picked up,
            // which is a nag about something they are not trying to do.
            if ($ready || ! $state['capabilities'][$capability]) {
                continue;
            }

            $steps[] = [
                'id' => $capability.'_ready',
                'label' => $labels[$capability],
                // Names the fields that are actually missing. "Complete your
                // profile" sent people to a page with no phone number on it.
                'description' => 'Still needed: '.implode(', ', $state['missing'][$capability]),
                'completed' => false,
                'href' => '/public-profile',
            ];
        }

        return $steps;
    }

    /**
     * Dismiss the onboarding checklist permanently.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'onboarding_checklist_dismissed_at' => now(),
        ])->save();

        return back();
    }
}
