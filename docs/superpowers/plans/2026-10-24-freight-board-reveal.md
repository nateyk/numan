# Freight Board Reveal — Implementation Plan

**Spec:** `docs/superpowers/specs/2026-10-24-freight-board-design.md`
**Date:** 2026-10-24
**Goal:** A transporter reveals the consignor's phone, WhatsApp and email directly from a freight listing. Interests are hidden behind a config flag without deleting them.

**Verification baseline**

```bash
vendor/bin/pint --dirty --format agent
npm run types
npx eslint <changed ts/tsx files>
npm run build
php artisan test --compact <changed test paths>
```

Working directory: `/home/nate/projects/numan`.

---

## Task 1 — Add the interests flag, middleware, and shared Inertia prop

**Files**

- `config/marketplace.php`
- `app/Http/Middleware/EnsureInterestsEnabled.php` (new)
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/types/index.d.ts`
- `.env.example`
- `phpunit.xml`

**Steps**

1. Add to `config/marketplace.php`, before `free_limits`:

```php
'interests_enabled' => env('MARKETPLACE_INTERESTS_ENABLED', false),
```

Add a short comment: interests are hidden by default because the board's action is contact reveal; setting the env variable restores the routes and UI.

2. Create `EnsureInterestsEnabled`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInterestsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('marketplace.interests_enabled'), 404);

        return $next($request);
    }
}
```

3. In `HandleInertiaRequests::share()`, add beside `flash`:

```php
'marketplace' => [
    'interests_enabled' => (bool) config('marketplace.interests_enabled'),
],
```

4. In `resources/js/types/index.d.ts`, add to `SharedData`:

```ts
marketplace: {
    interests_enabled: boolean;
};
```

5. Add `MARKETPLACE_INTERESTS_ENABLED=false` to `.env.example` near the marketplace settings, and `<env name="MARKETPLACE_INTERESTS_ENABLED" value="true"/>` to `phpunit.xml` so the existing interest suite keeps running.

**Tests:** covered by Task 6.

**Commit:** `feat(marketplace): add an interests feature flag`

---

## Task 2 — Make the reveal gate listing-aware

**Files**

- `app/Services/ContactRevealService.php`
- `tests/Feature/Marketplace/ListingContactRevealTest.php` (new, backend portion)

**Steps**

1. Import `App\Models\FreightPost`.

2. Change `canReveal()` to accept an optional context:

```php
public function canReveal(User $viewer, User $counterparty, ?Model $context = null): bool
{
    if ($viewer->id === $counterparty->id) {
        return true;
    }

    if ($context instanceof FreightPost && (int) $context->user_id === (int) $counterparty->id) {
        return true;
    }

    return $this->hasConnection($viewer->id, $counterparty->id)
        || $this->hasLiveInterest($viewer->id, $counterparty->id);
}
```

The context branch must require that the post belongs to the counterparty. A post context must never open contact for a third party.

3. Pass context in `reveal()` and `contactDetailsFor()`:

```php
if (! $this->canReveal($viewer, $counterparty, $context)) {
```

4. Teach `contextIds()` about `FreightPost`:

```php
} elseif ($context instanceof FreightPost) {
    $ids['freight_post_id'] = (int) $context->id;
}
```

5. Update the class docblock: the gate is an existing relationship **or a listing owned by the counterparty**.

6. Add tests in `tests/Feature/Marketplace/ListingContactRevealTest.php`:

- a signed-in stranger with a phone revealing an active listing gets `allowed`, contact data, and a `contact_reveals` row whose `freight_post_id` is the listing id and `interest_id` is null;
- no `freight_interests` row is created;
- the same pair reveals again as `already` with one ledger row;
- a `FreightPost` context passes for its owner's counterparty but a post belonging to someone else does not bypass the relationship gate (assert a third user still gets 403 from `reveal()`);
- profile WhatsApp is preferred over transporter WhatsApp, and transporter WhatsApp is the fallback.

Use the existing fixtures in `tests/Feature/Marketplace/ContactRevealMeteringTest.php` as the pattern (`meterUser()`, `MarketplacePlanSeeder`).

**Commit:** `feat(marketplace): allow a reveal from a freight listing`

---

## Task 3 — Add the listing reveal endpoint

