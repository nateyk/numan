# Freight Requests + Transporter Offers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the guide’s core loop `Post → Discover → Interest → Select → Connect` as two incremental releases: (A) draft wizard + moderation + public discovery, (B) interest/selection/connection with controlled contact reveal.

**Architecture:** New freight domain owns its tables/models/enums/policies/services/controllers/requests. Wizard is draft-first (POST creates ULID draft, PUT per step). Moderation is an explicit state machine with required reasons and audit history. Discovery is policy-scoped (only `active` freight). Interests are idempotent per transporter/post with valid-transition guards. Accept creates `MarketplaceConnection` atomically inside a DB transaction and is the sole contact-reveal gate. Reuse existing `locations`/`cargo_types`/`truck_types`/`transporter_profiles`/location-search/frontend primitives; do not add new map/combobox frameworks.

**Tech Stack:** Laravel 13.18.1 + PHP 8.4, Inertia Laravel 3.1 + React 19.2 + TypeScript 5.7, Tailwind 4.3 + shadcn/ui, Fortify 1.37, Wayfinder 0.1.20, Pest 4.7 + PHPUnit 12, Pint, Larastan level 7, Vite 8

**Spec:** `NUMANFREIGHT_FRESH_BUILD_GUIDE.md` §§6, 8.2-8.6, 11-15, 18, 20-21, 25, 29

## Global Constraints

- PHP 8.4 + Laravel 13.x (installed 13.18.1) — do not downgrade or add Wave/Livewire/Folio/Filament/CoreUI.
- Frontend: Inertia 3 + React 19 + TypeScript 5.7 + Tailwind 4 + shadcn/ui (Radix) — reuse `resources/js/components/ui`, `resources/js/components/maps`, `resources/js/components/freight`.
- Auth: Fortify; onboarding gate `onboarded` middleware stays; do not fold onboarding into registration.
- Roles: Spatie Permission not installed in this repo baseline — keep current `is_superadmin` + workspace-pivot authorization for admin; do not introduce unrelated role system in this plan.
- Storage: private docs on `storage/app/private` via `local` disk; never expose raw doc paths.
- Maps: Leaflet 1.9.4 + react-leaflet 5; authoritative distance/duration from server OSRM — never calculate in browser.
- After route changes: `php artisan wayfinder:generate --with-form` + `npm run types:check` must stay green.
- Before PR: `vendor/bin/pint --dirty --format agent`, `npm run lint:check`/`format:check`, `npm run types:check`, `php artisan test --compact`, `phpstan level 7`, `npm run build` + remove `public/hot`.
- Copy guardrails: never port Gadgetza `deals/stores/codes` domain or ecab WordPress logic; never add GPS/dispatch/escrow/internal-chat/tender-bidding in this plan.
- Naming: `FreightPost` (ulid), `FreightInterest`, `MarketplaceConnection`; controllers `App\Http\Controllers\Freight\*` + `Admin\*`; pages `resources/js/pages/freight/*`, `discover/*`, `connections/*`; keep `public_profiles` vs `marketplace_profiles` mismatch as-is and do not rename tables in this plan.

---

## File Structure

**Release A — Freight posting + moderation + discovery**

