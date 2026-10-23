---
target: /freight discovery page
total_score: 22
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
target_identity: "file:/home/nate/projects/numan/resources/js/pages/discover/index.tsx"
target_fingerprint: "sha256:29b4e38dceff33855abbc3a7255a4edd98112436d10406e50c6de7cdb5acc25f"
target_path: /home/nate/projects/numan/resources/js/pages/discover/index.tsx
timestamp: 2026-10-22T13-25-07Z
slug: resources-js-pages-discover-index-tsx
---
# Critique (re-run): /freight discovery page (resources/js/pages/discover/index.tsx)

Method: dual-agent (A: ses_e9c4462feffeD1PZ4iYrOOeq75 · B: ses_e9c390a9fffe9N4taEWnrW9HK6)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Sort/cargo dropdowns relabel and set aria-current without triggering a visit; two sources of truth disagree on screen; can_interest never rendered; no skeleton during visits |
| 2 | Match System / Real World | 3 | ETB/towns/Express Interest/Consignor strong; raw enums leak (rejected/withdrawn, one_time elsewhere), ISO pickup dates, "Location #5" |
| 3 | User Control and Freedom | 2 | Dialog guards dismissal mid-processing; withdraw is one-tap unguarded with no toast; error path auto-navigates via setTimeout; login drops browse context |
| 4 | Consistency and Standards | 3 | Consistent shadcn vocabulary; chips remove instantly but applying needs the distant Apply button; h-8/h-9 height wobble |
| 5 | Error Prevention | 2 | Interest dialog + unknown-OSM warning are good; silent sort/cargo non-application previews a result it will not deliver |
| 6 | Recognition Rather Than Recall | 3 | Chips and labels externalize state; deferred-apply requires remembering Apply exists |
| 7 | Flexibility and Efficiency | 2 | Deep links work but doSearch drops truck_type/cargo_type/pickup_from/pickup_to silently; canRegister never sent |
| 8 | Aesthetic and Minimalist Design | 3 | Restrained shell; marketing hero + amber billing banner + primary Active badge x12 + 11-14px dense card |
| 9 | Help and Documentation | 2 | Interest dialog is the only model explanation; nothing defines Verified/Active/reveal quota |
| 10 | Error Recovery | 3 | Three-way empty separation and OSM message are the page's best work; string-sniffed routing is brittle |
| Total | | 22/40 | (prior run 20/40) |

## Design Specificity Verdict

LLM assessment: Partially anchored — the data is Ethiopian freight, the composition is not. Genuinely specific: ETB tariffs, real town names, consignor verification chip, Express Interest dialog marketplace model, metered-contact discipline. Category-interchangeable: the card is a generic shadcn Card with two symmetric key-value tables (swap for Price/Beds/Baths and it is a real-estate grid); the route — the atomic unit — is text plus a size-3.5 MapPin; the cargo taxonomy is hardcoded generic SaaS categories (General/Cereals/Construction/Refrigerated — no coffee, livestock, fertilizer, fuel, cement) while the server filters against the cargoType table; "Distance: Near to Far" is a generic-marketplace label for what freight calls lane length. The restrained shadcn shell is evidence, not failure; within it the product let data do all the anchoring work.

Deterministic scan: detect --json on discover/index.tsx + discovery-chrome.tsx returned exit 0, findings [] (clean), browser overlay skipped (no browser automation available). Parent verification: can_interest computed and shipped by the controller (lines 112/221) and declared in the interface but never rendered; cargo taxonomy hardcoded at page line ~600; canRegister never sent by the controller; doSearch builds its query from origin/destination/search/cargo/sort only, dropping deep-linked truck_type/cargo_type/pickup_from/pickup_to; card CTAs and Apply are h-8 (32px, below the 44px touch baseline).

## What's Working

1. Three-way empty-state separation (load-failure vs filtered-empty vs market-empty) with blame placed correctly and state preserved — rare, excellent.
2. Express Interest dialog copy names the counterpart, states what they see, pre-answers regret, guards dismissal mid-processing.
3. Accessibility depth: fieldset/legend, aria-labelledby wiring, aria-live results header, per-chip labeled removals, role=list cards, aria-current.