**Files**

- `app/Http/Controllers/Freight/MarketplaceConnectionController.php`
- `routes/web.php`
- `tests/Feature/Marketplace/ListingContactRevealTest.php`

**Steps**

1. Add `use App\Models\FreightPost;` to the controller.

2. Add:

```php
public function revealFromPost(Request $request, string $ulid): RedirectResponse|JsonResponse
{
    $post = FreightPost::with('user.profile')->where('ulid', $ulid)->firstOrFail();

    $viewer = $request->user();

    if ($post->isHidden() && ! ($viewer?->is_superadmin || $viewer?->id === $post->user_id)) {
        abort(404);
    }

    Gate::authorize('view', $post);

    $counterparty = $post->user;

    if (! $counterparty) {
        abort(403);
    }

    if ($viewer->id === $counterparty->id) {
        abort(403);
    }

    if ($missing = $this->missingPhoneStep($request)) {
        return $missing;
    }

    return $this->performReveal($request, $viewer, $counterparty, $post);
}
```

3. In `routes/web.php`, inside the authenticated group beside the freight routes:

```php
Route::post('/freight/{ulid}/reveal', [MarketplaceConnectionController::class, 'revealFromPost'])->name('freight.reveal');
```

Keep it outside the interests middleware group.

4. Add endpoint tests:

- guest is redirected to login;
- signed-in stranger gets the contact and the ledger row;
- hidden listing 404s for a stranger; owner is not 404 (403, because an owner cannot reveal themselves);
- non-active listing returns 403 for a stranger;
- viewer without a phone gets `requires_phone`, and no ledger row is written;
- quota exhaustion returns 402 JSON / redirects with `reveal_blocked`, no contact is present.

**Commit:** `feat(marketplace): reveal contact from a listing endpoint`

---

## Task 4 — Decouple the detail page from interests

**Files**

- `app/Http/Controllers/Freight/PublicFreightDiscoveryController.php`
- `tests/Feature/PublicFreightDetailTest.php`

**Steps**

1. Only look up `hasInterest`, `interestStatus`, `interestId` and `interestsCount` when `config('marketplace.interests_enabled')` is true. Default all four to false/null/0.

2. For a signed-in non-owner, compute:

```php
$canReveal = $this->reveals->canReveal($user, $post->user, $post);
$revealedContacts = $canReveal
    ? $this->reveals->contactIfAlreadyRevealed($user, $post->user, $post)
    : null;
```

3. `interestsCount` stays null for non-owners and for flag-off.

4. Update `PublicFreightDetailTest`:

- replace `does not offer a reveal to someone with no interest` with a test that a signed-in stranger now gets `canReveal = true`, `interestId = null`, `revealedContacts = null`, and still cannot see the phone in the payload;
- keep the existing interest-id test for flag-on;
- add a test that `revealedContacts` is served from a post-context ledger row even without an interest;
- add a flag-off test asserting `hasInterest`, `interestStatus`, `interestId`, and `interestsCount` stay empty while `canReveal` is true for a signed-in non-owner.

**Commit:** `refactor(freight): stop requiring an interest to reveal a contact`

---

## Task 5 — Add the profile WhatsApp field and backfill

**Files**

- `database/migrations/2026_10_24_000001_add_whatsapp_to_public_profiles_table.php` (new)
- `app/Models/Profile.php`
- `app/Http/Requests/Settings/MarketplaceDetailsRequest.php`
- `app/Http/Controllers/Settings/MarketplaceDetailsController.php`
- `resources/js/pages/settings/marketplace-details.tsx`
- `app/Services/ContactRevealService.php`
- `tests/Feature/Settings/MarketplaceDetailsTest.php`
- `tests/Feature/Marketplace/ListingContactRevealTest.php`

**Steps**

1. Migration: add `whatsapp` nullable after `phone`. Backfill from `transporter_profiles.whatsapp` by user id using `DB::table(...)->chunkById(100, ...)`; guard with `Schema::hasTable('transporter_profiles')`. Down drops the column.

2. `Profile::$fillable` gains `whatsapp`.

3. `MarketplaceDetailsRequest`: add

