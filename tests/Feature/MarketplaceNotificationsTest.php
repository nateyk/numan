<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FreightInterestStatusChanged;
use App\Notifications\FreightInterestSubmitted;
use App\Notifications\FreightPostModerated;
use App\Notifications\MarketplaceConnectionCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createConsignorForNotification(): User
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

function createApprovedTransporterForNotification(): User
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

it('sends notification when interest is submitted', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $transporter = createApprovedTransporterForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    $this->actingAs($transporter)->post("/my-freight/{$post->ulid}/interests", [
        'message' => 'Test',
        'contact_name' => 'John Doe',
        'contact_phone' => '+251911234567',
        'contact_email' => 'john@example.com',
        'bank_account_name' => 'John Doe',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'bank_account_number' => '1000123456789',
        'bank_iban' => 'ET123456789012345678901',
        'bank_swift' => 'CBEETAA',
    ]);

    Notification::assertSentTo($consignor, FreightInterestSubmitted::class, function ($notification) use ($post, $transporter) {
        return $notification->post->ulid === $post->ulid
            && $notification->interest->user_id === $transporter->id;
    });
});

it('sends notification when interest is shortlisted', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $transporter = createApprovedTransporterForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/shortlist");

    Notification::assertSentTo($transporter, FreightInterestStatusChanged::class, function ($notification) use ($interest) {
        return $notification->interest->id === $interest->id
            && $notification->oldStatus === 'submitted'
            && $notification->newStatus === 'shortlisted';
    });
});

it('sends notification when connection is created', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $transporter = createApprovedTransporterForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");

    Notification::assertSentTo($consignor, MarketplaceConnectionCreated::class);
    Notification::assertSentTo($transporter, MarketplaceConnectionCreated::class);
});

it('sends notification when interest is withdrawn', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $transporter = createApprovedTransporterForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);

    $this->actingAs($transporter)->post("/interests/{$interest->id}/withdraw");

    Notification::assertSentTo($consignor, FreightInterestStatusChanged::class, function ($notification) use ($interest) {
        return $notification->interest->id === $interest->id
            && $notification->oldStatus === 'submitted'
            && $notification->newStatus === 'withdrawn';
    });
});

it('does not send duplicate notification on idempotent accept', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $transporter = createApprovedTransporterForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");
    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");

    Notification::assertSentTo($consignor, MarketplaceConnectionCreated::class, 1);
    Notification::assertSentTo($transporter, MarketplaceConnectionCreated::class, 1);
});

it('sends notification when freight post is moderated', function () {
    Notification::fake();

    $consignor = createConsignorForNotification();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'submitted']);

    $admin = User::factory()->superadmin()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $admin->id]);
    $workspace->users()->attach($admin->id, ['role' => 'owner']);
    $admin->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($admin->fresh())->post("/admin/freight/{$post->ulid}/moderate", ['to_status' => 'active', 'reason' => 'Looks good']);

    Notification::assertSentTo($consignor, FreightPostModerated::class, function ($notification) use ($post) {
        return $notification->post->ulid === $post->ulid
            && $notification->action === 'approved';
    });
});
