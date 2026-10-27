<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy profile schema is gone.
 *
 * Shipping the additive Task 1 migration and this drop migration together means
 * there is exactly one profile table at rest. These assertions are the guard
 * against a later change quietly reintroducing one of the old tables or the
 * onboarding columns.
 */
uses(RefreshDatabase::class);

it('drops the legacy transporter tables and columns', function () {
    expect(Schema::hasTable('transporter_profiles'))->toBeFalse()
        ->and(Schema::hasTable('transporter_profile_truck_type'))->toBeFalse()
        ->and(Schema::hasTable('transporter_routes'))->toBeFalse()
        ->and(Schema::hasTable('transporter_documents'))->toBeFalse()
        ->and(Schema::hasTable('transporter_profile_moderation'))->toBeFalse()
        ->and(Schema::hasTable('transporter_profile_revisions'))->toBeFalse()
        ->and(Schema::hasColumn('public_profiles', 'onboarding_step'))->toBeFalse()
        ->and(Schema::hasColumn('public_profiles', 'onboarding_completed_at'))->toBeFalse();
});
