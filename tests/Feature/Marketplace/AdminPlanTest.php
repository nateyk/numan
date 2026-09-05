<?php

use App\Models\MarketplacePlan;
use App\Models\User;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->superadmin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);
    // 2FA not required for test? bypass
});

it('superadmin can list marketplace plans', function () {
    $this->actingAs($this->superadmin)->get('/admin/marketplace-plans')->assertSuccessful();
});

it('superadmin can create marketplace plan', function () {
    $response = $this->actingAs($this->superadmin)->post('/admin/marketplace-plans', [
        'slug' => 'test-plan',
        'name' => 'Test Plan',
        'description' => 'Test',
        'price_monthly_etb' => 500,
        'price_yearly_etb' => 5000,
        'is_active' => true,
        'is_popular' => false,
        'sort_order' => 10,
        'limits' => ['active_freight_posts' => 10, 'monthly_posts' => 20, 'interests_per_month' => 30],
        'features' => ['Feature A', 'Feature B'],
    ]);

    $response->assertRedirect();
    expect(MarketplacePlan::where('slug', 'test-plan')->exists())->toBeTrue();
});

it('superadmin can update and archive plan', function () {
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $this->actingAs($this->superadmin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'pro',
        'name' => 'Pro Updated',
        'description' => 'Updated',
        'price_monthly_etb' => 1099,
        'price_yearly_etb' => 10990,
        'is_active' => true,
        'is_popular' => true,
        'sort_order' => 2,
        'limits' => ['active_freight_posts' => 30],
        'features' => ['Updated'],
    ])->assertRedirect();

    expect(MarketplacePlan::find($plan->id)->name)->toBe('Pro Updated');

    $this->actingAs($this->superadmin)->delete("/admin/marketplace-plans/{$plan->slug}")->assertRedirect();
    expect(MarketplacePlan::withTrashed()->find($plan->id)->trashed())->toBeTrue();

    $this->actingAs($this->superadmin)->post("/admin/marketplace-plans/{$plan->id}/restore")->assertRedirect();
    expect(MarketplacePlan::find($plan->id)->trashed())->toBeFalse();
});

it('normal user cannot access admin plans', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/marketplace-plans')->assertForbidden();
});