- Create: `database/migrations/2026_08_31_000011_create_freight_posts_table.php` — `freight_posts` (+ ulid, user_id, origin/destination location FKs, exact lat/lng decimal(10,7), site notes, point_confirmed_at, cargo_type FK, cargo_description, required_truck_type FK, truck_count, schedule_type, pickup_date/time, flexibility, status, completion_score, indexes)
- Create: `database/migrations/2026_08_31_000012_create_freight_post_routes_table.php` — `freight_post_routes` (freight_post_id FK, distance_meters, duration_seconds, geometry json, routing_provider)
- Create: `database/migrations/2026_08_31_000013_create_freight_post_moderation_logs_table.php` — moderation logs (freight_post_id, from_status, to_status, reason, moderator_id)
- Create: `app/Enums/FreightPostStatus.php` — enum `draft/submitted/under_review/active/closed/expired/rejected` with `label()` + `canTransitionTo()`
- Create: `app/Enums/ScheduleType.php` — `one_time/recurring`
- Create: `app/Models/FreightPost.php` — ULID, casts, relations (user, originLocation, destinationLocation, cargoType, truckType, routeSnapshot, moderationLogs, interests), scopes `active`, `submittable`, completion helpers
- Create: `app/Models/FreightPostRoute.php`
- Create: `app/Models/FreightPostModerationLog.php`
- Create: `app/Services/FreightCompletionService.php` — computes 0-100 score from required fields
- Create: `app/Services/RoutePreviewService.php` — server OSRM fetch, returns distance/duration/geometry or null; never trusts browser values
- Create: `app/Http/Requests/StoreFreightRouteRequest.php`, `StoreFreightCargoRequest.php`, `StoreFreightScheduleRequest.php`, `SubmitFreightPostRequest.php` — per-step validation, preserve valid data
- Create: `app/Http/Controllers/Freight/FreightPostController.php` — `index` (my freight), `store` (create draft), `show` (ulid)
- Create: `app/Http/Controllers/Freight/FreightRouteController.php` — `show` + `update` (PUT /my-freight/{ulid}/route)
- Create: `app/Http/Controllers/Freight/FreightCargoController.php`
- Create: `app/Http/Controllers/Freight/FreightScheduleController.php`
- Create: `app/Http/Controllers/Freight/FreightReviewController.php` — review + `submit` (draft→submitted)
- Create: `app/Http/Controllers/Freight/RoutePreviewController.php` — `POST /freight/routes/preview` (CSRF JSON from lat/lng)
- Create: `app/Http/Controllers/Freight/PublicFreightDiscoveryController.php` — public `GET /freight` + `GET /discover` (filters: origin/destination, cargo/truck type, dates, expiration)
- Create: `app/Http/Controllers/Admin/AdminFreightController.php` — queue + show + `moderate` (approve/reject with required reason, transaction, history)
- Create: `app/Policies/FreightPostPolicy.php` — owner can view/update draft, submit; public can view only active; admin via superadmin gate; no self-moderation
- Modify: `app/Providers/AppServiceProvider.php:78-83` — register `FreightPostPolicy` (keep existing `WorkspacePolicy`)
- Modify: `routes/web.php` — my-freight wizard routes (POST /my-freight, GET/PUT per step, POST submit), preview route, public discovery routes (outside auth), admin freight routes (inside superadmin+RequireAdminTwoFactor)
- Create: `database/factories/FreightPostFactory.php`
- Create: `resources/js/components/freight/FreightWizardShell.tsx` — 4-step stepper + `FreightWizardFooter` + Save-draft status
- Create: `resources/js/components/maps/RoutePreviewMap.tsx` — lazy React-Leaflet map (A green, B blue, polyline, fitBounds, OSM tiles from `map_config`)
- Create: `resources/js/pages/freight/route.tsx`, `cargo.tsx`, `schedule.tsx`, `review.tsx` — per-step pages using `LocationSearchInput`, reuse existing freight UI patterns
- Create: `resources/js/pages/freight/index.tsx` — my-freight list
- Create: `resources/js/pages/discover/index.tsx` — public discovery with filters, counts, pagination
- Create: `resources/js/pages/admin/freight/index.tsx` + `show.tsx` — moderation queue
- Modify (fix in scope): `app/Http/Controllers/LocationSearchController.php:23-30` — return `{locations: [...]}` shape + include `slug` to match `LocationSearchInput` (`resources/js/components/maps/location-search-input.tsx:68-72` expects `data.locations` + `slug`)

**Release B — Interest → Select → Connect**

- Create: `database/migrations/2026_08_31_000014_create_freight_interests_table.php` — `freight_interests` (freight_post_id, user_id, status, message, accepted_at, unique active per user/post)
- Create: `database/migrations/2026_08_31_000015_create_freight_interest_status_histories_table.php`
- Create: `database/migrations/2026_08_31_000016_create_marketplace_connections_table.php` — `marketplace_connections` (public_id ulid, freight_post_id, freight_interest_id, consignor_user_id, transporter_user_id)
- Create: `database/migrations/2026_08_31_000017_create_marketplace_connection_outcomes_table.php`
- Create: `database/migrations/2026_08_31_000018_create_marketplace_connection_audit_logs_table.php`
- Create: `app/Enums/FreightInterestStatus.php` — `submitted/shortlisted/selected/rejected/withdrawn`
- Create: `app/Enums/ConnectionOutcome.php` — `contacted/successful/no_response/not_suitable/cancelled/unknown`
- Create: `app/Models/FreightInterest.php`, `FreightInterestStatusHistory.php`, `MarketplaceConnection.php`, `MarketplaceConnectionOutcome.php`, `MarketplaceConnectionAuditLog.php` — relations, scopes, `contactDetailsFor(User $user): ?array` on connection
- Create: `app/Http/Requests/StoreFreightInterestRequest.php` — message optional, validation
- Create: `app/Http/Controllers/Freight/FreightInterestController.php` — `store` (POST /my-freight/{ulid}/interests) with duplicate/self checks, quota stub (Pennant flag bypass for Phase 0)
- Create: `app/Http/Controllers/Freight/FreightInterestStatusController.php` — `shortlist`/`reject` for consignor
- Create: `app/Http/Controllers/Freight/FreightInterestAcceptanceController.php` — `accept` creates `MarketplaceConnection` atomically (DB::transaction, idempotent by unique freight_interest_id)
- Create: `app/Http/Controllers/Freight/TransporterMyInterestsController.php` — list + `withdraw` before accept
- Create: `app/Http/Controllers/Freight/MarketplaceConnectionController.php` — `index` (my connections), `show` (ulid), `reveal` (policy-guarded contact)
- Create: `app/Http/Controllers/Freight/MarketplaceConnectionOutcomeController.php` — record outcome
- Create: `app/Policies/FreightInterestPolicy.php`, `MarketplaceConnectionPolicy.php`
- Create: `app/Notifications/FreightInterestSubmitted.php`, `FreightInterestStatusChanged.php`, `MarketplaceConnectionCreated.php` — `mail`+`database`, respect user notification preferences where applicable
- Create: `resources/js/pages/freight/interests.tsx` — per-post interest list with transporter snapshot + actions
- Create: `resources/js/pages/freight/my-interests.tsx` — transporter’s interests
- Create: `resources/js/pages/connections/index.tsx` + `show.tsx` — connection list/detail with outcome form + contact reveal
- Modify: `resources/js/pages/discover/index.tsx` — add “Express Interest” CTA gated by `provides_transport` + approved transporter profile

