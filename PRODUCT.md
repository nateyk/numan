# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Primary: shippers / consignors.** Businesses that need goods moved and must post a load, compare carriers, choose one, and get the shipment delivered. Many will use the product on a phone.

**Secondary: transporters / carriers.** Truck owners and fleet operators who need to find loads that fit their vehicles and lanes. Carrier accounts are expected to be verified before they can operate.

Numan Freight optimizes product decisions for the shipper first.

## Product Purpose

Numan Freight connects shippers with verified transporters so a load can move from posting to a confirmed booking. The product is not hard-limited to one geography, but the operating context includes Djibouti by default.

Success means a shipper can post a real load, select a carrier, and complete the booking loop with confidence. Growing carrier liquidity and recurring revenue matter, but neither replaces that first working loop.

## Positioning

Positioning is not finalized. The intended advantages are:

- a verified carrier network;
- matching loads to carriers' declared lanes instead of a passive listing board;
- Ethiopian payment and pricing fit through ETB and Chapa;
- one workspace for a shipper's loads, team, carrier relationships, and billing.

These are product intentions, not proven market claims. Do not present them as established facts until real evidence exists.

## Operating Context

- Web product, used mobile-first. Phone layouts are a requirement, not a secondary viewport.
- Ethiopian freight context, with ETB pricing and Chapa as the real payment path.
- Djibouti is included by default; the product is not restricted to Ethiopia-only domestic lanes.
- A load includes a route, cargo details, schedule, and commercial terms such as a tariff.
- Transporters maintain a separate profile covering vehicles, lanes, and verification documents.
- Shippers work inside a workspace with team roles; workspace membership is the tenancy model.
- Admin moderation and transporter approval are part of the trust model.

## Capabilities and Constraints

The existing application is Laravel 12 with Inertia v2, React 19, Tailwind CSS v4, and Pest 4. It already includes freight posting, carrier interests, public load discovery, transporter profiles, admin moderation, workspaces, and marketplace plans.

Confirmed constraints:

- Preserve the Numan Freight name and identity.
- Preserve verified transporters as a trust requirement.
- Preserve the workspace/team model.
- Preserve Chapa payments in ETB.
- Design for mobile-first use.

Current product limitations that future work must not mistake for finished behavior:

- Only a few freight screens have dedicated mobile layouts; most still rely on wide tables or horizontal scrolling.
- The landing page and several product surfaces still contain SaaS-template copy or placeholder content.
- The shipper-to-carrier loop is not yet proven end to end.
- Commission, escrow, and carrier payouts are undecided; do not imply they exist.

Open product decisions:

- The final revenue model beyond subscriptions.
- The concrete matching and notification mechanism.
- The level of human moderation versus automated publication.
- The evidence and success metrics that will define traction.

## Brand Commitments

- The product name **Numan Freight** is fixed.
- The existing visual identity may evolve; no other brand voice, palette, logo system, or personality guideline has been confirmed.
- Do not invent customer names, logos, testimonials, load volumes, carrier counts, or performance claims.

## Evidence on Hand

No external evidence has been confirmed for design use. The repository contains landing-page statistics and testimonial-style content that are not verified; treat them as placeholders and do not repeat them as real proof.

Until real customer stories, logos, shipment examples, or operational metrics are supplied, design should rely on the working product UI and clearly framed product facts.

## Product Principles

1. **Book the load.** Every shipper-facing decision should reduce the distance between posting a load and a confirmed carrier.
2. **Trust is operational.** Verification, clear status, and honest empty states matter more than promotional claims.
3. **Local by default.** ETB, Chapa, Djibouti, and phone-first use are product conditions, not edge cases.
4. **One shipper workspace.** Loads, team, carrier relationships, and billing should feel like one coherent place to work.
5. **Finish the freight loop before expanding the template.** Non-freight SaaS surfaces must not outrank the core shipping job.
