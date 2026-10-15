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
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

/**
 * A post with named endpoints, so a card has something to render.
 *
 * The status is a parameter because the admin moderation queue defaults to
 * submitted — that is the queue it exists to show — so a fixture that is only
 * ever active is simply absent from it.
 */
function routePost(User $owner, Workspace $workspace, string $status = FreightPostStatus::Active->value): FreightPost
{
    return FreightPost::factory()->create([
        'status' => $status,
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
 * The route names a card can render off a payload, read the way the page reads.
 *
 * Accepts either one row or a list of them: Inertia hands a paginator's rows
 * over as a plain array here and as a Collection elsewhere, and the difference
 * is not worth a test per producer.
 *
 * @return array{0: ?string, 1: ?string}
 */
function routeNamesOn(mixed $rows): array
{
    $row = $rows instanceof Collection ? $rows->all() : $rows;

    if (! is_array($row)) {
        return [null, null];
    }

    if (array_is_list($row)) {
        $row = $row[0] ?? null;

        if (! is_array($row)) {
            return [null, null];
        }
    }

    $read = static function (mixed $value): ?string {
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        if ($value instanceof Collection || $value instanceof Arrayable) {
            $value = $value instanceof Collection ? $value->all() : $value->toArray();
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

/**
 * Assert a list payload carries the named route a card can render.
 *
 * Compared with expect() rather than through assertInertia so a failure shows
 * the names that were actually sent, which is the whole question here.
 */
function expectRowsRenderTheRoute(mixed $rows): void
{
    expect(routeNamesOn($rows))->toBe(['Kombolcha', 'Hawassa']);
}

/** The Inertia props of a response, without Inertia's dot-path walker. */
function inertiaProps(TestResponse $response): array
{
    return $response->viewData('page')['props'] ?? [];
}

it('sends a discover card a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routePost($owner, $workspace);

    $response = $this->get('/freight');
    $response->assertOk();

    expectRowsRenderTheRoute(inertiaProps($response)['freight']['data'] ?? null);
});

it('sends the owner list a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routePost($owner, $workspace);

    $response = $this->actingAs($owner)->get(route('freight.index'));
    $response->assertOk();

    expectRowsRenderTheRoute(inertiaProps($response)['posts']['data'] ?? null);
});

it('sends an admin moderation table a route it can render', function () {
    [$owner, $workspace] = routeConsignor();
    routePost($owner, $workspace, FreightPostStatus::Submitted->value);

    $response = $this->actingAs(User::factory()->superadmin()->create())
        ->get('/admin/freight');
    $response->assertOk();

    expectRowsRenderTheRoute(inertiaProps($response)['posts']['data'] ?? null);
});

it('sends the public post page the origin its details card reads', function () {
    [$owner, $workspace] = routeConsignor();
    $post = routePost($owner, $workspace);

    $this->get(route('freight.public.show', $post->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('post.origin.name', 'Kombolcha'));
});

it('sends the owner their own post page the same shape the public one sends', function () {
    [$owner, $workspace] = routeConsignor();
    $post = routePost($owner, $workspace);

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
    $post = routePost($owner, $workspace);
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
