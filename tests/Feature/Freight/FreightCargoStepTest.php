<?php

use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The cargo step after cargo_description was replaced by item_name.
 *
 * item_name is the single free-text description of what is being loaded. It used
 * to be a second, optional field next to a free-text cargo_description, which
 * meant two boxes for one piece of information and neither was required.
 */
uses(RefreshDatabase::class);

/**
 * A user allowed to post freight, with a workspace and a draft post.
 *
 * Duplicated from FreightWizardTest rather than extracted: that helper is local
 * to its file, and moving it into Pest.php is a wider change than this test
 * needs.
 *
 * @return array{0: User, 1: string}
 */
function cargoStepUser(): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'account_type' => 'personal',
        'display_name' => 'Test User',
        'phone' => '+251900000000',
        'primary_location_id' => $location->id,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    test()->actingAs($user->fresh())->post('/my-freight');

    return [$user->fresh(), FreightPost::firstOrFail()->ulid];
}

/**
 * @return array<string, mixed>
 */
function validCargoStep(array $overrides = []): array
{
    return array_merge([
        'cargo_type_id' => CargoType::factory()->create()->id,
        'item_name' => 'Teff in 40kg bags',
        'actual_weight' => 20,
        'weight_unit' => 'ton',
        'tariff_amount' => 15000,
        'tariff_currency' => 'ETB',
        'required_truck_type_id' => TruckType::factory()->create()->id,
        'truck_count' => 2,
    ], $overrides);
}

it('saves the cargo step', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep())
        ->assertRedirect();

    $post = FreightPost::where('ulid', $ulid)->firstOrFail();

    expect($post->item_name)->toBe('Teff in 40kg bags')
        ->and((float) $post->actual_weight)->toBe(20.0)
        ->and($post->weight_unit)->toBe('ton');
});

it('requires an item name', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['item_name' => null]))
        ->assertSessionHasErrors('item_name');

    expect(FreightPost::where('ulid', $ulid)->firstOrFail()->item_name)->toBeNull();
});

it('requires a cargo type', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['cargo_type_id' => null]))
        ->assertSessionHasErrors('cargo_type_id');
});

it('requires the weight, the tariff and the truck type', function () {
    [$user, $ulid] = cargoStepUser();

    // Each of these was previously optional and defaulted in the form, so a post
    // could be submitted with no truck type at all and be unmatchable.
    foreach (['actual_weight', 'weight_unit', 'tariff_amount', 'tariff_currency', 'required_truck_type_id', 'truck_count'] as $field) {
        $this->actingAs($user)
            ->put("/my-freight/{$ulid}/cargo", validCargoStep([$field => null]))
            ->assertSessionHasErrors($field);
    }
});

it('saves the tariff weight and its unit', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep([
            'tariff_weight' => 26,
            'tariff_weight_unit' => 'quintal',
        ]))
        ->assertRedirect();

    $post = FreightPost::where('ulid', $ulid)->firstOrFail();

    expect((float) $post->tariff_weight)->toBe(26.0)
        ->and($post->tariff_weight_unit)->toBe('quintal');
});

it('treats the tariff weight as optional', function () {
    [$user, $ulid] = cargoStepUser();

    // Optional on purpose: most loads are billed on actual weight, and forcing
    // everyone to type the same number twice would just be a wrong number typed
    // twice.
    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep())
        ->assertRedirect();

    expect(FreightPost::where('ulid', $ulid)->firstOrFail()->tariff_weight)->toBeNull();
});

it('rejects a tariff weight that is not a number', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['tariff_weight' => 'heavy']))
        ->assertSessionHasErrors('tariff_weight');
});

it('rejects a tariff weight unit that is not a real unit', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['tariff_weight_unit' => 'pounds']))
        ->assertSessionHasErrors('tariff_weight_unit');
});

it('rejects a negative weight or tariff', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['actual_weight' => -5]))
        ->assertSessionHasErrors('actual_weight');

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['tariff_amount' => -100]))
        ->assertSessionHasErrors('tariff_amount');
});

it('rejects a truck count outside the allowed range', function () {
    [$user, $ulid] = cargoStepUser();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep(['truck_count' => 0]))
        ->assertSessionHasErrors('truck_count');
});

it('no longer stores cargo_description from the cargo step', function () {
    [$user, $ulid] = cargoStepUser();

    // cargo_description is still a fillable column because historical rows carry
    // it and the admin search still matches on it, but it is not part of the
    // step any more. It is also no longer in rules(), so it is not persisted.
    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/cargo", validCargoStep([
            'cargo_description' => 'smuggled past validation',
        ]))
        ->assertRedirect();

    expect(FreightPost::where('ulid', $ulid)->firstOrFail()->cargo_description)->toBeNull();
});
