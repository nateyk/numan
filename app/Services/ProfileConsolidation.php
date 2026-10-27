<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves the legacy transporter identity onto the consolidated public profile.
 *
 * Everything here is driver-neutral and uses the query builder rather than
 * Eloquent models, because it runs inside a migration where model boot hooks and
 * casts would add behaviour the schema does not have yet.
 */
class ProfileConsolidation
{
    /**
     * Copy every transporter row and its children onto public_profiles.
     */
    public function backfill(): void
    {
        $hasLegacy = Schema::hasTable('transporter_profiles');

        if ($hasLegacy) {
            DB::table('transporter_profiles')
                ->orderBy('id')
                ->chunkById(100, function ($transporters): void {
                    foreach ($transporters as $transporter) {
                        $this->backfillTransporter($transporter);
                    }
                });
        }

        $this->ensureEveryProfileHasSlug();
        $this->ensureTransportProfilesHaveStatus();

        if ($hasLegacy) {
            $this->copyChildren();
        }
    }

    /**
     * Recreate the legacy transporter schema from the consolidated profile.
     *
     * This is the rollback path for the drop migration. It never throws a
     * deliberate exception: a down() that refuses to run is a down() nobody can
     * use in an incident.
     */
    public function restoreLegacy(): void
    {
        $this->createLegacySchema();

        DB::table('public_profiles')
            ->orderBy('id')
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    if (! $this->shouldRestoreTransporter($profile)) {
                        continue;
                    }

                    $this->restoreTransporter($profile);
                }
            });

        $this->restoreChildren();
    }

    /**
     * Drop the legacy transporter tables in dependency order.
     */
    public function dropLegacy(): void
    {
        Schema::dropIfExists('transporter_profile_truck_type');
        Schema::dropIfExists('transporter_routes');
        Schema::dropIfExists('transporter_documents');
        Schema::dropIfExists('transporter_profile_moderation');
        Schema::dropIfExists('transporter_profile_revisions');
        Schema::dropIfExists('transporter_profiles');
    }

    /**
     * @param  object{user_id: int|string, slug: ?string, display_name: ?string, legal_name: ?string, phone: ?string, whatsapp: ?string, email: ?string, business_description: ?string, primary_location_id: ?int, is_company: mixed, status: ?string, verification_level: ?string, completion_score: mixed, phone_visible: mixed, whatsapp_visible: mixed, email_visible: mixed, created_at: ?string, updated_at: ?string}  $transporter
     */
    private function backfillTransporter(object $transporter): void
    {
        $profile = DB::table('public_profiles')
            ->where('user_id', $transporter->user_id)
            ->first();

        $verificationStatus = $this->verificationStatus($transporter->verification_level);
        $now = now();

        if ($profile === null) {
            DB::table('public_profiles')->insert([
                'user_id' => $transporter->user_id,
                'slug' => $this->uniqueSlug((string) ($transporter->slug ?: 'profile')),
                'posts_freight' => false,
                'provides_transport' => true,
                'account_type' => $transporter->is_company ? 'business' : 'personal',
                'display_name' => $transporter->display_name,
                'phone' => $transporter->phone,
                'whatsapp' => $transporter->whatsapp,
                'email' => $transporter->email,
                'business_description' => $transporter->business_description,
                'primary_location_id' => $transporter->primary_location_id,
                'legal_name' => $transporter->legal_name,
                'status' => $transporter->status ?: 'draft',
                'verification_status' => $verificationStatus,
                'completion_score' => (int) ($transporter->completion_score ?? 0),
                'phone_visible' => (bool) $transporter->phone_visible,
                'whatsapp_visible' => (bool) $transporter->whatsapp_visible,
                'email_visible' => (bool) $transporter->email_visible,
                'created_at' => $transporter->created_at ?? $now,
                'updated_at' => $transporter->updated_at ?? $now,
            ]);

            return;
        }

        $changes = [
            'provides_transport' => true,
            'slug' => $this->uniqueSlug((string) ($transporter->slug ?: $profile->slug), (int) $profile->id),
        ];

        // Fill blanks only. A consignor who has already chosen a display name or
        // phone keeps it; the transporter row is the fallback, not the authority.
        foreach (['display_name', 'legal_name', 'phone', 'whatsapp', 'primary_location_id'] as $field) {
            if ($this->isBlank($profile->{$field}) && ! $this->isBlank($transporter->{$field})) {
                $changes[$field] = $transporter->{$field};
            }
        }

        foreach (['email', 'business_description'] as $field) {
            if (! $this->isBlank($transporter->{$field})) {
                $changes[$field] = $transporter->{$field};
            }
        }

        if (! $this->isBlank($transporter->status)) {
            $changes['status'] = $transporter->status;
        }

        if ($transporter->completion_score !== null) {
            $changes['completion_score'] = (int) $transporter->completion_score;
        }

        // Never downgrade a profile that has already been verified for something
        // other than the legacy transporter field.
        if ($verificationStatus === 'verified' || $this->isBlank($profile->verification_status)) {
            $changes['verification_status'] = $verificationStatus;
        }

        // Refusal wins. public_profiles defaults every flag to true while
        // transporter_profiles defaults every flag to false, so copying either
        // side alone would publish numbers somebody had hidden.
        $changes['phone_visible'] = $this->bothVisible($profile->phone_visible, $transporter->phone_visible);
        $changes['whatsapp_visible'] = $this->bothVisible($profile->whatsapp_visible, $transporter->whatsapp_visible);
        $changes['email_visible'] = $this->bothVisible($profile->email_visible, $transporter->email_visible);

        DB::table('public_profiles')->where('id', $profile->id)->update($changes);
    }

    /**
     * Give every profile a slug, including rows that predate the column.
     */
    private function ensureEveryProfileHasSlug(): void
    {
        DB::table('public_profiles')
            ->where(function ($query): void {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $name = $profile->display_name
                        ?: DB::table('users')->where('id', $profile->user_id)->value('name')
                        ?: 'profile';

                    $slug = $this->uniqueSlug(Str::slug($name).'-'.Str::random(4));

                    DB::table('public_profiles')->where('id', $profile->id)->update(['slug' => $slug]);
                }
            });
    }

    /**
     * A profile that offers transport but never had a legacy listing starts as a
     * draft, so it can be reviewed instead of silently appearing in the directory.
     */
    private function ensureTransportProfilesHaveStatus(): void
    {
        $hasLegacy = Schema::hasTable('transporter_profiles');

        DB::table('public_profiles')
            ->where('provides_transport', true)
            ->whereNull('status')
            ->orderBy('id')
            ->chunkById(100, function ($profiles) use ($hasLegacy): void {
                foreach ($profiles as $profile) {
                    if ($hasLegacy && DB::table('transporter_profiles')->where('user_id', $profile->user_id)->exists()) {
                        continue;
                    }

                    DB::table('public_profiles')->where('id', $profile->id)->update([
                        'status' => 'draft',
                        'completion_score' => 0,
                    ]);
                }
            });
    }

    /**
     * Copy every child table by joining the legacy profile's user to the public
     * profile. IDs and stored paths are preserved so existing downloads keep
     * resolving.
     */
    private function copyChildren(): void
    {
        $profileIds = DB::table('public_profiles')->pluck('id', 'user_id');

        if (Schema::hasTable('transporter_profile_truck_type')) {
            $rows = DB::table('transporter_profile_truck_type as pivot')
                ->join('transporter_profiles as transporter', 'transporter.id', '=', 'pivot.transporter_profile_id')
                ->get([
                    'pivot.transporter_profile_id',
                    'pivot.truck_type_id',
                    'pivot.created_at',
                    'pivot.updated_at',
                    'transporter.user_id',
                ]);

            foreach (array_chunk($rows->all(), 500) as $chunk) {
                $inserts = [];

                foreach ($chunk as $row) {
                    $profileId = $profileIds[$row->user_id] ?? null;

                    if ($profileId === null) {
                        continue;
                    }

                    $inserts[] = [
                        'profile_id' => $profileId,
                        'truck_type_id' => $row->truck_type_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($inserts !== []) {
                    DB::table('profile_truck_type')->insertOrIgnore($inserts);
                }
            }
        }

        if (Schema::hasTable('transporter_routes')) {
            $rows = DB::table('transporter_routes as route')
                ->join('transporter_profiles as transporter', 'transporter.id', '=', 'route.transporter_profile_id')
                ->get([
                    'route.id',
                    'route.origin_location_id',
                    'route.destination_location_id',
                    'route.created_at',
                    'route.updated_at',
                    'transporter.user_id',
                ]);

            foreach (array_chunk($rows->all(), 500) as $chunk) {
                $inserts = [];

                foreach ($chunk as $row) {
                    $profileId = $profileIds[$row->user_id] ?? null;

                    if ($profileId === null) {
                        continue;
                    }

                    $inserts[] = [
                        'id' => $row->id,
                        'profile_id' => $profileId,
                        'origin_location_id' => $row->origin_location_id,
                        'destination_location_id' => $row->destination_location_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($inserts !== []) {
                    DB::table('profile_routes')->insertOrIgnore($inserts);
                }
            }
        }

        if (Schema::hasTable('transporter_documents')) {
            $rows = DB::table('transporter_documents as document')
                ->join('transporter_profiles as transporter', 'transporter.id', '=', 'document.transporter_profile_id')
                ->get([
                    'document.id',
                    'document.type',
                    'document.original_name',
                    'document.stored_path',
                    'document.mime',
                    'document.size',
                    'document.status',
                    'document.reviewed_by',
                    'document.reviewed_at',
                    'document.created_at',
                    'document.updated_at',
                    'transporter.user_id',
                ]);

            foreach (array_chunk($rows->all(), 500) as $chunk) {
                $inserts = [];

                foreach ($chunk as $row) {
                    $profileId = $profileIds[$row->user_id] ?? null;

                    if ($profileId === null) {
                        continue;
                    }

                    $inserts[] = [
                        'id' => $row->id,
                        'profile_id' => $profileId,
                        'type' => $row->type,
                        'original_name' => $row->original_name,
                        'stored_path' => $row->stored_path,
                        'mime' => $row->mime,
                        'size' => $row->size,
                        'status' => $row->status,
                        'reviewed_by' => $row->reviewed_by,
                        'reviewed_at' => $row->reviewed_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($inserts !== []) {
                    DB::table('profile_documents')->insertOrIgnore($inserts);
                }
            }
        }

        if (Schema::hasTable('transporter_profile_moderation')) {
            $rows = DB::table('transporter_profile_moderation as moderation')
                ->join('transporter_profiles as transporter', 'transporter.id', '=', 'moderation.transporter_profile_id')
                ->get([
                    'moderation.id',
                    'moderation.from_status',
                    'moderation.to_status',
                    'moderation.reason',
                    'moderation.moderator_id',
                    'moderation.created_at',
                    'moderation.updated_at',
                    'transporter.user_id',
                ]);

            foreach (array_chunk($rows->all(), 500) as $chunk) {
                $inserts = [];

                foreach ($chunk as $row) {
                    $profileId = $profileIds[$row->user_id] ?? null;

                    if ($profileId === null) {
                        continue;
                    }

                    $inserts[] = [
                        'id' => $row->id,
                        'profile_id' => $profileId,
                        'kind' => 'listing',
                        'from_status' => $row->from_status,
                        'to_status' => $row->to_status,
                        'reason' => $row->reason,
                        'moderator_id' => $row->moderator_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($inserts !== []) {
                    DB::table('profile_moderations')->insertOrIgnore($inserts);
                }
            }
        }
    }

    /**
     * @param  object{id: int|string, user_id: int|string, slug: ?string, display_name: ?string, legal_name: ?string, phone: ?string, whatsapp: ?string, email: ?string, business_description: ?string, primary_location_id: ?int, account_type: ?string, status: ?string, verification_status: ?string, completion_score: mixed, phone_visible: mixed, whatsapp_visible: mixed, email_visible: mixed, created_at: ?string, updated_at: ?string}  $profile
     */
    private function shouldRestoreTransporter(object $profile): bool
    {
        if ($profile->provides_transport) {
            return true;
        }

        return DB::table('profile_truck_type')->where('profile_id', $profile->id)->exists()
            || DB::table('profile_routes')->where('profile_id', $profile->id)->exists()
            || DB::table('profile_documents')->where('profile_id', $profile->id)->exists();
    }

    /**
     * @param  object{id: int|string, user_id: int|string, slug: ?string, display_name: ?string, legal_name: ?string, phone: ?string, whatsapp: ?string, email: ?string, business_description: ?string, primary_location_id: ?int, account_type: ?string, status: ?string, verification_status: ?string, completion_score: mixed, phone_visible: mixed, whatsapp_visible: mixed, email_visible: mixed, created_at: ?string, updated_at: ?string}  $profile
     */
    private function restoreTransporter(object $profile): void
    {
        $name = $profile->display_name
            ?: DB::table('users')->where('id', $profile->user_id)->value('name')
            ?: 'profile';

        DB::table('transporter_profiles')->insertOrIgnore([
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'slug' => $this->uniqueLegacySlug((string) ($profile->slug ?: Str::slug($name).'-'.Str::random(4))),
            'display_name' => $profile->display_name ?: $name,
            'legal_name' => $profile->legal_name,
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'email' => $profile->email,
            'business_description' => $profile->business_description,
            'is_company' => $profile->account_type === 'business',
            'status' => $profile->status ?: 'draft',
            'verification_level' => $profile->verification_status === 'verified' ? 'verified' : 'unverified',
            'completion_score' => (int) ($profile->completion_score ?? 0),
            'phone_visible' => (bool) $profile->phone_visible,
            'whatsapp_visible' => (bool) $profile->whatsapp_visible,
            'email_visible' => (bool) $profile->email_visible,
            'primary_location_id' => $profile->primary_location_id,
            'created_at' => $profile->created_at ?? now(),
            'updated_at' => $profile->updated_at ?? now(),
        ]);
    }

    private function restoreChildren(): void
    {
        $transporterIds = DB::table('transporter_profiles')->pluck('id', 'user_id');
        $profileIds = DB::table('public_profiles')->pluck('id', 'user_id');

        $rows = DB::table('profile_truck_type as pivot')
            ->join('public_profiles as profile', 'profile.id', '=', 'pivot.profile_id')
            ->get(['pivot.profile_id', 'pivot.truck_type_id', 'pivot.created_at', 'pivot.updated_at', 'profile.user_id']);

        foreach (array_chunk($rows->all(), 500) as $chunk) {
            $inserts = [];

            foreach ($chunk as $row) {
                $transporterId = $transporterIds[$row->user_id] ?? null;
                $profileId = $profileIds[$row->user_id] ?? null;

                if ($transporterId === null || $profileId === null) {
                    continue;
                }

                $inserts[] = [
                    'transporter_profile_id' => $transporterId,
                    'truck_type_id' => $row->truck_type_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($inserts !== []) {
                DB::table('transporter_profile_truck_type')->insertOrIgnore($inserts);
            }
        }

        $rows = DB::table('profile_routes as route')
            ->join('public_profiles as profile', 'profile.id', '=', 'route.profile_id')
            ->get([
                'route.id',
                'route.origin_location_id',
                'route.destination_location_id',
                'route.created_at',
                'route.updated_at',
                'profile.user_id',
            ]);

        foreach (array_chunk($rows->all(), 500) as $chunk) {
            $inserts = [];

            foreach ($chunk as $row) {
                $transporterId = $transporterIds[$row->user_id] ?? null;

                if ($transporterId === null) {
                    continue;
                }

                $inserts[] = [
                    'id' => $row->id,
                    'transporter_profile_id' => $transporterId,
                    'origin_location_id' => $row->origin_location_id,
                    'destination_location_id' => $row->destination_location_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($inserts !== []) {
                DB::table('transporter_routes')->insertOrIgnore($inserts);
            }
        }

        $rows = DB::table('profile_documents as document')
            ->join('public_profiles as profile', 'profile.id', '=', 'document.profile_id')
            ->get([
                'document.id',
                'document.type',
                'document.original_name',
                'document.stored_path',
                'document.mime',
                'document.size',
                'document.status',
                'document.reviewed_by',
                'document.reviewed_at',
                'document.created_at',
                'document.updated_at',
                'profile.user_id',
            ]);

        foreach (array_chunk($rows->all(), 500) as $chunk) {
            $inserts = [];

            foreach ($chunk as $row) {
                $transporterId = $transporterIds[$row->user_id] ?? null;

                if ($transporterId === null) {
                    continue;
                }

                $inserts[] = [
                    'id' => $row->id,
                    'transporter_profile_id' => $transporterId,
                    'type' => $row->type,
                    'original_name' => $row->original_name,
                    'stored_path' => $row->stored_path,
                    'mime' => $row->mime,
                    'size' => $row->size,
                    'status' => $row->status,
                    'reviewed_by' => $row->reviewed_by,
                    'reviewed_at' => $row->reviewed_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($inserts !== []) {
                DB::table('transporter_documents')->insertOrIgnore($inserts);
            }
        }

        $rows = DB::table('profile_moderations as moderation')
            ->join('public_profiles as profile', 'profile.id', '=', 'moderation.profile_id')
            ->where('moderation.kind', 'listing')
            ->get([
                'moderation.id',
                'moderation.from_status',
                'moderation.to_status',
                'moderation.reason',
                'moderation.moderator_id',
                'moderation.created_at',
                'moderation.updated_at',
                'profile.user_id',
            ]);

        foreach (array_chunk($rows->all(), 500) as $chunk) {
            $inserts = [];

            foreach ($chunk as $row) {
                $transporterId = $transporterIds[$row->user_id] ?? null;

                if ($transporterId === null) {
                    continue;
                }

                $inserts[] = [
                    'id' => $row->id,
                    'transporter_profile_id' => $transporterId,
                    'from_status' => $row->from_status,
                    'to_status' => $row->to_status,
                    'reason' => $row->reason,
                    'moderator_id' => $row->moderator_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($inserts !== []) {
                DB::table('transporter_profile_moderation')->insertOrIgnore($inserts);
            }
        }
    }

    private function createLegacySchema(): void
    {
        Schema::dropIfExists('transporter_profile_revisions');
        Schema::dropIfExists('transporter_profile_moderation');
        Schema::dropIfExists('transporter_documents');
        Schema::dropIfExists('transporter_routes');
        Schema::dropIfExists('transporter_profile_truck_type');
        Schema::dropIfExists('transporter_profiles');

        Schema::create('transporter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('business_description')->nullable();
            $table->boolean('is_company')->default(false);
            $table->string('status')->default('draft')->comment('draft,pending,approved,rejected');
            $table->string('verification_level')->default('unverified')->comment('unverified,verified');
            $table->integer('completion_score')->default(0);
            $table->boolean('phone_visible')->default(false);
            $table->boolean('whatsapp_visible')->default(false);
            $table->boolean('email_visible')->default(false);
            $table->foreignId('primary_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['verification_level']);
            $table->index(['is_company']);
        });

        Schema::create('transporter_profile_truck_type', function (Blueprint $table): void {
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['transporter_profile_id', 'truck_type_id']);
        });

        Schema::create('transporter_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->unique(['transporter_profile_id', 'origin_location_id', 'destination_location_id'], 'transporter_routes_unique');
        });

        Schema::create('transporter_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('license,insurance,other');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('status')->default('pending')->comment('pending,approved,rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->index(['status']);
            $table->index(['type']);
        });

        Schema::create('transporter_profile_moderation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
        });

        Schema::create('transporter_profile_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status')->default('pending')->comment('pending,approved,rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->index(['status']);
        });
    }

    private function uniqueSlug(string $desired, ?int $ignoreProfileId = null): string
    {
        $base = Str::slug($desired) ?: 'profile';
        $slug = $base;
        $i = 1;

        while (DB::table('public_profiles')
            ->where('slug', $slug)
            ->when($ignoreProfileId !== null, fn ($query) => $query->where('id', '!=', $ignoreProfileId))
            ->exists()) {
            $slug = $base.'-'.Str::random(4).($i > 1 ? (string) $i : '');
            $i++;
        }

        return $slug;
    }

    private function uniqueLegacySlug(string $desired): string
    {
        $base = Str::slug($desired) ?: 'profile';
        $slug = $base;
        $i = 1;

        while (DB::table('transporter_profiles')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::random(4).($i > 1 ? (string) $i : '');
            $i++;
        }

        return $slug;
    }

    private function verificationStatus(?string $level): string
    {
        return $level === 'verified' ? 'verified' : 'unverified';
    }

    private function bothVisible(mixed $profileFlag, mixed $transporterFlag): bool
    {
        $profileVisible = $profileFlag === null ? true : (bool) $profileFlag;

        return $profileVisible && (bool) $transporterFlag;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
