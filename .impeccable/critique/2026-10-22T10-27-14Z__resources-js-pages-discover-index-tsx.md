---
target: /freight discovery page
total_score: 20
max_score: 40
na_heuristics: 
p0_count: 2
p1_count: 2
target_identity: "file:/home/nate/projects/numan/resources/js/pages/discover/index.tsx"
target_fingerprint: "sha256:4de47eab16761d0165168aef7665d43777ecc7805f0a21f6fc16669098818a6e"
target_path: /home/nate/projects/numan/resources/js/pages/discover/index.tsx
timestamp: 2026-10-22T10-27-14Z
slug: resources-js-pages-discover-index-tsx
---
# Critique: /freight discovery page (resources/js/pages/discover/index.tsx)

Method: dual-agent (A: ses_e9ce39b13ffeCPDezwTWYGi2Ji · B: ses_e9ce39b10ffe7EER3VuaiZqPim)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | "Sorted by Distance" reprints before any query runs; Apply has no pending state |
| 2 | Match System / Real World | 2 | "Find verified loads" (loads are not verified, carriers are); "Across Ethiopia" in a product shipping Djibouti by default; raw ISO dates |
| 3 | User Control and Freedom | 2 | clearAll() drops the sort; sort/cargo discarded if not Applied; no confirm on withdraw |
| 4 | Consistency and Standards | 2 | Two amber implementations side by side; filters add only on Apply but remove instantly; Express Interest is a dialog on show, bare POST here |
| 5 | Error Prevention | 1 | isOwn reads f.user, resource emits owner, so owners see Express Interest on their own load; CTA has no processing guard |
| 6 | Recognition Rather Than Recall | 3 | Chips and echoed sort good; distance and verification not recognized, require opening every load |
| 7 | Flexibility and Efficiency | 2 | Controller supports truck_type/pickup_from/pickup_to/cargo_type; none exposed; deep links filter with no chip shown |
| 8 | Aesthetic and Minimalist Design | 2 | Six stacked bands before the first card; two nested bordered boxes per card; badge duplicates footer button state |
| 9 | Error Recovery | 2 | Unknown-location alert excellent; generic empty state offers the shipper's CTA and no Clear filters |
| 10 | Help and Documentation | 2 | Nothing explains what Express Interest commits to, how reveal works, or what Active means |
| Total | | 20/40 | Acceptable |

All ten heuristics applied (Operate surface, no n/a).

## Design Specificity Verdict

