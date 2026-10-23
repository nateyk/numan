---
target: /freight load detail page
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
target_identity: "file:/home/nate/projects/numan/resources/js/pages/freight/show.tsx"
target_fingerprint: "sha256:b1129240ed99dd1e7e12f5f419488ad26bdefe470887e80bd1d78d6bb531f135"
target_path: /home/nate/projects/numan/resources/js/pages/freight/show.tsx
timestamp: 2026-10-22T13-25-55Z
slug: resources-js-pages-freight-show-tsx
---
# Critique: /freight load detail page (resources/js/pages/freight/show.tsx)

Method: dual-agent (A: ses_e9c4462f9ffenhNHnp2Q8WcrSA · B: ses_e9c4462f9bfferB0g1Z0XLPe1wZ)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Processing states exist; "awaiting consignor" unexplained; requiresPhone flow reports a successful POST as toast.error |
| 2 | Match System / Real World | 2 | Good bones (consignor/transporter/tariff/ETB); "Consignor Poster" jargon, raw schedule_type one_time, raw coordinates, Head title "NumanFreight" |
| 3 | User Control and Freedom | 2 | Back/Cancel/Browse exist; no withdraw or re-express from this page; one-tap metered reveal without confirm; 1.5s auto-redirect seizes navigation |
| 4 | Consistency and Standards | 2 | Internal shadcn consistency; LandingHeader vs DiscoveryHeader on adjacent pages of the same flow; hardcoded hrefs vs Wayfinder; h1 icons missing aria-hidden; raw <a> in poster card |
| 5 | Error Prevention | 2 | Interest dialog gates the POST; reveal server-metered; reveal itself one-tap irreversible; transporter-profile failure discovered after submit |
| 6 | Recognition Rather Than Recall | 3 | Facts, verification, quota at point of need; dialog claims contact fields that do not exist; next step never stated after interest |
| 7 | Flexibility and Efficiency | 2 | No prev/next between loads; Back to Freight drops the discovery query string |
| 8 | Aesthetic and Minimalist Design | 2 | Clean two-column restraint; origin/destination restated 3x, tariff typeset like coordinates; landing footer is the largest element and belongs to another product |
| 9 | Error Recovery | 2 | Toasts, inline recovery button, quota link; string-matched routing is brittle; quota warning renders with no button to act on; success reported as error |
| 10 | Help and Documentation | 2 | Dialog fine print, reciprocity note, straight-line estimate label; reveal quota arrives with no count and no definition |
| Total | | 21/40 | Acceptable (first run for this slug) |

## Design Specificity Verdict

LLM assessment: Largely unanchored, actively contradicted by its own chrome. Strip the values and this is a generic listing detail (job post, apartment). Real signals: ETB default, truck count, verified-consignor badge, reveal-gated contact, honest straight-line estimate. Missed opportunities: the tariff — the commercial fact of the marketplace — is one Row among fourteen identical rows, typeset no differently than Coordinates 9.0194, 38.7525 (raw lat/lng shown to carriers at all); the lane distance/duration the discovery card renders prominently live hidden inside the collapsed map toggle button label; verification is a 3px muted shield. And the chrome reads "Laravel SAAS Starter" above a "Verified consignor" badge — a specificity negative on the highest-trust surface.

Deterministic scan: detect --json on show.tsx + components/freight/ returned exit 0, findings [] (clean); browser overlay skipped (no browser automation available). Parent verification: LandingHeader/LandingFooter imports at show.tsx line 9 (rendered 174/469); hasInterest set true on ANY interest with no status filter (controller ~line 184); DialogDescription "Provide your contact details for the consignor to contact you." over a message-only form; requiresPhone fires toast.error inside onSuccess; "awaiting consignor" copy at line 314 regardless of status; Head title "NumanFreight" at line 170.

## What's Working

1. The reveal-gate architecture: three-state ContactBlock plus PostOwnerSummary's deliberate emptiness mean contact cannot leak structurally, and rendering nothing instead of a "contact hidden" placeholder refuses to teach carriers that a number exists behind a button that usually is not there.
2. Truthful verification states — VerificationBadge no longer hardcodes "Verified consignor"; isHiddenListing wording deliberately matches My Freight to avoid divergent screens describing the same fact.
3. Deliberate evaluation-first stack: details before CTA on mobile with a stated ordering comment; map behind a toggle with distance in the label and honest straight-line provenance.

## Priority Issues

