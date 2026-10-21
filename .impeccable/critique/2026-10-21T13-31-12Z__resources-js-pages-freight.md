---
target: resources/js/pages/freight
total_score: 15
max_score: 40
na_heuristics: 
p0_count: 3
p1_count: 2
target_identity: "file:/home/nate/projects/numan/resources/js/pages/freight"
timestamp: 2026-10-21T13-31-12Z
slug: resources-js-pages-freight
---
Method: dual-agent (A: ses_ea1695a22ffez05M162GIb11iG · B: ses_ea1695a1effe3N4rKjbpwctJfN)

Target: resources/js/pages/freight — shipper load flow (My Freight -> post wizard -> review -> choose a carrier). Source-based; browser automation not exposed this session, so no rendered-pixel check or overlay ran.

### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 1 | Review never renders form.errors; no freight page reads flash - submission succeeds or fails silently (verified: zero flash matches across freight/) |
| 2 | Match System / Real World | 2 | Right freight vocabulary (quintal, tariff, towns), but raw enums leak (one_time, under_review) and raw lat,lng rows shown to users |
| 3 | User Control and Freedom | 1 | Accept & Connect closes the post with no confirm/undo; review "Save draft" is window.history.back() |
| 4 | Consistency and Standards | 2 | Three primary-button placements in one wizard; owner's own post renders on the marketing shell; two toast systems |
| 5 | Error Prevention | 1 | Disabled Save Route gives no reason; irreversible accept/reject unguarded; failed town resolve silently disables the button |
| 6 | Recognition Rather Than Recall | 2 | Step 1 labeled "Route & Schedule" but schedule is step 3; cargo step never shows the chosen route |
| 7 | Flexibility and Efficiency | 2 | Good: ULID search, status filter, jumpable steps. Missing: duplicate/re-post, bid sorting, search button |
| 8 | Aesthetic and Minimalist Design | 2 | Clean per component, but list page stacks 2 plan banners + h1 + copy + search + select + button + menu + 8 chips before row one |
| 9 | Error Recovery | 1 | Field errors excellent on route/cargo/schedule, none on review.tsx or interests.tsx - "Invalid transition." invisible |
| 10 | Help and Documentation | 1 | 60% completion floor, what accepting does, moderation timing never explained |
| Total | | 15/40 | Poor |

### Design Specificity Verdict

LLM assessment: Not grounded enough. Data is freight (ULIDs, Ethiopian towns, ETB/quintal tariff, Leaflet route preview), but composition is stock shadcn CRUD - Card + Table + overflow menu, 8 status chips, 4-step wizard in the same shell as settings. A billing or CRM product could ship it unchanged. Undercut by implementation-facing copy ("act via dropdown", "934 major Ethiopian towns ... Leaflet route"), raw coordinates, untranslated enums.

Deterministic scan: 0 findings across all 8 freight files (directory + per-file scans, exit 0). Detector proven live: parent scan returned a real side-tab finding at admin/system-notifications.tsx:283; no impeccable-disable comments; no config/DESIGN.md suppression. Problems are structural/interaction-level, not CSS anti-patterns. No false positives.

Visual overlays: none - browser automation not exposed, injection never ran, no user-visible overlay for this run.

### Overall Impression

Competent, locally literate CRUD - cared about at the field level - but it does not yet look like this product. Biggest opportunity: make the loop's feedback real. Every freight controller flashes success/errors; no freight page renders either. The peak (submitted), the valley (waiting for bids), and the highest-stakes moment (accepting a carrier) all happen in silence. Cheapest work here, and the difference between "the app works" and "the shipper trusts it."

### What's Working

1. Progressive wizard that teaches the task: 4 gated steps, per-field errors, freight-specific placeholders.
2. Route preview is the one screen no unrelated SaaS could copy: freight-biased town search, OSM resolution, auto distance/duration with a real calculating state, persistent map.
3. Honest states where they exist: real dual layouts on the 3 pages that need them (index, interests, my-interests), empty states with a next action, proper destructive-confirm dialog, truthful "identity not verified" fallback.

### Priority Issues

[P0] Submit failures invisible on final step. review.tsx renders no form.errors while FreightReviewController::submit() returns back()->withErrors() for 5 reasons (origin, cargo, truck type, pickup date, score < 60). Why: shipper submits, page reloads unchanged, nothing says what to fix; task stops. Fix: render an error summary above submit (~lines 213-245) plus a field-by-field blocking checklist replacing the bare completionScore badge. Command: /impeccable harden

