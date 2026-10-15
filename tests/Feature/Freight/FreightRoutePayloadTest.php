<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\CargoType;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\TruckType;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The freight route payload, from the frontend's side.
 *
 * Every page that renders a route reads it through lib/freight-route.ts, which
 * copes with a row arriving under more than one set of key names. That only
 * works if each producer sends a usable name, so these assert they do.
 *
 * The bug this exists for: FreightPostResource sends origin / destination,
 * while the cards read originLocation / origin_location. Every card rendered
 * an em dash in place of the town, over live posts sitting in the database.
 * The name was in the payload the whole time; nothing was reading it.
 *
 * routeNamesOn() below reads the way the page does — trying each known
 * key — so these pass once any producer sends the name under any of them, and
 * fail when a producer stops sending a name at all.
 */

/**
 * A consignor: onboarded, posting freight, with a workspace.
 *
 * @return array{0: User, 1: Workspace}
 */
function routeConsignor(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'account_type' => 'personal',
        'display_name' => 'Route Consignor',
        'phone' => '+251900000001',
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return [$user->fresh(), $workspace];
}

/** An active post with named endpoints, so a card has something to render. */
function routeActivePost(User $owner, Workspace $workspace): FreightPost
{
    return FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'origin_location_id' => Location::factory()->create([
            'name' => 'Kombolcha',
            'region_name' => 'Amhara',
        ])->id,
        'destination_location_id' => Location::factory()->create([
            'name' => 'Hawassa',
            'region_name' => 'Sidama',
        ])->id,
        'cargo_type_id' => CargoType::factory()->create(['name' => 'Wheat'])->id,
        'required_truck_type_id' => TruckType::factory()->create(['name' => 'Trailer'])->id,
    ]);
}

/** A transporter with a complete profile, able to hold interests. */
function routeTransporter(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Route Transporter',
        'phone' => '+251900000002',
    ]);

    TransporterProfile::firstOrCreate(['user_id' => $user->id], [
        'slug' => 'route-'.Str::lower(Str::random(8)),
        'display_name' => 'Route Transporter',
        'legal_name' => 'ROUTE TRANSPORT',
        'status' => 'approved',
        'phone' => '+251900000002',
    ]);

    return $user->fresh();
}

/**
 * The route names a card can render off a row, read the way the page reads.
 *
 * @return array{0: ?string, 1: ?string}
 */
function routeNamesOn(mixed $row): array
{
    $row = $row instanceof Collection ? $row->first() : $row;

    if (! is_array($row)) {
        return [null, null];
    }

    $read = static function (mixed $value): ?string {
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        if (! is_array($value)) {
            return null;
        }

        $name = $value['name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    };

    return [
        $read($row['origin'] ?? null)
            ?? $read($row['originLocation'] ?? null)
            ?? $read($row['origin_location'] ?? null),
        $read($row['destination'] ?? null)
            ?? $read($row['destinationLocation'] ?? null)
            ?? $read($row['destination_location'] ?? null),
    ];
}

function rendersKombolchaToHawassa(mixed $rows): bool
{
    [$origin, $destination] = routeNamesOn($rows);

    return $origin === 'Kombolcha' && $destination === 'Hawassa';
}

it('sends a discover card a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routeActivePost($owner, $workspace);

    $this->get('/freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('freight.data', rendersKombolchaToHawassa(...)));
});

it('sends the owner list a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routeActivePost($owner, $workspace);

    $this->actingAs($owner)
        ->get(route('freight.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('posts.data', rendersKombolchaToHawassa(...)));
});

it('sends an admin moderation table a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routeActivePost($owner, $workspace);

    $this->actingAs(User::factory()->superadmin()->create())
        ->get('/admin/freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('posts.data', rendersKombolchaToHawassa(...)));
});

it('sends the public post page the origin its details card reads', function () {
    [$owner, $workspace] = routeConsignor();
    $post = routeActivePost($owner, $workspace);

    $this->get(route('freight.public.show', $post->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('post.origin.name', 'Kombolcha'));
});

it('sends the owner their own post page the same shape the public one sends', function () {
    [$owner, $workspace] = routeConsignor();
    $post = routeActivePost($owner, $workspace);

    // Both render freight/show. When the two sent different shapes, the owner's
    // copy of their own post lost its route and its map while the public copy
    // of the same post kept both.
    $this->actingAs($owner)
        ->get(route('freight.show', $post->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('post.origin.name', 'Kombolcha')
            ->where('post.destination.name', 'Hawassa')
        );
});

it('sends a transporter their interest list a route they can read', function () {
    [$owner, $workspace] = routeConsignor();
    $post = routeActivePost($owner, $workspace);
    $transporter = routeTransporter();

    FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => FreightInterestStatus::Submitted->value,
    ]);

    $this->actingAs($transporter)
        ->get(route('freight.my-interests'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'interests',
            function (mixed $rows) {
                // This resource sends the endpoints as bare names rather than
                // location objects, so a page that only accepts objects renders
                // a dash here.
                $rows = $rows instanceof Collection ? $rows->all() : $rows;
                $post = $rows[0]['freight_post'] ?? $rows[0]['freightPost'];

                return ($post['origin'] ?? null) === 'Kombolcha'
                    && ($post['destination'] ?? null) === 'Hawassa';
            }
        ));
});