[P0] Chrome regression: the load detail page ships the starter kit's marketing header and footer — "Laravel SAAS Starter" wordmark, four dead anchors, XCO footer pitch — while the discovery page that links here already carries DiscoveryHeader/DiscoveryFooter whose doc-comment says the chrome is the credibility. The grid-to-detail handoff breaks mid-flow. Fix: swap to DiscoveryHeader/DiscoveryFooter (pass canRegister). Suggested command: adapt.

[P1] Commercial and lane facts are demoted below the grid card that fed this page: tariff is one Row among fourteen in identical type; distance/duration hide inside the collapsed map toggle and vanish without coordinates; raw coordinates get equal billing to the money. Fix: stat strip under the h1 (Tariff ETB large, Distance km, Pickup window, Trucks); distance/duration always visible; delete the coordinates row. Suggested command: layout.

[P1] The interest state machine has five outcomes and the page renders two, both badly: hasInterest computed with no status filter, so a rejected carrier reads "Interest rejected — awaiting consignor" (false twice over) with the CTA blocked and no re-apply or withdraw anywhere on the page. Fix: map status to copy and actions — pending/"awaiting consignor"+Withdraw; accepted/"consignor accepted"+View connection; rejected/"not selected"+re-express if allowed. Suggested command: clarify.

[P2] The interest dialog description contradicts its own form ("Provide your contact details" over a form with zero contact fields), and the transporter-profile error both renders an inline recovery button and auto-navigates after 1.5s — one paraphrase away from firing on the wrong failure. Fix: description → "Send the consignor a short message — your profile contact details are shared only when you choose to reveal them."; delete the setTimeout, keep the inline button. Suggested command: clarify.

[P3] The reveal — the signature transaction — is underweighted and its failure states leak: a small outline button, no confirmation, no reciprocity statement before spending a scarce resource; requiresPhone reports success as toast.error; the quota-exhausted warning renders even when no reveal button was ever present. Fix: promote to primary in the action column once interest exists; lightweight confirm stating reciprocity; inline phone-required state instead of a post-success error toast; show quota message only when a reveal action is or was present. Suggested command: bolder.

## Persona Red Flags

Casey (mobile): first screen is the template header with dead anchors; the entire details card stacks before the CTA (lg:col-span-7 full card on mobile), pushing Express Interest roughly two screens down with no sticky companion; tapping it logged out visits bare /login without return intent — after sign-in she lands on the dashboard, the load gone; Back to Freight already dropped her filters.

Jordan (first-timer): the dialog promises contact fields that do not exist; "awaiting consignor" answers nothing about what happens next; "Uses one of your contact reveals" introduces a quota at the moment of spending with no count and no definition; the missing-profile error yanks him to another page mid-sentence.

Riley (stress): rejected interest renders "rejected — awaiting consignor" and blocks re-expression on an active load; quota warning renders on loads where no interest (hence no reveal button) exists; requiresPhone success triggers an error toast; string-snatched redirect fires on any error containing "transporter profile".

## Minor Observations

- Head title "NumanFreight" — brand inconsistent in the one place users tab-switch from.
- Hardcoded hrefs (/freight, /my-interests, /my-freight/{ulid}, reveal, billing) vs sibling's Wayfinder convention.
- Back to Freight and Browse More Freight duplicate the escape hatch; neither preserves the discovery query string.
- marketplaceEntitlement computed, shipped, never read — plan-forbidden carriers discover it after submit.
- h1 MapPin/ArrowRight lack aria-hidden (discovery card equivalents have it); dialog Label lacks htmlFor, Textarea lacks id.
- schedule_type renders raw one_time; coordinates rendered as a carrier-facing row.
- interestsCount in the poster card uses raw <a> (full reload for the owner's most common action).
- LandingFooter text-white/30-40 contrast below 3:1 (moot after chrome swap).

## Questions to Consider

1. The browse page was rescued from this chrome with a comment saying so, and the detail page — where a carrier hands over their phone number — was not. What does it say that this loop has not been walked end-to-end as a carrier since the chrome split?
2. If the discovery card renders "932 km · ~14 h" and tariff as first-class facts, what changed between grid and detail that made those facts worth hiding behind a collapsed toggle — and which page is right?
3. The reveal is where revenue and trust models intersect; is a one-tap outline button with a quota warning the right weight for the signature transaction?
4. The interest state machine has five outcomes and the page renders two, one lying in two directions — what else on this page is rendered from the happy path because the unhappy ones were never looked at?
