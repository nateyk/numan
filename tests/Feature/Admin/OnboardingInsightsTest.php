<?php

use App\Models\OnboardingStepLog;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_superadmin' => true]);
});

it('displays onboarding insights page for superadmins', function () {
    $this->actingAs($this->admin)
        ->get('/admin/onboarding-insights')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/onboarding-insights')
            ->has('metrics')
            ->has('funnel')
            ->has('dropOff')
            ->has('dailyCompletions')
        );
});

it('prevents non-admin access', function () {
    $user = User::factory()->create(['is_superadmin' => false]);

    $this->actingAs($user)
        ->get('/admin/onboarding-insights')
        ->assertForbidden();
});

it('shows correct completion rate metrics', function () {
    // Create 4 users in last 30 days, 2 onboarded
    User::factory()->count(2)->create([
        'created_at' => now()->subDays(5),
        'onboarded_at' => now()->subDays(4),
    ]);
    User::factory()->count(2)->create([
        'created_at' => now()->subDays(5),
        'onboarded_at' => null,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/onboarding-insights')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('metrics.total_registered', 5) // 4 + admin
            ->where('metrics.total_onboarded', 3) // 2 created + admin (factory sets onboarded_at)
        );
});

it('shows funnel step data', function () {
    $user = User::factory()->create();

    OnboardingStepLog::create(['user_id' => $user->id, 'step' => 'welcome', 'action' => 'viewed']);
    OnboardingStepLog::create(['user_id' => $user->id, 'step' => 'welcome', 'action' => 'completed']);
    OnboardingStepLog::create(['user_id' => $user->id, 'step' => 'workspace', 'action' => 'viewed']);

    $this->actingAs($this->admin)
        ->get('/admin/onboarding-insights')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('funnel', 3)
            ->where('funnel.0.step', 'welcome')
            ->where('funnel.0.viewed', 1)
            ->where('funnel.0.completed', 1)
            ->where('funnel.1.step', 'workspace')
            ->where('funnel.1.viewed', 1)
            ->where('funnel.1.completed', 0)
        );
});

it('shows drop-off data for incomplete steps', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Both viewed welcome
    OnboardingStepLog::create(['user_id' => $user1->id, 'step' => 'welcome', 'action' => 'viewed']);
    OnboardingStepLog::create(['user_id' => $user2->id, 'step' => 'welcome', 'action' => 'viewed']);

    // Only user1 completed welcome
    OnboardingStepLog::create(['user_id' => $user1->id, 'step' => 'welcome', 'action' => 'completed']);

    $this->actingAs($this->admin)
        ->get('/admin/onboarding-insights')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dropOff.0.step', 'welcome')
            ->where('dropOff.0.dropped', 1)
            ->where('dropOff.0.drop_rate', 50)
        );
});

it('keeps historical step logs queryable after the wizard is retired', function () {
    $user = User::factory()->create(['onboarded_at' => null]);

    OnboardingStepLog::create(['user_id' => $user->id, 'step' => 'workspace', 'action' => 'viewed']);

    expect(OnboardingStepLog::count())->toBe(1);

    $this->actingAs($this->admin)
        ->get('/admin/onboarding-insights')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('funnel', function ($funnel): bool {
                $workspace = collect($funnel)->firstWhere('step', 'workspace');

                return $workspace !== null && $workspace['viewed'] === 1;
            })
        );
});

it('has removed the retired step tracking endpoint', function () {
    $user = User::factory()->create(['onboarded_at' => null]);

    $this->actingAs($user)
        ->post('/onboarding/track-step', [
            'step' => 'workspace',
            'action' => 'viewed',
        ])
        ->assertNotFound();

    expect(OnboardingStepLog::count())->toBe(0);
});

it('has removed the wizard page that wrote the welcome log', function () {
    $user = User::factory()->create(['onboarded_at' => null]);

    $this->actingAs($user)
        ->get('/onboarding')
        ->assertRedirect('/public-profile');

    expect(OnboardingStepLog::where('step', 'capabilities')->count())->toBe(0);
});