## Priority Issues

[P0] The sort and cargo controls lie: they report success without changing results. Dropdown selection only sets local state; trigger relabels, chip appears, aria-current sets, while the results header (server filter) still reads Sorted by Latest. Two visible sources of truth disagree on screen — the primary interaction of a browse surface. Fix: apply on selection (matching how chip-removal already re-queries instantly), or gate button/chip/header on server-confirmed state. Suggested command: clarify.

[P1] The card has no decision hierarchy: tariff typeset identically to Trucks at text-sm font-semibold; distance/duration at 11px muted; visual peaks are the h3 and the zero-information Active badge. The carrier must read five uniform rows and compute, card after card. Fix: tariff as typographic peak with ETB attached and "Tariff not stated" for absence; distance paired with the route line as second signal; demote Trucks/Item; remove the primary Active badge. Suggested command: typeset.

[P1] Mobile fold and touch targets: marketing subtitle + full filter card + chips + banner precede the first card on 360px; card CTAs, Apply, and filter controls are h-8 (32px) with text-xs, below the 44px baseline on a mobile-first product. Fix: collapse hero to one state-driven line; filter panel collapsible behind one Filters control with search always visible; raise controls to min-h-11 on touch breakpoints. Suggested command: layout.

[P2] Marketing copy makes unverifiable claims: "934 major towns" presented as fact with no evidence; "Find verified loads" claims load-level verification the product does not have (carriers/consignors are verified); "Across Ethiopia" with Djibouti in scope by default. Fix: state-driven subtitle (live count + approval fact + verified consignors). Suggested command: quieter.

[P2] Off-audience billing surface and dead carrier entitlement: the amber can_post post-limit banner is a shipper problem above carrier results, while can_interest — the carrier's quota, computed server-side — is never read, so exhaustion surfaces only as a brittle toast after commitment. Fix: render can_interest pre-emptively (disable with reason + upgrade path); move the can_post banner out of browse. Suggested command: distill.

## Persona Red Flags

Casey: hero + filter stack pushes first card below the fold; she relabels sort and the list does not move while the header contradicts the button; both card actions are 32px shared-width targets doing the most important work.

Jordan: subtitle promises "verified loads" undefined; Active badge x12 tells him nothing; "Near to Far" orders lane length, not distance from his depot; login from the interest button loses browse context; the exit button reads "Leave".

Riley: deep-linked truck_type/pickup_from/cargo_type render no chip and evaporate on next Apply; quota error string-sniffs into a forced redirect; dangerouslySetInnerHTML pagination labels; withdraw has no guard.

## Minor Observations

- "Leave" is ambiguous for withdraw; aria-label knows the right words.
- Raw status enums (rejected/withdrawn) lowercase-capitalized in badges.
- ISO pickup dates via slice(0,10) on a human decision surface.
- Redundant "Status: Active" aria-label announced twelve times.
- Withdraw unconfirmed/no processing/no success toast — asymmetric with commit.
- Cargo taxonomy hardcoded while server matches cargoType.name.
- canRegister never sent (dead prop).
- Login from interest button loses filter state (no return intent).
- No skip-to-content link despite main#main-content.
- "934 major towns" renders even at total 0.
- text-muted-foreground/50 at text-[11px] near the legibility floor outdoors.

## Questions to Consider

1. If the tariff is why a carrier picks a load, what would this card look like if the ETB figure were the largest thing on it, the way a rate sheet or fuel pump treats money?
2. What is "Distance: Near to Far" near to — the truck or the lane — and is that what the sort delivers?
3. Why does the carrier-facing page surface the shipper's post-limit quota in amber while computing and discarding the carrier's interest quota — who is this page for?
4. If the chrome comment says the chrome is the credibility, is a monochrome card grid with an 11px green check the credibility an Ethiopian carrier needs before wiring a real truck to a stranger's load?
