<?php

namespace App\Http\Controllers;

use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __construct(
        protected PlanLimitService $planLimitService
    ) {}

    /**
     * Display the usage dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        // Workspace quotas are not relevant to Numan's single-profile mode.
        // Preserve the legacy page for any future workspace-specific experience.
        if (! config('features.workspace.ui')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $limits = $this->planLimitService->getLimits($workspace->plan_name);

        $usage = [
            'workspaces' => [
                'label' => 'Total Workspaces',
                'current' => $user->ownedWorkspaces()->count(),
                'limit' => $limits['workspaces'],
                'message' => $this->planLimitService->getWorkspaceLimitMessage($user),
            ],
            'team_members' => [
                'label' => 'Team Members',
                'current' => $workspace->users()->count() + $workspace->invitations()->count(),
                'limit' => $limits['team_members'],
                'message' => $this->planLimitService->getTeamMemberLimitMessage($workspace),
            ],
            'api_keys' => [
                'label' => 'API Keys',
                'current' => $workspace->apiKeys()->count(),
                'limit' => $limits['api_keys'],
                'message' => $this->planLimitService->getApiKeyLimitMessage($workspace),
            ],
            'webhooks' => [
                'label' => 'Outgoing Webhooks',
                'current' => $workspace->webhookEndpoints()->count(),
                'limit' => $limits['webhooks'],
                'message' => $this->planLimitService->getWebhookLimitMessage($workspace),
            ],
        ];

        return Inertia::render('usage/index', [
            'workspace' => [
                'name' => $workspace->name,
                'plan' => $workspace->plan_name,
            ],
            'usage' => $usage,
        ]);
    }
}