LLM assessment: Partially anchored — specific in the middle, category-interchangeable at the edges. The card interior (origin-destination chip, ETB tariff, truck_type x count, Express Interest verb) is unmistakably this product. The frame is not: LandingHeader renders a Sparkles tile reading "Laravel SAAS Starter" with four dead anchor navs (#features, #pricing, #testimonials, #faq) on /freight, and LandingFooter renders the XCO Agency starter-kit pitch. Worse than interchangeable: owner.display_name, owner.is_verified, route_snapshot.distance_meters/duration_seconds ship on every card and none are rendered — verified counterparties and lane matching, the product's stated pillars, are in the payload and out of the pixels.

Deterministic scan: detect --json resources/js/pages/discover/index.tsx returned exit 0, findings [] (clean). No false positives. The detector caught nothing the LLM review found; the findings are contract mismatches a pattern detector does not target. Visual overlays: none available (no browser automation this session); CLI scan only.

Parent verification: A's load-bearing claims re-checked against source and all true — controller line 68 maps distance_asc/distance_desc to the same arm as default sort; resource emits owner plus distance/duration/geometry while the page declares f.user; isOwn compares f.user?.id (always undefined); line 1 imports LandingHeader, LandingFooter; CTA fires router.post with message: '' and no processing state.

## Overall Impression

The card is a real freight marketplace card wrapped in someone else's landing page. The single biggest opportunity: the data that would make this page specific and trustworthy (kilometres, verified shipper, honest sorting) is already sent by the server and simply not drawn. This is less about styling than honoring contracts that already exist.

## What's Working

1. The active-filter chip row — hidden query state becomes individually reversible objects with real aria-labels. Correct pattern, properly executed.
2. The unknown-location amber alert — names the OSM failure in plain language, distinguishes one-sided vs two-sided failure, offers Clear filters inline. The best component on the page; the empty state should copy it.
3. Heading semantics — h1 to sr-only h2 to h3 per card with aria-labelledby, fieldset/legend. Above average, costs nothing.

## Priority Issues

[P0] The sort control lies twice over. What: distance_asc/distance_desc both map to orderByDesc('pickup_date'), identical to default, and the results meta reprints "Sorted by Distance" the instant the menu item is clicked, before any query runs. Why: distance is the lane decision; the UI claims to sort by it, does not, then announces it did — a trust event on a marketplace whose pitch is better matching. Fix: real ordering (orderBy route_snapshot.distance, nulls last) or delete the two options; echo "Sorted by" only when the response lands; pending state on the meta line during router.get. Suggested command: clarify.

[P0] The card omits distance, duration, and shipper verification while shipping them, and the own-post check reads the wrong key. What: the resource sends route_snapshot.distance_meters/duration_seconds/geometry and owner.display_name/is_verified; the card draws none of them. isOwn reads f.user (never sent), so owners see Express Interest on their own load and the Your Post badge is unreachable. geometry (12 OSRM polylines) ships per page view for a field its docblock says belongs behind a button. Why: a carrier cannot judge a lane or counterparty without opening every load — the grid is a list of addresses, not a marketplace — and this is the same owner-vs-visitor bug class already fixed on show. Fix: compact route line (Addis Ababa to Dewele, 932 km, ~14 h) plus shipper name with verified mark; gate isOwn on owner.id; strip geometry from the list payload. Suggested command: layout.

[P1] The page frame belongs to a different product. What: Laravel SAAS Starter header, four dead anchor links, XCO Agency footer pitch; no Post-a-load, My-Interests, or carrier CTA anywhere. Why: on a public trust surface the chrome is the credibility — a carrier decides whether to reveal their phone number under someone else's logo; dead nav items look interactive and do nothing. Fix: freight-scoped header (Numan Freight mark, Browse / Post a Load / My Interests / Sign in) and a plain freight footer; disable or repoint landing anchors when the sections are not mounted. Suggested command: adapt.

[P1] Express Interest is an unconfirmed, unguarded commitment post. What: one tap fires router.post('/my-freight/{ulid}/interests', { message: '' }) — no dialog, no processing state, no consequence copy; freight/show opens a full dialog for the identical action. Why: a mis-tap mid-scroll commits the carrier, a double-tap fires twice, and the moment of highest willingness gets the least information. Fix: open the existing interest dialog from the card (or two-tap inline confirm), disable while processing, one sentence of consequence copy. Suggested command: harden.

[P2] Empty state, error state, and wrong-audience CTA are one component. What: safeFreight's shape-guard converts payload failures into data: [] rendering No active freight found with the CTA Post Freight (the shipper's job), no Clear filters, no retry; above it renders Showing 1-0 of 0 results. Why: a carrier who filtered too hard is told to become a shipper; a carrier who hit an error is told the market is empty; the first line read is visibly impossible. Fix: three branches — no matches (lead with Clear filters), load failure (retry), anonymous carrier (Become a verified transporter); hide the count row at total 0. Suggested command: clarify.

## Persona Red Flags

Casey (distracted mobile; mobile-first is a stated product requirement): seven sequential controls stack before the first card, pushing the grid below the fold on 360px; both card CTAs are h-8 text-xs (32px tall, 12px labels — below the 44px touch minimum); Express Interest posts on first tap with no processing state; the mobile header targets are language, appearance, and hamburger — none is browse loads.

Jordan (first-timer carrier): distance and duration sit in the payload and are never drawn — every load must be opened to learn kilometres; owner.is_verified, the positioning pillar, is invisible at the decision moment; the only badge on every card reads Active twelve times, zero information in the prime slot; nothing explains what Express Interest means; the zero-results answer is Post Freight, the shipper's job; header nav promises Features/Pricing/Testimonials/FAQ and delivers four no-op clicks.

Riley (stress tester): clearAll() silently resets sort; deep links ?cargo_type=3&truck_type=2 filter with no chip shown (invisible constraint); double-tap Express Interest fires two POSTs; ?origin=5 on a deleted location renders "Origin: Location #5" (a DB id as user copy); empty results render "Showing 1-0 of 0 results"; interest badges render raw lowercase enums.

## Minor Observations

- Head title says NumanFreight; PRODUCT.md fixes the name as Numan Freight.
- Subtitle at zero results: "Browse 0 active freight posts — 934 major towns" — hardcoded proof-inventing next to a live zero.
- pickup_date renders raw 2026-10-22; pickup_date_flexibility_days is in the payload unused ("in 6 days" is free).
- Two amber alert implementations with different tokens sitting ~100px apart.
- Search field has three names: Label "Search", placeholder, aria-label.
- Pagination numeric aria-labels are "1", "2" rather than "Page 1".
- ArrowRight connector at text-muted-foreground/50 drops out at narrow widths.
- Card top-right badge duplicates the footer button state.

## Questions to Consider

1. You ship 12 OSRM polylines per page view to power nothing, while the one number inside them (km) is what the sort menu promises and the card omits — what would it take to ship less and show more?
2. isOwn reads a key no producer sends — has anyone ever loaded /freight while signed in as an owner? Every reader/writer contract on this surface may have gone unverified.
3. If the whole reason to choose Numan over a WhatsApp group is "verified network plus lane matching", why are both facts in the JSON and neither in the pixels?