```php
'whatsapp' => [
    'nullable',
    'string',
    'max:32',
    'regex:/^\+?[0-9\s\-()]{6,32}$/',
],
```

plus `'whatsapp.regex' => 'That does not look like a WhatsApp number.'`.

4. `MarketplaceDetailsController::edit()` sends `'whatsapp' => $profile->whatsapp`; `update()` writes `'whatsapp' => $data['whatsapp'] ?? null`.

5. In `resources/js/pages/settings/marketplace-details.tsx`:

- add `whatsapp: string | null` to `Details` and `whatsapp: profile.whatsapp ?? ''` to the form;
- add an optional WhatsApp input directly after phone with `inputMode="tel"` and copy such as “Optional — used for the WhatsApp button when a transporter reveals your contact.”;
- fix the visibility card copy: contact is released to a signed-in person who chooses to reveal it, every release is recorded, and the switches decide which channels can go out. Do not promise a live conversation;
- keep the existing checkbox keys.

6. In `ContactRevealService::contactDetailsFor()`:

```php
$whatsapp = $whatsappRefused
    ? null
    : (filled($profile?->whatsapp) ? $profile->whatsapp : $transporter?->whatsapp);
```

7. Tests:

- marketplace details saves and returns WhatsApp; invalid values are rejected;
- a consignor profile WhatsApp is revealed;
- `whatsapp_visible = false` returns null while phone and email still release;
- profile WhatsApp wins over transporter WhatsApp.

**Commit:** `feat(profiles): add a WhatsApp number for consignors`

---

## Task 6 — Hide the interest routes behind the flag

**Files**

- `routes/web.php`
- `tests/Feature/Marketplace/InterestsDisabledTest.php` (new)

**Steps**

1. Wrap the interest routes (`freight.interests.*` and `freight.my-interests`) in:

```php
Route::middleware(EnsureInterestsEnabled::class)->group(function (): void {
    // interest routes, including the interest reveal
});
```

Move `freight.interests.reveal` into this group. Import the middleware.

2. Tests with `config(['marketplace.interests_enabled' => false])` and an onboarded user:

- `GET /my-interests` is 404;
- `POST /my-freight/{ulid}/interests` is 404;
- `POST /interests/{interest}/reveal` is 404;
- `GET /freight` still works and shares `marketplace.interests_enabled = false`;
- with the config true, `/my-interests` is not 404.

**Commit:** `feat(marketplace): gate interest routes behind the flag`

---

## Task 7 — Rebuild the poster card around the listing reveal

**Files**

- `resources/js/components/freight/consignor-poster-card.tsx`
- `resources/js/pages/freight/show.tsx`

**Steps**

1. `RevealedContacts` gains `whatsapp?: string | null`.

2. Remove the `interestId` prop from `ContactBlock` and the card. The card no longer builds an interest URL.

3. Add a local helper:

```ts
function whatsappHref(raw: string): string {
    let digits = raw.replace(/\D/g, '');
    if (digits.startsWith('0')) digits = `251${digits.slice(1)}`;
    else if (digits.length === 9 && /^[79]/.test(digits)) digits = `251${digits}`;
    return `https://wa.me/${digits}`;
}
```

4. Revealed block:

- phone: `tel:` link;
- WhatsApp: `wa.me` link, only when a number is present;
- email: `mailto:` link, only when present;
- no channels: “This consignor has not shared a contact method yet.”;
- omit absent channels entirely.

5. Guest state: when `!canReveal && !isOwnPost && !revealedContacts`, render “Sign in to view contact” linking to ``/login?redirect=${encodeURIComponent(url.split('?')[0])}`` using `usePage().url`. No contact data is present.

6. Resolve the quota message without `interestId`: show it when `revealBlocked && !revealedContacts`.

7. Confirm dialog copy: the reveal costs one contact reveal and shows the consignor's phone, WhatsApp and email. Remove “shares your phone number with them”. The inline note below the button says the same.

8. Phone step copy: a phone is required before revealing someone else's contact. Remove “A consignor who calls you back…”.

9. Owner block: accept `interestsEnabled` and render the “N interested transporters” row only when `isOwnPost && interestsEnabled`.

10. In `show.tsx`:

- remove `interestId` from props/destructuring and the card call;
- `handleReveal()` posts to `/freight/${post.ulid}/reveal`;
- read `marketplace` from `usePage<SharedData>().props`;
- gate the entire interest action block and both dialogs on `interestsEnabled`, while keeping “Manage Your Post” and “Browse More Freight”;
- don't render the “not available for interest” badge when the flag is off.

**Commit:** `feat(freight): make reveal the listing's primary action`