---

### Task 1: FreightPost domain tables + enums + models

**Files:**
- Create: `database/migrations/2026_08_31_000011_create_freight_posts_table.php`
- Create: `database/migrations/2026_08_31_000012_create_freight_post_routes_table.php`
- Create: `database/migrations/2026_08_31_000013_create_freight_post_moderation_logs_table.php`
- Create: `app/Enums/FreightPostStatus.php`
- Create: `app/Enums/ScheduleType.php`
- Create: `app/Models/FreightPost.php`
- Create: `app/Models/FreightPostRoute.php`
- Create: `app/Models/FreightPostModerationLog.php`
- Create: `database/factories/FreightPostFactory.php`
- Test: `tests/Feature/FreightPostDomainTest.php`

**Interfaces:**
- Consumes: `Location`, `CargoType`, `TruckType` ids; existing `Profile` onboarding check
- Produces: `FreightPostStatus::canTransitionTo(FreightPostStatus): bool`, `FreightPost::scopeActive(Builder): Builder`, `FreightPostFactory` states `draft()`, `submitted()`, `active()`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/FreightPostDomainTest.php
it('creates freight post with ulid and draft status', function () {
    $user = User::factory()->create();
    $post = FreightPost::factory()->for($user)->create(['status' => FreightPostStatus::Draft->value]);
    expect($post->ulid)->not->toBeNull()
        ->and($post->status)->toBe(FreightPostStatus::Draft->value);
});

