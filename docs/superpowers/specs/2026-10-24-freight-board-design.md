# Freight Board: reveal contact from the listing

**Date:** 2026-10-24
**Status:** Draft for approval after builder review

## Intent

Numan Freight is a freight board. A consignor posts a load. A transporter browses, opens the listing, and spends a metered contact reveal to see the consignor's phone, WhatsApp, and email, then calls or messages them directly. That is the whole loop.

The interest machinery — Express Interest, My Interests, shortlist, reject, accept, withdraw, interest statuses — is the wrong loop for this model. It is hidden from every board surface, not deleted. One config flag brings it back. The reveal ledger, quota, and phone-required gate stay; the relationship gate becomes listing-aware.

## Why

The current reveal can only happen through an interest. The frontend reveal button has no URL without an interest id, and the backend gate (`ContactRevealService::canReveal`) returns true only when a live interest or connection exists. Hiding the interest section alone leaves the reveal unreachable. The reveal has to be attachable to the listing itself.

The builder review found three gaps that would otherwise ship broken:

1. **WhatsApp is not a consignor field.** `contactDetailsFor()` returns `whatsapp` only from `transporter_profiles`. `public_profiles` has a `whatsapp_visible` switch but no `whatsapp` value, and the Marketplace details form shows "Share my WhatsApp number" with no number to share. A consignor without a transporter profile has no WhatsApp at all.
2. **The consent copy becomes false.** Marketplace details promises that contact is "only released to someone you have a live conversation with." A listing reveal has no conversation. The reveal dialog and the phone-required step also promise that the consignor receives the carrier's phone, but the listing reveal is one-way: the consignor has no reveal inbox in this pass.
3. **The flag has to reach the frontend.** A backend-only flag 404s the routes but leaves the links and CTAs in place. The flag must be shared with Inertia and gate the UI too, or "one flag restores the loop" is not true.

## Goals

- A signed-in transporter opens a listing and reveals the consignor's contact directly. No interest is created.
- The revealed contact shows phone, WhatsApp, and email, each with its own action: call (`tel:`), WhatsApp (`wa.me`), email (`mailto:`).
- The reveal stays metered: free plan 3 per rolling 7 days, Standard unlimited, re-revealing the same person is free, a missing phone on the viewer blocks it, quota exhaustion shows the plan link.
- Posting stays free up to the existing 5 live posts; the contact reveal is the paywall. This protects supply while charging for demand.
- Every board trace of interests disappears: nav links, footer links, discovery cards, the detail page, the app sidebar, the My Freight owner link and count, the owner-facing "interested transporters" count, and the copy that tells people to use interests.
- A guest on a listing sees a sign-in call to action instead of an invisible contact block.
- The copy that describes contact release tells the truth about the new gate.
- The change is reversible: one config flag restores the interest routes and interest UI.

## Non-goals

- Deleting interest code, routes, models, tables, or notifications. They stay, gated off.
- Building the deeper contact-reveal work: per-listing metering, reveal history for the viewer, refunds, or changing the quota numbers.
- A consignor-side inbox of who revealed them. The listing reveal is one-way in this pass: the carrier sees the consignor's contact; the consignor does not receive the carrier's phone.
- Changing plan prices or the free-plan post limit.
- Redesigning the discovery page or the load card beyond removing interest UI.
- Admin screens, analytics, and the connections pages.
- Overwriting plan copy an admin has already customised.

## Anti-goals

- A reveal that works without signing in. Contact data must never reach an anonymous page.
- A reveal that consumes quota on a page load. Only the explicit action records a reveal.
- A second copy of the quota logic. The existing `ContactRevealService::reveal()` is the only thing that meters.
- Widening the interest and connection reveal endpoints. The new gate applies only when the context is a freight post.

## Constraints

- Laravel 12, Inertia v2, React 19, Tailwind v4, Pest 4. Existing conventions, including the Spatie guidelines and the project's comment style.
- The reveal ledger is keyed on the pair (`revealer_id`, `revealed_id`), so a repeat reveal of the same consignor is already free. That behaviour is preserved.
- `public_profiles` holds `phone_visible`, `whatsapp_visible`, and `email_visible`, all defaulting to true. It does not hold a WhatsApp value yet.
- The public detail page has no app layout, so a missing phone must stay an inline step with a link to the field that fixes it.
- The login and register pages already accept a `redirect` query string and pass it through the auth form, so the guest call to action can return to the listing.
- The existing interest test suite must keep testing the interest loop. The flag defaults to false in production and true in `phpunit.xml`; the new flag-off tests override it.

## How it behaves

### The reveal

- New endpoint: `POST /freight/{ulid}/reveal`, named `freight.reveal`, behind auth. The counterparty is the listing's owner.
- The route authorises `view` on the post and mirrors the detail page's hidden-post rule: a hidden post 404s for everyone except its owner and a superadmin.
- `ContactRevealService::canReveal()` gains an optional context and a third path that applies only when the context is a `FreightPost` owned by the counterparty. The interest and connection paths stay exactly as they are. Existing endpoints keep the old gate, so `/interests/{interest}/reveal` does not become a stranger-accessible endpoint.
- `contactDetailsFor()` and `reveal()` pass the context through to `canReveal()`.
- The ledger row records `freight_post_id` from the listing and leaves `interest_id` null. `contextIds()` learns to read a `FreightPost`.
- The response shape is unchanged: redirect back with `revealed_contact`, or `reveal_blocked`, or `requires_phone`. The detail page already reads all three.
- The detail controller stops looking up the viewer's interest for reveal purposes. `canReveal` becomes "signed in and not the owner"; `revealedContacts` comes from `contactIfAlreadyRevealed()` with the post as context.
- The owner revealing their own listing remains a no-op in the UI and is not a metering event in the service.

