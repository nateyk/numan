<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConsignor(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

function createApprovedTransporter(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'primary_location_id' => $location->id,
        'status' => 'approved',
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('transporter expresses interest and consignor shortlists', function () {
    $consignor = createConsignor();
    $transporter = createApprovedTransporter();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($transporter)->post("/my-freight/{$post->ulid}/interests", [
        'message' => 'I am interested',
        'contact_name' => 'John Doe',
        'contact_phone' => '+251911234567',
        'contact_email' => 'john@example.com',
        'bank_account_name' => 'John Doe',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'bank_account_number' => '1000123456789',
        'bank_iban' => 'ET123456789012345678901',
        'bank_swift' => 'CBEETAA',
    ])->assertRedirect();

    $interest = FreightInterest::first();
    expect($interest)->not->toBeNull()
        ->and($interest->status)->toBe(FreightInterestStatus::Submitted->value);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/shortlist")->assertRedirect();
    expect($interest->fresh()->status)->toBe(FreightInterestStatus::Shortlisted->value);
});

it('prevents self-interest', function () {
    $consignor = createConsignor();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($consignor)->post("/my-freight/{$post->ulid}/interests", [
        'message' => 'self',
        'contact_name' => 'John Doe',
        'contact_phone' => '+251911234567',
        'contact_email' => 'john@example.com',
        'bank_account_name' => 'John Doe',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'bank_account_number' => '1000123456789',
        'bank_iban' => 'ET123456789012345678901',
        'bank_swift' => 'CBEETAA',
    ])->assertSessionHasErrors('interest');

    expect(FreightInterest::count())->toBe(0);
});

it('allows transporter to withdraw before accept', function () {
    $consignor = createConsignor();
    $transporter = createApprovedTransporter();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($transporter)->post("/my-freight/{$post->ulid}/interests", [
        'message' => 'I am interested',
        'contact_name' => 'John Doe',
        'contact_phone' => '+251911234567',
        'contact_email' => 'john@example.com',
        'bank_account_name' => 'John Doe',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'bank_account_number' => '1000123456789',
        'bank_iban' => 'ET123456789012345678901',
        'bank_swift' => 'CBEETAA',
    ])->assertRedirect();
    $interest = FreightInterest::first();

    $this->actingAs($transporter)->post("/interests/{$interest->id}/withdraw")->assertRedirect();
    expect($interest->fresh()->status)->toBe(FreightInterestStatus::Withdrawn->value);
});