[P0] Submit confirmation never renders - no flash on any freight page. Controller redirects with success; Inertia swaps before onSuccess so setShowSuccessModal runs unmounted; freight/index.tsx never reads flash (verified). Why: the peak of the journey is missing - no confirmation, no moderation expectation. Fix: render flash.success banner on freight/index.tsx and/or redirect back to review so the modal paints. Command: /impeccable harden

[P0] "Manage Your Post" dead-ends the owner on a carrier-facing page. FreightPostController::show() (:132) loads locations/route but not user, passes no hasInterest/interestsCount/canReveal block, so isOwnPost resolves false. Why: shipper sees "Consignor - identity not verified" about themselves, an Express Interest button on their own load, or no management actions. Fix: load user.profile and pass the same prop block as PublicFreightDiscoveryController::show(), or redirect owner to the management view. Command: /impeccable clarify

[P1] Accept & Reject are one tap from an irreversible bid-killing decision with no feedback. interests.tsx:212 (desktop) and :252 (mobile) POST straight to acceptance, which closes the post; page renders neither errors nor flash; mobile disable logic (=== 'selected') is looser than desktop so a rejected bid taps and fails silently. Why: the commitment moment has no warning, no consequences, no result. Fix: confirmation dialog ("Accepting closes this post to all other carriers"), processing state, render errors/flash, align mobile disable logic. Command: /impeccable harden

[P1] Bids cap at 12 with a false total and nothing to compare. Controller paginates 12 with no links/total; interests.tsx:99 prints a page-local count as "N total"; pagination block never renders; rows carry no price, fleet size, rating, or response rate. Why: the carrier-choice screen neither shows all bidders nor decision data. Fix: pass/render links/total (or Inertia merge/infinite scroll); extend InterestResource + rows with comparison attributes. Command: /impeccable layout

### Persona Red Flags

Jordan (first-timer): review.tsx:213-245 - confirms, submits, page reloads unchanged; cannot tell a 60% block from a network failure, and the last step teaches nothing after every prior step taught with inline errors.

Sam (accessibility-dependent): route.tsx:198-206 label has no htmlFor/id so the combobox announces unlabeled; icon-only triggers (index.tsx:545, interests.tsx:208) unnamed; status badges near 3.1:1 contrast; schedule.tsx:56 toggles lack aria-pressed and visible focus.

Casey (distracted mobile user): interests.tsx:250-254 - three adjacent 32px buttons per card with Accept as solid primary in the middle, one thumb-tap from closing the post, no confirmation, no visible result.

Mahlet (project persona - Addis agri-exporter, 3 loads/week to Djibouti, phone + shared workspace): row menu has no Duplicate/Repost so every weekly load is retyped across three wizard screens; on mobile the menu drops Interests, forcing View -> Consignor card -> View to see who bid.

### Minor Observations

- FreightWizardShell.tsx:87-92 green "Saved" dot before anything saves; three completion indicators can disagree.
- route.tsx:270-295 renders "0 KM / 0 min" as if measured - fake value, not an empty state.
- Raw enums leak into copy: "You have no under_review posts yet." (index.tsx:412), one_time on detail card.
- interests.tsx:131 promises bids arrive "with contact details" while contact is metered behind a reveal quota.
- Insurance/depreciation on detail card have no wizard input - permanently "—".
- No list-level loading skeleton; only Inertia's top progress bar.
- Pagination uses dangerouslySetInnerHTML with no aria-current.
- Freight pages hardcode English while the app supports RTL and ships {ar,es,fr}.json.
- Empty breadcrumbs on every freight page - header row of trigger + search + notifications with dead space.
- index.tsx stacks 2 amber plan banners above the primary task; "act via dropdown" copy is implementation-facing.

### Questions to Consider

1. If accepting one carrier closes the post and burns every other bid, why are Shortlist / Accept / Reject three equal adjacent buttons instead of a staged compare -> shortlist -> choose with the irreversible one structurally separated?
2. The 60% completion floor is enforced but secret - should review show a field-by-field blocking checklist, and why is the threshold hidden?
3. /my-freight/{ulid} and /freight/{ulid} both render freight/show.tsx with different prop shapes and only one eager-loads user - which is the canonical manage URL?
4. Every freight controller flashes feedback and no freight page renders it, with two toast systems coexisting - is feedback deliberately page-local, or was the shared flash contract never wired up here?