### The listing page

- The Consignor Poster card is the action. Its button posts to `/freight/{ulid}/reveal` and no longer needs an interest id.
- A signed-in non-owner sees the reveal button, the phone step, or the quota message.
- A guest sees "Sign in to view contact", linking to `/login?redirect=<current listing path>`. No contact data reaches the page.
- The owner sees their own actions, never a reveal of their own contact.
- After a reveal, the contact block shows each present channel: phone as a call link, WhatsApp as a `wa.me` link, email as a mailto link. Absent channels are omitted, not shown empty. If none are present, the block says the consignor has not shared a contact method.
- The Express Interest button, the interest status text, the withdraw button, the re-express button, and both dialogs are hidden when the flag is off.
- The confirm dialog says the reveal uses one of the viewer's contact reveals and will show the consignor's channels. It does not promise that the consignor receives the viewer's phone.
- The phone-required step says a phone is needed before revealing someone else's contact. It does not promise the consignor will receive it.
- The success toast does not fire when the redirect carried `reveal_blocked` or `requires_phone`; the inline state is the feedback.

### The WhatsApp channel

- Add a nullable `whatsapp` column to `public_profiles`, next to `phone`.
- Add an optional WhatsApp number input to Marketplace details, validated like the phone field.
- Backfill the column from `transporter_profiles.whatsapp` for users who already have one.
- `contactDetailsFor()` prefers the profile WhatsApp, falls back to the transporter profile's WhatsApp, and honours both visibility flags. The transporter profile form keeps writing its own field; the service's fallback covers it.
- The `wa.me` link uses digits only. Local Ethiopian forms (`0…` and bare `9…`/`7…` mobile numbers) normalise to `251…`; international numbers are left as digits. This is a display helper, not a change to stored data.

### Hiding the interest loop

One flag, `config('marketplace.interests_enabled')`, defaulting to `false` and overridable by `MARKETPLACE_INTERESTS_ENABLED`.

When false:

- A small `EnsureInterestsEnabled` middleware on the interest route group returns 404. It covers `/my-interests`, the owner interest list and express endpoint, withdraw/shortlist/reject/accept, and the interest-based reveal. The controllers are untouched.
- `HandleInertiaRequests` shares `marketplace.interests_enabled` so every page can gate its UI.
- The freight chrome drops "My Interests" from the header and footer.
- The app sidebar drops the My Interests item.
- The discovery cards drop the Express Interest button, the interest status badge, and the withdraw control. The card still links to the listing.
- The detail page hides the interest status, the Express Interest/Withdraw/Re-express actions, and both dialogs. The listing reveal remains.
- The My Freight row drops the "Interests (count)" menu item. The delete dialog no longer talks about interests.
- The owner's "N interested transporters" block on the poster card is not rendered.
- Marketplace details and the transporter profile page describe the board loop, not the interest loop.
- The free-plan pricing bullets drop the interests line, and the Standard plan copy drops "interests" where it is still the seeded default. Plan limits and prices are untouched.
- The `Billing` upsell card says "Unlimited freight posts and contact reveals", not interests.

When true, the interest routes and interest UI behave as they do today. The listing reveal remains available as the primary action. Pricing copy stays board-focused; the flag restores the feature, not the marketing.

### The business model

- Free: 5 live posts, 3 contact reveals per rolling 7 days.
- Standard: unlimited posts and reveals; 149 ETB/week, 449 ETB/month, 3,999 ETB/year.
- Posting stays free up to 5 because an empty board has nothing to reveal. The contact reveal is the paid action.
- Posting is still gated by the existing `posts_freight` capability and onboarding checks; this pass does not add a new posting payment.
- The Standard plan's limits still include interests for flag-on installs. Only its copy is board-focused.

### What is deliberately left alone

- Plan limits and prices.
- The phone-required gate, the quota-exhausted state, and the confirm dialog before spending a reveal.
- Admin screens and analytics.
- The connections pages. They are reachable for legacy connections; this pass does not add a new way to create one.
- Interest notifications already in the database. No new ones are generated while the flag is off. A stale notification link may 404; that is acceptable for this pass.

## Decisions made here

- **Posting stays free, reveals are the paywall.** Confirmed in conversation.
- **Hide, don't delete.** One config flag restores the routes and the UI.
- **Any signed-in user may reveal a listing, not only transporter profiles.** The phone-required gate still applies.
- **The new gate is context-scoped.** Only a post-context reveal bypasses the relationship check. This keeps the existing interest and connection endpoints honest and keeps the stranger-cannot-reveal tests meaningful.
- **WhatsApp becomes a profile field.** The existing visibility toggle already points at it; the data was missing.
- **The listing reveal is one-way.** The consignor does not get the carrier's phone in this pass, so the copy must not say they do.
- **The guest call to action is a sign-in link, not a public reveal.**
- **Plan copy is updated only where it is still the seeded default.** Admin customisations are preserved.

## Left to the builder

- The exact wording of the reveal button, confirm dialog, phone step, guest CTA, and empty state. Keep it short and name the cost.
- The WhatsApp display helper's exact normalisation for local numbers, and whether it lives in the card or a shared formatter.
- The plan-copy data migration's guard: update only the exact seeded description/features, leave anything an admin changed, and make the rollback symmetrical.
- Whether the discovery card shows a small "Contact available" cue or stays silent. Silent is the safer default.
- Test layout: extend `PublicFreightDetailTest` for the new reveal and the changed `canReveal`; add a listing-reveal test for the ledger, quota, phone step, hidden/non-active posts, and WhatsApp visibility; add a flag-off route/UI test; keep the existing interest suite running with the flag on.