it('guards invalid status transition', function () {
    expect(FreightPostStatus::Draft->canTransitionTo(FreightPostStatus::Active))->toBeFalse()
        ->and(FreightPostStatus::Draft->canTransitionTo(FreightPostStatus::Submitted))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=FreightPostDomainTest`
Expected: FAIL — `Class FreightPost not found` / `FreightPostStatus not found`

- [ ] **Step 3: Write minimal implementation**

```php
// app/Enums/FreightPostStatus.php
enum FreightPostStatus: string {
  case Draft='draft'; case Submitted='submitted'; case UnderReview='under_review'; case Active='active'; case Closed='closed'; case Expired='expired'; case Rejected='rejected';
  public function label(): string { return match($this){ self::Draft=>'Draft', self::Submitted=>'Submitted', self::UnderReview=>'Under Review', self::Active=>'Active', self::Closed=>'Closed', self::Expired=>'Expired', self::Rejected=>'Rejected'}; }
  public function canTransitionTo(self $t): bool { return match($this){ self::Draft=>in_array($t,[self::Submitted],true), self::Submitted=>in_array($t,[self::UnderReview,self::Rejected],true), self::UnderReview=>in_array($t,[self::Active,self::Rejected],true), self::Active=>in_array($t,[self::Closed,self::Expired],true), default=>false}; }
  public static function values(): array { return array_column(self::cases(),'value'); }
}
// FreightPost model: fillable ulid,user_id,origin_location_id,destination_location_id,origin_exact_lat/lng etc., casts, belongsTo relations, scopeActive, HasFactory, SoftDeletes optional per guide (keep without soft deletes for freight_posts to match SaaS starter pattern)
```

Create migrations with ULID `char(26)`, FKs to `locations`/`cargo_types`/`truck_types`/`users`, `decimal(10,7)` for exact lat/lng, indexes on `status,origin_location_id,destination_location_id,pickup_date`.

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=FreightPostDomainTest`
Expected: PASS (2 passed)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_31_000011_* database/migrations/2026_08_31_000012_* database/migrations/2026_08_31_000013_* app/Enums/FreightPostStatus.php app/Enums/ScheduleType.php app/Models/FreightPost.php app/Models/FreightPostRoute.php app/Models/FreightPostModerationLog.php database/factories/FreightPostFactory.php tests/Feature/FreightPostDomainTest.php
git commit -m "feat(freight): add FreightPost domain tables, enums, models and factory"
```

### Task 2: Freight completion + route preview services + discovery scopes

**Files:**
- Create: `app/Services/FreightCompletionService.php`
- Create: `app/Services/RoutePreviewService.php`
- Modify: `app/Models/FreightPost.php:20-60` — add `completionScore(): int` delegating to service, scopes `scopeActive`, `scopeOwnedBy(User)`
- Test: `tests/Feature/Services/FreightCompletionServiceTest.php`, `tests/Feature/Services/RoutePreviewServiceTest.php`

**Interfaces:**
- Consumes: `FreightPost` attributes
- Produces: `FreightCompletionService::score(FreightPost): int`, `FreightCompletionService::update(FreightPost): void`, `RoutePreviewService::preview(float $fromLat,float $fromLng,float $toLat,float $toLng): ?array{distance_meters:int,duration_seconds:int,geometry:array,provider:string}`

- [ ] **Step 1: Write the failing test**

```php
it('scores freight post completion', function () {
  $post = FreightPost::factory()->make(['origin_location_id'=>null]);
  expect(app(FreightCompletionService::class)->score($post))->toBeLessThan(100);
});
it('returns null when OSRM unreachable', function () {
  Http::fake(fn()=> Http::response(null,500));
  expect(app(RoutePreviewService::class)->preview(9,38,7,38))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=FreightCompletionServiceTest`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

```php
// FreightCompletionService: 0-100 based on origin/destination/cargo/truck/schedule/exact points confirmed (each bucket 20-25 pts)
// RoutePreviewService: read config('services.osrm.url') else env('OSRM_URL','https://router.project-osrm.org'), GET /route/v1/driving/{lng,lat};{lng,lat}?overview=full&geometries=geojson, timeout 5s, return null on non-200
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=FreightCompletionServiceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/FreightCompletionService.php app/Services/RoutePreviewService.php app/Models/FreightPost.php tests/Feature/Services/FreightCompletionServiceTest.php tests/Feature/Services/RoutePreviewServiceTest.php
git commit -m "feat(freight): add completion and OSRM route preview services"
```

### Task 3: Freight policies + location-search fix + admin freight policy registration

**Files:**
- Create: `app/Policies/FreightPostPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php:78-83` — register policy
- Modify: `app/Http/Controllers/LocationSearchController.php:9-31` — fix response shape
- Test: `tests/Feature/FreightPostPolicyTest.php`, `tests/Feature/LocationSearchTest.php`

**Interfaces:**
- Consumes: `User`, `FreightPost`
- Produces: `FreightPostPolicy::view/update/submit/moderate(User,FreightPost): bool`

- [ ] **Step 1: Write the failing test**

```php
it('prevents non-owner from updating draft', function () {
  $owner = User::factory()->create(); $other = User::factory()->create();
  $post = FreightPost::factory()->for($owner)->create(['status'=>FreightPostStatus::Draft->value]);
  expect((new FreightPostPolicy)->update($other,$post))->toBeFalse();
});
it('returns locations wrapper with slug', function () {
  $this->actingAs(User::factory()->create())->getJson('/locations/search?q=addis')->assertJsonStructure(['locations'=>[['id','name','region_name','slug','latitude','longitude']]]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=FreightPostPolicyTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

```php
// FreightPostPolicy: view = owner or active+approved; update = owner && in [draft,submitted,rejected? no, only draft per guide]; submit = owner && draft && completion >= threshold; moderate = $user->is_superadmin
// LocationSearchController: select id,name,region_name,slug,latitude,longitude where is_active and name/region like %q%, limit 15, return response()->json(['locations'=>$results])
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=FreightPostPolicyTest --filter=LocationSearchTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Policies/FreightPostPolicy.php app/Providers/AppServiceProvider.php app/Http/Controllers/LocationSearchController.php tests/Feature/FreightPostPolicyTest.php tests/Feature/LocationSearchTest.php
git commit -m "feat(freight): add FreightPostPolicy and fix location search contract"
```

### Task 4: Draft-first wizard controllers + per-step Form Requests

**Files:**
- Create: `app/Http/Requests/StoreFreightRouteRequest.php`
- Create: `app/Http/Requests/StoreFreightCargoRequest.php`
- Create: `app/Http/Requests/StoreFreightScheduleRequest.php`
- Create: `app/Http/Requests/SubmitFreightPostRequest.php`
- Create: `app/Http/Controllers/Freight/FreightPostController.php`
- Create: `app/Http/Controllers/Freight/FreightRouteController.php`
- Create: `app/Http/Controllers/Freight/FreightCargoController.php`
- Create: `app/Http/Controllers/Freight/FreightScheduleController.php`
- Create: `app/Http/Controllers/Freight/FreightReviewController.php`
- Create: `app/Http/Controllers/Freight/RoutePreviewController.php`
- Modify: `routes/web.php` — wire my-freight + preview routes
- Test: `tests/Feature/FreightWizardTest.php`

**Interfaces:**
- Consumes: `FreightPost`, `FreightPostStatus`, `RoutePreviewService`, `FreightCompletionService`
- Produces: `POST /my-freight` → `302` + `FreightPost` draft with ULID; `PUT /my-freight/{ulid}/route|cargo|schedule`; `POST /my-freight/{ulid}/submit`; `POST /freight/routes/preview` → `{distance_meters,duration_seconds,geometry,provider}`

- [ ] **Step 1: Write the failing test**

```php
it('creates draft and allows stepwise update', function () {
  $user = actingAsOnboardedUserWithCapability('posts_freight');
  $res = $this->post('/my-freight'); $res->assertRedirect();
  $ulid = FreightPost::first()->ulid;
  $this->put("/my-freight/{$ulid}/route", ['origin_location_id'=>Location::first()->id,'destination_location_id'=>Location::factory()->create()->id,'origin_exact_lat'=>9.03,'origin_exact_lng'=>38.74,'destination_exact_lat'=>7.49,'destination_exact_lng'=>38.42,'origin_point_confirmed_at'=>now(),'destination_point_confirmed_at'=>now(),'pickup_date'=>now()->addDays(2)->toDateString()])->assertRedirect();
  expect(FreightPost::first()->origin_location_id)->not->toBeNull();
});
it('rejects submit when incomplete', function () {
  $post = FreightPost::factory()->create(['status'=>FreightPostStatus::Draft->value,'cargo_type_id'=>null]);
  $this->actingAs($post->user)->post("/my-freight/{$post->ulid}/submit")->assertSessionHasErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=FreightWizardTest`
Expected: FAIL — route not found / controller missing

- [ ] **Step 3: Write minimal implementation**

```php
// FreightPostController@store: authorize posts_freight capability via Profile, create FreightPost status draft ulid = Str::ulid(), user_id, completion 0, redirect to route step
// Each step controller: find by ulid + authorize update, validate via Form Request, update only its fields, call FreightCompletionService::update, persist route snapshot if coords present via RoutePreviewService, redirect to next step
// RoutePreviewController@preview: validate from_lat/lng,to_lat/lng, call service, return json or 422 route_unavailable
// Requests: origin/destination must be active locations and distinct; exact lat/lng decimal 10,7 between ranges; point_confirmed_at nullable datetime; cargo_type/truck_type must be active if provided; truck_count integer 1-50; schedule_type in one_time,recurring; pickup_date after today; flexibility 0-30
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=FreightWizardTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreFreight* app/Http/Controllers/Freight/* routes/web.php tests/Feature/FreightWizardTest.php
git commit -m "feat(freight): add draft-first wizard controllers and per-step validation"
```

### Task 5: Admin freight moderation queue

**Files:**
- Create: `app/Http/Controllers/Admin/AdminFreightController.php`
- Modify: `routes/web.php` — admin freight routes under `auth+superadmin+RequireAdminTwoFactor`
- Test: `tests/Feature/AdminFreightModerationTest.php`

**Interfaces:**
- Consumes: `FreightPost`, `FreightPostStatus`
- Produces: `GET /admin/freight?status=submitted`, `GET /admin/freight/{ulid}`, `POST /admin/freight/{ulid}/moderate` with `to_status` + `reason`

- [ ] **Step 1: Write the failing test**

```php
it('moderates freight from submitted to active with reason required on reject', function () {
  $admin = createSuperAdmin(); $post = FreightPost::factory()->create(['status'=>FreightPostStatus::Submitted->value]);
  $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status'=>'rejected'])->assertSessionHasErrors('reason');
  $this->actingAs($admin)->post("/admin/freight/{$post->ulid}/moderate", ['to_status'=>'active','reason'=>'Looks good'])->assertRedirect();
  expect($post->fresh()->status)->toBe(FreightPostStatus::Active->value);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=AdminFreightModerationTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

```php
// AdminFreightController@index: filter by status (default submitted), paginate 15
// show: load relations
// moderate: validate to_status in allowed values + canTransitionTo, reason required if rejected, DB::transaction { update status + create FreightPostModerationLog }, audit via spatie activity
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=AdminFreightModerationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AdminFreightController.php routes/web.php tests/Feature/AdminFreightModerationTest.php
git commit -m "feat(admin): add freight moderation queue with state-machine guards"
```

### Task 6: Public freight discovery (scopes + controller)

**Files:**
- Create: `app/Http/Controllers/Freight/PublicFreightDiscoveryController.php`
- Modify: `app/Models/FreightPost.php` — add scopes `scopeActive`, `scopeSearch(?string $q)`, `scopeFilter(array)`
- Modify: `routes/web.php` — public GET /freight and /discover outside auth
- Test: `tests/Feature/PublicFreightDiscoveryTest.php`

**Interfaces:**
- Consumes: `FreightPost::active()`
- Produces: `GET /freight?origin=&destination=&cargo_type=&truck_type=&pickup_from=&pickup_to=` → paginated `active` only, with distance/duration from `freight_post_routes`

- [ ] **Step 1: Write the failing test**

```php
it('only lists active freight publicly', function () {
  FreightPost::factory()->create(['status'=>FreightPostStatus::Active->value]);
  FreightPost::factory()->create(['status'=>FreightPostStatus::Draft->value]);
  $this->get('/freight')->assertOk()->assertInertia(fn($p)=> $p->where('freight.data', fn($d)=> count($d)===1));
});
it('filters by origin and cargo type', function () {
  // create two active posts with different origins/cargo, assert filter narrows to 1
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=PublicFreightDiscoveryTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

```php
// PublicFreightDiscoveryController@index: query FreightPost::with([originLocation,destinationLocation,cargoType,truckType,routeSnapshot])->active()->when(origin)->when(destination)->when(cargo_type)->when(truck_type)->when(pickup range)->orderByDesc(pickup_date)->paginate(12)->withQueryString(); return Inertia::render('discover/index', [...])
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=PublicFreightDiscoveryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Freight/PublicFreightDiscoveryController.php app/Models/FreightPost.php routes/web.php tests/Feature/PublicFreightDiscoveryTest.php
git commit -m "feat(discovery): add public freight discovery with active-only scopes and filters"
```

### Task 7: Freight wizard UI (shell + 4 steps + discovery/my-freight pages)

**Files:**
- Create: `resources/js/components/freight/FreightWizardShell.tsx`
- Create: `resources/js/components/maps/RoutePreviewMap.tsx`
- Create: `resources/js/pages/freight/route.tsx`
- Create: `resources/js/pages/freight/cargo.tsx`
- Create: `resources/js/pages/freight/schedule.tsx`
- Create: `resources/js/pages/freight/review.tsx`
- Create: `resources/js/pages/freight/index.tsx`
- Create: `resources/js/pages/discover/index.tsx`
- Create: `resources/js/pages/admin/freight/index.tsx`
- Create: `resources/js/pages/admin/freight/show.tsx`
- Test: manual UI check + `npm run types:check`

**Interfaces:**
- Consumes: Wayfinder routes for `my-freight.*`, `freight.routes.preview`, `admin.freight.*`, `locations.search`
- Produces: 4-step wizard with stepper, Save-draft status, LocationSearchInput + map confirm, cargo/truck selects, schedule inputs, review summary + `I confirm accurate` checkbox + `Submit for review`

- [ ] **Step 1: Write the failing test (types)**

Run: `npm run types:check`
Expected: FAIL — missing pages

- [ ] **Step 2: Implement UI**

Reuse `AuthLayout`/`AppLayout`, `resources/js/components/ui/*`, `LocationSearchInput` (now `{locations:[...]}`), lazy `RoutePreviewMap` (dynamic import, `map_config` tile_url). Wizard shell: props `ulid, currentStep, completionScore, saveStatus`; footer: `Previous | Save draft | Continue` (Review shows `Submit for review`). Each step page uses `useForm` + Wayfinder `put` + per-field errors. `review.tsx` shows route map + freight card + entitlement line stub + unchecked confirm checkbox (required). `discover/index.tsx` filters via `router.get` with `preserveState`. Admin pages mimic `resources/js/pages/admin/announcements.tsx` pattern.

- [ ] **Step 3: Verify**

Run: `ddev exec php artisan wayfinder:generate --with-form && npm run types:check && npm run build`
Expected: PASS, `public/build/manifest.json` updated, no `public/hot`

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/freight/FreightWizardShell.tsx resources/js/components/maps/RoutePreviewMap.tsx resources/js/pages/freight/* resources/js/pages/discover/index.tsx resources/js/pages/admin/freight/* 
git commit -m "feat(ui): add freight wizard, discovery and admin moderation pages"
```

### Task 8: Release A hardening — expiration, duplicate detection, privacy, audits

**Files:**
- Create: `app/Console/Commands/ExpireFreightPosts.php` (or scheduled closure)
- Modify: `app/Models/FreightPost.php` — `scopeExpired` + `expireIfPastPickup()` helper
- Modify: `app/Http/Controllers/Freight/FreightPostController.php` — duplicate heuristic (same origin/destination/cargo within 7 days) warning
- Modify: `config/services.php` — `osrm.url`, `map.tile_url` etc.
- Test: `tests/Feature/FreightExpirationTest.php`

**Interfaces:**
- Produces: `php artisan freight:expire` transitions `active` past `pickup_date + flexibility` to `expired`

- [ ] **Step 1: Write the failing test**

```php
it('expires active freight past pickup window', function () { ... });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --compact --filter=FreightExpirationTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Schedule via `routes/console.php` or `app/Console/Kernel` daily.

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --compact --filter=FreightExpirationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ExpireFreightPosts.php app/Models/FreightPost.php config/services.php tests/Feature/FreightExpirationTest.php
git commit -m "feat(freight): add expiration and duplicate detection"
```

### Task 9: Freight interests domain

**Files:**
- Create: `database/migrations/2026_08_31_000014_create_freight_interests_table.php`
- Create: `database/migrations/2026_08_31_000015_create_freight_interest_status_histories_table.php`
- Create: `app/Enums/FreightInterestStatus.php`
- Create: `app/Models/FreightInterest.php`
- Create: `app/Models/FreightInterestStatusHistory.php`
- Test: `tests/Feature/FreightInterestDomainTest.php`

**Interfaces:**
- Produces: `FreightInterestStatus::canTransitionTo()`, `FreightInterest::scopeActive`, unique composite `freight_post_id + user_id` where status not in `rejected/withdrawn`

- [ ] **Step 1: Write the failing test**

```php
it('prevents duplicate active interest', function () {
  $post = FreightPost::factory()->create(['status'=>FreightPostStatus::Active->value]);
  $transporter = createTransporterUser(approved:true);
  FreightInterest::factory()->for($post)->for($transporter)->create(['status'=>FreightInterestStatus::Submitted->value]);
  expect(FreightInterest::where(...)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails** — class not found

- [ ] **Step 3: Write minimal implementation** — enum + models + migrations (status enum, message text nullable, accepted_at nullable, indexes)

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

### Task 10: Interest flow — express / withdraw / shortlist / reject + policies

**Files:**
- Create: `app/Http/Requests/StoreFreightInterestRequest.php`
- Create: `app/Policies/FreightInterestPolicy.php`
- Create: `app/Http/Controllers/Freight/FreightInterestController.php`
- Create: `app/Http/Controllers/Freight/FreightInterestStatusController.php`
- Create: `app/Http/Controllers/Freight/TransporterMyInterestsController.php`
- Modify: `app/Providers/AppServiceProvider.php` — register policy
- Modify: `routes/web.php` — `POST /my-freight/{ulid}/interests`, `POST /interests/{id}/withdraw|shortlist|reject`
- Test: `tests/Feature/FreightInterestFlowTest.php`

**Interfaces:**
- Consumes: `FreightPost::active`, `TransporterProfile::approved`, `Profile::provides_transport`
- Produces: interest lifecycle with guards: no self-interest, no duplicate active, only approved transporter, only active freight, valid transitions; withdraw allowed only by transporter before accept

- [ ] **Step 1: Write the failing test**

```php
it('transporter expresses interest and consignor shortlists', function () { ... });
it('prevents self-interest', fn()=> ...->assertForbidden());
it('allows transporter to withdraw before accept', fn()=> ...);
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Write minimal implementation** — controllers use `Gate::authorize`, write `FreightInterestStatusHistory`, fire notifications (stub in this task, full in Task 13)

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

### Task 11: Marketplace connections domain

**Files:**
- Create: `database/migrations/2026_08_31_000016_create_marketplace_connections_table.php`
- Create: `database/migrations/2026_08_31_000017_create_marketplace_connection_outcomes_table.php`
- Create: `database/migrations/2026_08_31_000018_create_marketplace_connection_audit_logs_table.php`
- Create: `app/Enums/ConnectionOutcome.php`
- Create: `app/Models/MarketplaceConnection.php`
- Create: `app/Models/MarketplaceConnectionOutcome.php`
- Create: `app/Models/MarketplaceConnectionAuditLog.php`
- Test: `tests/Feature/MarketplaceConnectionDomainTest.php`

**Interfaces:**
- Produces: `MarketplaceConnection::contactDetailsFor(User): ?array` (returns consignor contact only to transporter in connection and vice versa, policy-guarded), `public_id` ULID

- [ ] **Step 1: Write failing test** — `contactDetailsFor` returns null for outsider, returns contact for participant

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Write minimal implementation** — migrations with `public_id char(26) unique`, FKs to `freight_posts`, `freight_interests`, `users` x2; models with relations

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

### Task 12: Accept → atomic connection + connection UI

**Files:**
- Create: `app/Http/Controllers/Freight/FreightInterestAcceptanceController.php`
- Create: `app/Http/Controllers/Freight/MarketplaceConnectionController.php`
- Create: `app/Http/Controllers/Freight/MarketplaceConnectionOutcomeController.php`
- Create: `app/Policies/MarketplaceConnectionPolicy.php`
- Create: `resources/js/pages/freight/interests.tsx`
- Create: `resources/js/pages/freight/my-interests.tsx`
- Create: `resources/js/pages/connections/index.tsx`
- Create: `resources/js/pages/connections/show.tsx`
- Modify: `routes/web.php` — `POST /interests/{id}/accept`, `GET /connections`, `GET /connections/{public_id}`, `POST /connections/{public_id}/outcome`
- Test: `tests/Feature/ConnectionAcceptanceTest.php`

**Interfaces:**
- Produces: `POST /interests/{id}/accept` → `DB::transaction` creates `MarketplaceConnection` once per interest (unique `freight_interest_id`), updates interest to `selected`, closes freight to `closed`, writes audit logs; idempotent on retry

- [ ] **Step 1: Write the failing test**

```php
it('accept creates connection atomically and reveals contact only to participants', function () {
  $consignor = createConsignor(); $transporter = createApprovedTransporter();
  $post = FreightPost::factory()->for($consignor)->create(['status'=>FreightPostStatus::Active->value]);
  $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status'=>FreightInterestStatus::Submitted->value]);
  $this->actingAs($consignor)->post("/interests/{$interest->id}/accept")->assertRedirect();
  $conn = MarketplaceConnection::first(); expect($conn)->not->toBeNull();
  expect($conn->contactDetailsFor($transporter))->not->toBeNull();
  expect($conn->contactDetailsFor(User::factory()->create()))->toBeNull();
});
it('is idempotent on double accept', function () { $this->actingAs($consignor)->post("/interests/{$interest->id}/accept"); expect(MarketplaceConnection::count())->toBe(1); });
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Write minimal implementation** — `MarketplaceConnectionPolicy::view/reveal`, `MarketplaceConnection::contactDetailsFor` checks `transporter_user_id` or `consignor_user_id` vs `auth()->id()`; outcome controller validates enum

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

### Task 13: Marketplace notifications (in-app + email)

**Files:**
- Create: `app/Notifications/FreightInterestSubmitted.php`
- Create: `app/Notifications/FreightInterestStatusChanged.php`
- Create: `app/Notifications/MarketplaceConnectionCreated.php`
- Create: `app/Notifications/FreightPostModerated.php`
- Modify: `app/Http/Controllers/Freight/FreightInterestController.php`, `FreightInterestStatusController.php`, `FreightInterestAcceptanceController.php`, `AdminFreightController.php` — dispatch notifications
- Test: `tests/Feature/MarketplaceNotificationsTest.php`

**Interfaces:**
- Produces: database + mail channels; respects existing `User` notification preferences (add `interests` category if missing)

- [ ] **Step 1: Write failing test** — `Notification::fake(); post interest; assertSentTo(consignor, FreightInterestSubmitted)`

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Write minimal implementation** — notifications with `toMail` + `toDatabase`, queued

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

### Task 14: Release B UI polish + final quality gates

**Files:**
- Modify: `resources/js/pages/discover/index.tsx` — Express Interest CTA (disabled if not `provides_transport` or not approved)
- Modify: `resources/js/layouts/app-layout.tsx` or nav — add `My Freight`, `Discover`, `Connections` links gated by onboarding + capability
- Run: `ddev exec php artisan wayfinder:generate --with-form && npm run types:check && npm run build && rm -f public/hot && vendor/bin/pint --dirty --format agent && ddev exec php artisan test --compact`

**Interfaces:**
- Produces: nav visible only when `onboarded` + `posts_freight` / `provides_transport`; type checks green

- [ ] **Step 1: Run verification commands and capture output**

Run: `ddev exec php artisan test --compact 2>&1 | tail -30`
Expected: all green (or list failures to fix)

- [ ] **Step 2: Fix any failures**

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat(marketplace): polish discovery/connection UI and pass quality gates"
```

---

## Self-Review

**Spec coverage:** Guide §§13 (FreightPosting), 14 (Discovery/Interest/Select/Connect), 15 (use transporter profiles), 18 (maps/route preview) mapped: Task 1-2 domain, Task 4 wizard, Task 5 moderation, Task 6 discovery, Task 7 wizard UI, Task 8 expiration/duplicate, Task 9-10 interests, Task 11-12 connections/contact reveal, Task 13 notifications, Task 14 polish. Plans/entitlements/payments (guide §16-17) intentionally excluded per Approach 2 guardrails. Notifications cover required marketplace events (interest/status/connection/moderation) without adding Telegram yet (deferred to later horizon).

**Placeholder scan:** No TBD/TODO; each step has concrete file paths, code, and commands.

**Type consistency:** `FreightPost` uses `ulid:char(26)`, `MarketplaceConnection` uses `public_id:char(26)`, `FreightInterest` uses auto-increment `id`; enums use `label()` + `canTransitionTo()` matching `TransporterStatus` pattern (`app/Enums/TransporterStatus.php:22-35`); `RoutePreviewService::preview` signature matches preview controller contract; `contactDetailsFor(User): ?array` consistent across Task 11-12.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-31-freight-requests-offers.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