---

## Task 8 — Hide interest links on the board surfaces

**Files**

- `resources/js/components/freight/discovery-chrome.tsx`
- `resources/js/components/app-sidebar.tsx`
- `resources/js/pages/discover/index.tsx`
- `resources/js/pages/freight/index.tsx`

**Steps**

1. `DiscoveryHeader`: filter the My Interests nav link when `marketplace.interests_enabled` is false. `DiscoveryFooter`: hide the My interests link under the same flag.

2. `AppSidebar`: pass `interestsEnabled` into `getMainNavItems()` (and its `useMemo` deps) and omit the My Interests item when false.

3. `discover/index.tsx`:

- read the flag beside `auth`;
- only compute/render the interest status badge for the flag-on case; keep “Your Post” and “Active” badges working flag-off;
- when flag-off, replace the interest/express column with nothing (the View Details button remains full width);
- gate the confirm dialog and `confirmExpressInterest` on the flag.

4. `freight/index.tsx`:

- read the flag;
- hide the `Interests (count)` dropdown item when false;
- change the delete dialog copy to “This post will be permanently removed. This cannot be undone.” (no interests mention).

**Commit:** `feat(ui): remove interest links from the board when disabled`

---

## Task 9 — Align pricing and settings copy with the board

**Files**

- `resources/js/components/landing/landing-pricing.tsx`
- `resources/js/pages/Billing/plans.tsx`
- `resources/js/pages/Billing/index.tsx`
- `resources/js/pages/transporters/show.tsx`
- `config/marketplace.php`
- `database/migrations/2026_10_24_000002_remove_interests_from_standard_plan_copy.php` (new)

**Steps**

1. Drop the `free_interests_per_week` bullet from the free-plan lists on the landing page and `/billing/plans`. Keep the post and contact-reveal bullets.

2. Billing upsell: “Unlimited freight posts and contact reveals, paid in ETB.”

3. Transporter public page: replace the “Express interest… interest list” copy with “Contact details are never published on this page. Open a freight listing and reveal the consignor’s contact when you are ready to call or message them.”

4. Standard plan config:

```php
'description' => 'Full access — unlimited posts and contact reveals',
'features' => [
    'Unlimited active freight posts',
    'Unlimited contact reveals',
],
```

Leave limits and prices untouched.

5. Data migration updates existing `marketplace_plans` only when the description/features are exactly the old seeded values. Down reverses only when they are exactly the new values. Do not touch admin-customised rows.

**Commit:** `copy(marketplace): describe the freight board, not interests`

---

## Task 10 — Full verification

Run, in order:

```bash
vendor/bin/pint --dirty --format agent
npm run types
npx eslint resources/js/components/freight/consignor-poster-card.tsx \
  resources/js/components/freight/discovery-chrome.tsx \
  resources/js/components/app-sidebar.tsx \
  resources/js/pages/freight/show.tsx \
  resources/js/pages/freight/index.tsx \
  resources/js/pages/discover/index.tsx \
  resources/js/pages/settings/marketplace-details.tsx \
  resources/js/pages/transporters/show.tsx \
  resources/js/pages/Billing/plans.tsx \
  resources/js/pages/Billing/index.tsx \
  resources/js/components/landing/landing-pricing.tsx
npm run build
php artisan test --compact tests/Feature/Marketplace/ListingContactRevealTest.php \
  tests/Feature/PublicFreightDetailTest.php \
  tests/Feature/Marketplace/InterestsDisabledTest.php \
  tests/Feature/Settings/MarketplaceDetailsTest.php \
  tests/Feature/ContactVisibilityOptOutTest.php \
  tests/Feature/Marketplace/ContactRevealMeteringTest.php
```

Then run the full suite if the above is green. Confirm the new route:

```bash
php artisan route:list --name=freight.reveal
```

Smoke-check the detail page and a reveal with the demo posts after `npm run build`.
