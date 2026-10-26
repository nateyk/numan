<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->superadmin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);
});

it('superadmin can list marketplace plans', function () {
    $this->actingAs($this->superadmin)->get('/admin/marketplace-plans')->assertSuccessful();
});

it('superadmin can create marketplace plan', function () {
    $this->actingAs($this->superadmin)->post('/admin/marketplace-plans', [
        'slug' => 'test-plan',
        'name' => 'Test Plan',
        'description' => 'Test',
        'price_monthly_etb' => 500,
        'price_yearly_etb' => 5000,
        'sort_order' => 10,
        'limits' => ['active_freight_posts' => 10, 'interests_per_week' => 20],
        'features' => ['Feature A', 'Feature B'],
    ])->assertRedirect();

    expect(MarketplacePlan::where('slug', 'test-plan')->exists())->toBeTrue();
});

it('superadmin can update a plan price', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($this->superadmin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_weekly_etb' => 199,
        'price_monthly_etb' => 599,
        'price_yearly_etb' => 4999,
        'limits' => ['active_freight_posts' => -1, 'interests_per_week' => -1],
        'features' => ['Updated'],
    ])->assertRedirect();

    $plan->refresh();
    expect((float) $plan->price_monthly_etb)->toBe(599.0);
    expect((float) $plan->price_weekly_etb)->toBe(199.0);
});

it('rejects a negative price', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($this->superadmin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_weekly_etb' => -5,
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
    ])->assertSessionHasErrors('price_weekly_etb');
});

it('rejects a limit below -1', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($this->superadmin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
        'limits' => ['active_freight_posts' => -2],
    ])->assertSessionHasErrors('limits.active_freight_posts');
});

it('ignores non marketplace limit keys on update', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($this->superadmin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
        'limits' => [
            'active_freight_posts' => -1,
            'team_members' => 99,
            'workspaces' => 99,
            'api_keys' => 99,
            'webhooks' => 99,
        ],
    ])->assertRedirect();

    $limits = MarketplacePlan::find($plan->id)->limits;

    expect($limits)->toHaveKey('active_freight_posts');
    expect($limits)->not->toHaveKey('team_members');
    expect($limits)->not->toHaveKey('workspaces');
    expect($limits)->not->toHaveKey('api_keys');
    expect($limits)->not->toHaveKey('webhooks');
});

it('toggles a plan active without deleting it', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($this->superadmin)
        ->patch("/admin/marketplace-plans/{$plan->slug}/toggle")
        ->assertRedirect();

    expect(MarketplacePlan::find($plan->id)->is_active)->toBeFalse();
    expect(MarketplacePlan::find($plan->id)->trashed())->toBeFalse();

    $this->actingAs($this->superadmin)
        ->patch("/admin/marketplace-plans/{$plan->slug}/toggle")
        ->assertRedirect();

    expect(MarketplacePlan::find($plan->id)->is_active)->toBeTrue();
});

it('refuses to delete a plan that has subscriptions', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplaceSubscription::create([
        'workspace_id' => Workspace::factory()->create()->id,
        'plan_id' => $plan->id,
        'user_id' => $this->superadmin->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    $response = $this->actingAs($this->superadmin)
        ->delete("/admin/marketplace-plans/{$plan->slug}");

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect(MarketplacePlan::withTrashed()->find($plan->id))->not->toBeNull();
    expect(MarketplacePlan::withTrashed()->find($plan->id)->trashed())->toBeFalse();
});

it('can delete a plan with no subscriptions', function () {
    $plan = MarketplacePlan::create([
        'slug' => 'unused-plan',
        'name' => 'Unused',
        'price_monthly_etb' => 100,
        'price_yearly_etb' => 1000,
    ]);

    $this->actingAs($this->superadmin)
        ->delete("/admin/marketplace-plans/{$plan->slug}")
        ->assertRedirect();

    expect(MarketplacePlan::withTrashed()->find($plan->id)->trashed())->toBeTrue();
});

it('superadmin can update the free limits', function () {
    $this->actingAs($this->superadmin)->put('/admin/marketplace-settings', [
        'free_active_posts' => 5,
        'free_interests_per_week' => 12,
        'free_contact_reveals' => 7,
    ])->assertRedirect();

    expect(MarketplaceSetting::where('key', 'free_active_posts')->value('value'))->toBe(5);
    expect(MarketplaceSetting::where('key', 'free_interests_per_week')->value('value'))->toBe(12);
    expect(MarketplaceSetting::where('key', 'free_contact_reveals')->value('value'))->toBe(7);
});

it('rejects free limits below -1', function () {
    $this->actingAs($this->superadmin)->put('/admin/marketplace-settings', [
        'free_active_posts' => -5,
        'free_interests_per_week' => 3,
        'free_contact_reveals' => 3,
    ])->assertSessionHasErrors('free_active_posts');
});

it('normal user cannot access admin plans', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/marketplace-plans')->assertForbidden();
});
