# NumanFreight — Fresh Build Guide

> **Purpose:** Single-source product + technical guide to rebuild NumanFreight from scratch on a new repository without guesswork.
> **Audience:** Developers, AI coding agents, and product implementers starting a clean `laravel/react-starter-kit` repo.
> **Status:** Pilot edition — July–Aug 2026 | Stack: Laravel 13 + Inertia React 3 + Tailwind 4
> **Motto:** *Move freight with more confidence.*

---

## Table of Contents

1. [What NumanFreight Is](#1-what-numanfreight-is)
2. [Core Business Model](#2-core-business-model)
3. [Market Problem & Value Proposition](#3-market-problem--value-proposition)
4. [Positioning — Hard Scope Boundaries](#4-positioning--hard-scope-boundaries)
5. [Customers & Stakeholders](#5-customers--stakeholders)
6. [Core Marketplace Journey (5 Steps)](#6-core-marketplace-journey-5-steps)
7. [Product Capability Inventory (Pilot Scope)](#7-product-capability-inventory-pilot-scope)
8. [User Experience Deep Dive — Onboarding & Journeys](#8-user-experience-deep-dive--onboarding--journeys)
9. [Technical Stack (Source of Truth)](#9-technical-stack-source-of-truth)
10. [Architecture Principles](#10-architecture-principles)
11. [Data Model — Key Tables & Enums](#11-data-model--key-tables--enums)
12. [Authentication, Onboarding & Authorization](#12-authentication-onboarding--authorization)
13. [Freight Posting — Domain & Wizard](#13-freight-posting--domain--wizard)
14. [Discovery, Interest, Selection & Connection](#14-discovery-interest-selection--connection)
15. [Transporter Profiles & Verification](#15-transporter-profiles--verification)
16. [Plans, Entitlements & Revenue](#16-plans-entitlements--revenue)
17. [Payments — Chapa Integration](#17-payments--chapa-integration)
18. [Maps, Locations & Route Preview](#18-maps-locations--route-preview)
19. [Notifications & Telegram](#19-notifications--telegram)
20. [Trust, Safety, Privacy & Legal](#20-trust-safety-privacy--legal)
21. [Admin Operations](#21-admin-operations)
22. [What NOT to Build (Guardrails + Roadmap)](#22-what-not-to-build-guardrails--roadmap)
23. [Rebuild Strategy — Why Fresh, Not Port](#23-rebuild-strategy--why-fresh-not-port)
24. [Fresh Repository Bootstrap — Step by Step](#24-fresh-repository-bootstrap--step-by-step)
25. [Development Workflow, Commands & Quality Gates](#25-development-workflow-commands--quality-gates)
26. [Seed Accounts & Local URLs](#26-seed-accounts--local-urls)
27. [Testing Strategy](#27-testing-strategy)
28. [Deployment & Pilot Launch Checklist](#28-deployment--pilot-launch-checklist)
29. [Appendix — File Map, Routes & FAQ](#29-appendix--file-map-routes--faq)

---

## 1. What NumanFreight Is

**NumanFreight** is a **digital freight marketplace for Ethiopia**. It connects:

- **Consignors** (people/companies that need cargo moved) with
- **Transporters** (independent drivers, fleet owners, trucking companies that have capacity)

It is **not** a transport-management system (TMS), carrier, freight broker, insurer, GPS tracker, or escrow platform. It solves the **discovery + trust + controlled contact** layer. Commercial negotiation, transport execution, cargo handling, and payment settlement happen **off-platform, directly between consignor ↔ transporter** after the platform reveals contact.

**One-line promise:** Make demand visible, supply discoverable, and contact exchange controlled.

**Pilot geography:** Ethiopia, starting with 3–5 controlled corridors (e.g., Addis → Hawassa, Addis → Dire Dawa). Do not launch nationwide on day one — concentrate liquidity corridor by corridor.

---

## 2. Core Business Model

| Dimension | Decision |
|-----------|----------|
| **Market type** | Two-sided marketplace |
| **Demand side** | Personal + business consignors |
| **Supply side** | Independent transporters + transport companies |
| **Revenue model** | **Role-based subscriptions & entitlements**, paid via **Chapa** (Ethiopia). **No cargo-value commission in pilot.** |
| **Trust model** | Profile moderation, verification levels, private documents, controlled contact reveal, abuse reporting, audit logs |
| **Communication** | In-app + Email + Telegram (optional, preference-controlled) |

### Monetization logic

- **Free plan** exists for both roles — enough to experience value and seed the marketplace.
- **Paid plan expands capability**, not trust:
  - Transporter: `Free → Pro`
  - Consignor: `Free → Business`
- **Verification is NEVER sold.** Pro/Business does NOT = verified. Verification is earned via moderation review.
- Entitlements are **configurable JSON + usage periods + atomic usage events**, so limits can be tuned without code changes.
- Keep pricing transparent by role. Measure: free→paid conversion, renewal, churn, ARPU, support cost.

---

## 3. Market Problem & Value Proposition

### Problems today

1. **Discovery friction** — fragmented calls, personal networks, informal Telegram groups.
2. **Low information quality** — inconsistent route/cargo/truck/date presentation.
3. **Trust uncertainty** — no way to distinguish reviewed profile from paid badge.
4. **Privacy risk** — publishing phone numbers = spam/harassment.
5. **Market opacity** — no visibility into recurring demand or under-served truck categories.
6. **Operational overhead** — moderation & analytics done in spreadsheets.

### NumanFreight response

Standardize **freight request → discover → interest → select → connect** into auditable, moderated records. Protect contact until acceptance. Provide admin tooling for moderation, imports, and analytics.

---

## 4. Positioning — Hard Scope Boundaries

| NumanFreight DOES | NumanFreight DOES NOT (pilot) |
|-------------------|-------------------------------|
| Publish structured freight opportunities | Dispatch vehicles/drivers |
| Help approved transporters discover freight | GPS track vehicles |
| Collect & moderate transporter profiles | Manage proof-of-delivery |
| Allow transporters to express interest | Hold cargo payments in escrow |
| Help consignors shortlist / accept / reject | Settle freight charges |
| Reveal contact after authorized selection | Negotiate via internal chat |
| Sell marketplace subscriptions (Chapa) | Charge cargo-value commission |
| Provide reporting, safety tools, admin controls | Act as carrier/broker/insurer/guarantor |

> **Guardrail:** Do not add GPS, dispatch, escrow, cargo-payment, internal chat, tender bidding, or full TMS even though they are “adjacent to freight.” Add only after a validated business case.

---

## 5. Customers & Stakeholders

| Participant | Need | Value |
|-------------|------|-------|
| **Personal consignor** | Occasional load moved | Simple onboarding, structured posting, interest review, controlled reveal |
| **Business consignor** | Recurring / higher-volume freight | Higher entitlements, post management, invoices, history |
| **Independent transporter** | Qualified freight, credibility | Public profile, route/truck visibility, matching alerts |
| **Transport company** | Fleet promotion, more opportunities | Multi-truck capabilities, directory visibility, paid options |
| **Moderator** | Safety & accuracy | Moderation queues, reasons, history, suspend/restore |
| **Billing admin** | Plan & payment exceptions | Subscriptions, invoices, transactions, reconciliation |
| **Support / Super admin** | Operate marketplace | Users, permissions, analytics, imports, exports, notifications |

---

## 6. Core Marketplace Journey (5 Steps)

```
Post → Discover → Express Interest → Select → Connect
```

1. **Post** — Consignor creates freight request with Ethiopian reference locations, cargo, truck, schedule. Searchable town data, not free-text routes. Draft → submitted → moderation → active.
2. **Discover** — Approved transporters browse active freight via search/filters (origin, destination, cargo type, truck type, dates, expiration). Pagination + result counts.
3. **Express Interest** — Transporter signals genuine interest with optional message. Quota & abuse controls enforced.
4. **Select** — Consignor reviews interests, can **shortlist / accept / reject**. State transitions are permission-controlled and audited. Withdrawal allowed before final selection.
5. **Connect** — After **accept**, a `MarketplaceConnection` is created and **contact details are revealed** to authorized party. Outcome tracked: `contacted / successful / no_response / not_suitable / cancelled`.

Supporting controls: draft/moderation/published/closed/expired states, automated expiration, reminders, audit-friendly policy enforcement.

**North-star behavior:** A high-quality freight post receives timely interest from a suitable transporter, consignor accepts one, both connect, outcome = successful/useful.

---

## 7. Product Capability Inventory (Pilot Scope)

| Capability | Includes | Business Purpose |
|------------|----------|------------------|
| **Accounts & Onboarding** | Auth, email verification, resumable role-specific onboarding (see §12) | Reliable identities, reduce incomplete registrations |
| **Reference Data** | Ethiopian locations, truck types, cargo types (admin CRUD) | Standardize search, eliminate free-text chaos |
| **Transporter Profiles** | Capabilities, routes, privacy flags, documents, completion score, moderation, revision workflow | Credible supply, discoverable fleet |
| **Freight Posting** | Guided wizard, quality checks, duplicate detection, moderation, expiration | Usable demand records |
| **Interest & Selection** | Interest → withdraw → shortlist → accept/reject → contact reveal | Convert discovery to qualified introductions |
| **Plans & Entitlements** | Role-specific plans, subscriptions, usage periods, configurable quotas | Monetize without blocking core flow (see §16) |
| **Payments & Invoices** | Chapa init → verify → webhook → fulfillment → invoices | Sell subscriptions safely in ET (see §17) |
| **Notifications** | In-app, email, Telegram, preferences, retry, deduplication | Faster response |
| **Safety & Privacy** | Reports, private docs, exports, deletion, legal pages | Accountable operations |
| **Administration** | Moderation, plans, subs, analytics, imports, exports, user access | Operate marketplace |

---

## 8. User Experience Deep Dive — Onboarding & Journeys

### 8.1 Registration → Email Verification → Onboarding (MANDATORY GATE)

**Stack:** Laravel Fortify + 2FA + passkeys. Single `users` table.

**Onboarding is a separate gate** protected by `onboarded` middleware. Every new user must complete it before `dashboard`, `my-freight`, etc.

**Onboarding flow — 3 steps** (`resources/js/pages/onboarding/index.tsx` + `OnboardingController`):

```
Step 1: capabilities  →  Step 2: account_type  →  Step 3: details  →  dashboard
```

| Step | Key | What user does | Fields / logic |
|------|-----|----------------|----------------|
| 1 | `capabilities` | Choose what you want to do (multi-select) | `posts_freight` (Post Freight 📦) + `provides_transport` (Find Freight 🚚). At least one required. |
| 2 | `account_type` | Personal vs Business | `personal` / `business`. Determines Step 3 fields. |
| 3 | `details` | Fill identity | **All:** `display_name`, `phone`, `primary_location_id` (Ethiopian town). **If business:** `legal_name` (e.g., “ABC Transport PLC”) + `legal_form` (PLC/SC/Sole Proprietorship/Partnership/Gov/NGO/Association/Cooperative/Other → if Other, free-text). Validation server-side, resumable if interrupted. |

**UX:** `AuthLayout`, 3-bullet progress indicator, card buttons with checkmarks, shadcn `Select` for locations. Data stored on `marketplace_profiles` table, linked 1:1 to `users`.

> **Rebuild note:** Do NOT skip onboarding or fold it into registration. Keep it as its own Inertia page + middleware so fresh-repo setup is testable in isolation.

### 8.2 Freight Posting — Wizard (Current: 4 steps, Target: 4-step compact wizard)

**Routes (see `routes/web.php`):**

```
GET  /my-freight                 → FreightPostController@index
POST /my-freight                → FreightPostController@store  (creates draft + ULID)
GET  /my-freight/{ulid}/route   → FreightRouteController     (Step 1)
GET  /my-freight/{ulid}/cargo   → FreightCargoController     (Step 2)
GET  /my-freight/{ulid}/schedule→ FreightScheduleController  (Step 3)
GET  /my-freight/{ulid}/review  → FreightReviewController    (Step 4)
GET  /my-freight/{ulid}/pricing → FreightPricingController
GET  /my-freight/{ulid}         → FreightPostController@show
```

**Wizard shell:** `FreightWizardShell` + `FreightWizardFooter` — 4-step stepper:

```
1. Route & Schedule  2. Cargo & Truck  3. Post Details  4. Review & Submit
```

- Shows numbers for future, checkmarks for completed, highlights current.
- Allows returning to completed steps, prevents jumping to invalid futures.
- Max width ~1200px, thin borders, 6–8px radius, neutral palette, accent only for active states.
- Right side of header: `Save draft` status (Saved / Saving… / Unsaved / Unable to save).

**Step 1 — Route & Schedule** (`resources/js/pages/freight/route.tsx` — already implemented, use as reference):

- **Layout:** Desktop 40% form left / 60% map right (`lg:grid-cols-12`: 5 + 7). Mobile stacked.
- **Origin card:** `LocationSearchInput` (Ethiopian town search via `/locations/search`), A-marker. Destination: B-marker. Each shows “point confirmed on map.”
- **Schedule card:** `pickup_date` (date input), `pickup_time` (time input), `pickup_date_flexibility_days` (±0–30), `schedule_type` (One-Time / Recurring).
- **Map:** `RoutePreviewMap` (React Leaflet, OSM tiles). Config from `map_config` (tile_url, attribution, default_center). Shows `routePreview` geometry, loading (“Calculating OSRM route…”), error, and footer summary: `Addis Ababa → Hawassa — 275 km — approx. 4h 45m`. Route is fetched via `POST /freight/routes/preview` (server OSRM call; **never calculate distance in browser**). Lazy-loaded to avoid SSR issues.
- On submit: `PUT /my-freight/{ulid}/route`.

**Step 2 — Cargo & Truck** (`freight/cargo.tsx`):
- Only fields that exist in domain: `cargo_type_id`, `cargo_description`/`cargo_name`, weight/quantity if present, `required_truck_type_id`, `truck_count` (with `[-] 2 [+]` + native numeric input). Keep light — no pallets, temperature, hazmat unless already in schema.

**Step 3 — Post Details** (`freight/schedule.tsx` etc.):
- Marketplace-specific: `Direct Offer` vs `Tender` (two selectable panels, one-sentence explanation each, **use domain terminology exactly**), plus `closing/expiry date`, `additional instructions`. **No phone/email/WhatsApp** — contact reveal stays as Interest→Select→Connection.

**Step 4 — Review & Submit** (`freight/review.tsx`):
- Route preview/map on top, then freight-card-like summary (origin→destination, cargo, truck, count, pickup date, distance), then edit links per section.
- Entitlement usage line: `Your plan — 2 of 15 freight posts used this month` (from existing entitlement service output).
- Unchecked checkbox: “I confirm that the freight information is accurate.”
- Primary CTA: **Submit for review** (not “Publish now” — moderation is authoritative). Secondary: Save draft.

**Draft & validation UX:**
- Preserve draft behavior (per-step save). Returning restores step, selections, exact markers, route, cargo, truck, details. Never lose map state after validation failure.
- Errors shown beside fields, not a wall at top. On Continue: validate current step, focus first invalid field. Messages: “Choose an origin.” / “Confirm the exact loading point.” etc.

**Sticky action bar (desktop):**

```
Previous                  Save draft   Continue →
Previous                  Save draft   Submit for review   (on Review)
```

### 8.3 Discovery & Search

- **Public browsing** (`PublicFreightDiscoveryController@index` → `/freight`, `/discover`): search by origin/destination, cargo type, truck type, pickup date, expiration filtering. No auth required. Shows only `active` / approved freight.
- **Authenticated dashboard** (`UserNotificationController`, `PublicFreightDiscoveryController@discover`): personalized feed.
- UI: filters + sorting + result count + pagination. Cards show origin→destination, cargo description, truck, date, distance, status.

### 8.4 Transporter Flow: Browse → Express Interest

- **Directory:** `PublicTransporterDirectoryController` (`/transporters`, `/transporters/{slug}`) — only `approved` + `visible` profiles.
- **Express interest:** `POST /my-freight/{ulid}/interests` via `FreightInterestController`. Transporter adds optional message. Quota enforced by `MarketplaceEntitlementService` + `MarketplaceUsageEvent` (atomic). Policies prevent self-interest (can't interest own post) & duplicate active interest.

### 8.5 Consignor Flow: Review Interests → Shortlist / Accept / Reject

- **My interests:** `FreightPostInterestController`, `FreightInterestStatusController`, `FreightInterestAcceptanceController`, `TransporterMyInterestsController`.
- Consignor dashboard (`freight/interests.tsx`, `freight/my-interests.tsx`): list interests per post, each with transporter snapshot.
- Actions: `shortlist` → `accept` / `reject`, plus `withdraw` (transporter side before accept). Each transition writes `freight_interest_status_histories` + fires notifications.
- **Accept = atomic creation of `MarketplaceConnection`** (see §14).

### 8.6 Connection & Controlled Contact Reveal

- `MarketplaceConnectionController`, `MarketplaceConnectionOutcomeController`, `MarketplaceConnectionCancellationController`.
- After accept, authorized users see contact via `MarketplaceConnection` + policy-guarded `contactDetailsFor()`. **Consignor contact is never public.**
- Outcome tracking by either party: `contacted / successful / no_response / not_suitable / cancelled` → stored in `marketplace_connection_outcomes` + audit log.
- Own connections visible at `/connections` (`connections/index.tsx`, `connections/show.tsx`).

### 8.7 Dashboard (per role)

`routes/web.php` → `GET /dashboard` (onboarded): shows counts (`myFreightCount`, `activeFreightCount`, `myInterestsCount`), recent freight/interests, current plan, 30-day activity chart (freight vs interests per day), deltas vs previous 30 days. Built for the “one-account, multi-capability” model.

---

## 9. Technical Stack (Source of Truth)

Always verify against installed `composer.json` + `package.json` — do NOT copy outdated docs that mention Wave/Livewire/Folio/Filament/CoreUI.

| Layer | Technology |
|-------|------------|
| **Framework** | Laravel 13.x (PHP 8.3+) |
| **Frontend** | Inertia.js 3 + React 19 + TypeScript 5.7 |
| **Styling** | Tailwind CSS 4 + shadcn/ui (Radix) |
| **Auth** | Laravel Fortify 1.37 (2FA, recovery codes, passkeys via `laravel/passkeys`) |
| **Roles/Perms** | `spatie/laravel-permission` 8.x |
| **Flags** | `laravel/pennant` 1.24 |
| **SEO** | `artesaos/seotools` |
| **Telegram** | `irazasyed/telegram-bot-sdk` 3.16 |
| **Health/Backup** | `spatie/laravel-health`, `spatie/laravel-backup` |
| **DB** | SQLite locally, **MySQL 8+ in production** |
| **Testing** | PHPUnit 12 + Pest (feature + unit) |
| **Lint** | Pint (PHP), ESLint + Prettier (JS), `phpstan` level 7 via `larastan` |
| **Build** | Vite 8 + `@inertiajs/vite`, Wayfinder 0.1.14, `shadcn` 4.18 |
| **Maps** | `leaflet` 1.9.4 + `react-leaflet` 5 + `@types/leaflet` |
| **Tables/Charts** | `@tanstack/react-table` 8, `recharts` 3 |
| **Forms** | `react-hook-form` 7 + `zod` 4 + `@hookform/resolvers` |
| **DnD** | `@dnd-kit/*` 6–10 |

Check sibling files before creating new components — `resources/js/components/ui`, `resources/js/components/freight`, `resources/js/components/maps` already have reusable primitives.

---

## 10. Architecture Principles

1. **One-account architecture** — Single `users` table, one auth system. Consignor/transporter are **profiles + capabilities + records**, not separate guards.
2. **Admin role ≠ marketplace capability** — Admin roles (Administrator, Moderator, Billing Admin, Support) gate platform authority. Marketplace actions gate via **policies + profile status + moderation status + entitlements**.
3. **Server-authoritative everything** — No browser-trusted distance, pricing, or moderation state. OSRM, entitlements, and contact reveal are server-computed.
4. **Entitlements as config** — `marketplace_plans` + `marketplace_plan_entitlements` + `marketplace_plan_prices` drive quotas. `MarketplaceEntitlementService::subscriptionFor()` + `MarketplaceUsageEvent` enforce atomically.
5. **Moderation as state machine** — Explicit transitions, required reasons, audit logs, valid-status guards, transactions.
6. **Contact reveal = connection creation** — `MarketplaceConnection` is source of truth; `contactDetailsFor()` respects policies.
7. **Private docs, safe filenames** — `transporter_documents` on private disk, randomized storage path, policy-gated download (`AdminTransporterController@downloadDocument`).
8. **Package-first frontend** — Reuse Inertia/Tailwind/shadcn/React Leaflet/Leaflet/OSRM/reference-data endpoints. Do not add a second map/calendar/combobox framework without a real gap.

---

## 11. Data Model — Key Tables & Enums

### 11.1 Migrations overview

Run `ls database/migrations/*.php` — pilot has ~30 migrations. Key ones:

```
users, cache, jobs, passkeys, permission_tables
locations, cargo_types, truck_types
marketplace_profiles
transporter_profiles, transporter_profile_truck_type, transporter_routes
transporter_documents, transporter_profile_moderation, transporter_profile_revisions
freight_posts, freight_post_moderation_logs, freight_post_routes (+ exact lat/lng)
freight_interests, freight_interest_status_histories
marketplace_connections, marketplace_connection_outcomes, marketplace_connection_audit_logs
marketplace_plans, marketplace_plan_prices, marketplace_plan_entitlements, marketplace_plan_features, marketplace_plan_audit_logs
marketplace_subscriptions, marketplace_payment_transactions, marketplace_payment_receipts, chapa_webhook_events
marketplace_usage_events, marketplace_entitlement_definitions
marketplace_notification_deliveries, marketplace_notification_preferences, marketplace_notification_deduplications
marketplace_telegram_accounts, marketplace_telegram_link_tokens, telegram_webhook_updates
marketplace_reports, marketplace_report_evidences, marketplace_report_status_histories, marketplace_safety_actions
marketplace_data_export_requests, marketplace_account_deletion_requests, marketplace_account_restrictions
marketplace_legal_documents, marketplace_legal_document_versions, marketplace_legal_acceptances
marketplace_daily_metrics
```

### 11.2 Core domain columns (condensed)

**`freight_posts`** — `ulid` (public key), `user_id`, `origin_location_id`, `destination_location_id`, `origin_exact_lat/lng`, `destination_exact_lat/lng`, `origin_site_note`, `destination_site_note`, `origin_point_confirmed_at`, `destination_point_confirmed_at`, `cargo_type_id`, `cargo_description`, `required_truck_type_id`, `truck_count`, `schedule_type` (`one_time|recurring`), `pickup_date`, `pickup_time`, `pickup_date_flexibility_days`, `status` (enum), `completion_score`, plus route cache on `freight_post_routes` (`distance_meters`, `duration_seconds`, `geometry`, `routing_provider`).

**`freight_interests`** — `freight_post_id`, `user_id`, `status` (`submitted → shortlisted → selected / rejected / withdrawn`), `message`, `accepted_at`, plus history table.

**`marketplace_connections`** — `public_id`, `freight_post_id`, `freight_interest_id`, `consignor_user_id`, `transporter_user_id`, `outcome` (nullable), audit logs.

**`transporter_profiles`** — `user_id` (unique), `slug`, `status`/`moderation_status`, `verification_level`, `completion_score`, phone/WhatsApp/email + visibility flags, capabilities.

**`locations / cargo_types / truck_types`** — `name`, `region_name` (locations), `is_active`.

**`marketplace_plans`** — `public_id`, `name`, `marketplace_role` (`transporter|consignor`), `is_default`, `is_archived`, plus prices & entitlements.

### 11.3 Status enums (read from `app/Models/*` + `app/Enums` if present)

- **FreightPost status:** `draft → submitted → under_review → active → closed / expired / rejected` (check `FreightPost` model `status` enum + `label()` helper).
- **FreightInterest status:** `submitted → shortlisted → selected / rejected` + `withdrawn` (by transporter before accept).
- **Connection outcome:** `contacted / successful / no_response / not_suitable / cancelled / unknown`.
- **Transporter moderation:** `pending → approved / rejected`, with verification levels (`unverified / verified / ...`).

> Rebuild: create enums as PHP 8.1 `enum` with `label()` method — do not use raw strings in controllers.

---

## 12. Authentication, Onboarding & Authorization

- **Fortify** handles registration, login, email verification, password reset, 2FA, passkeys. Keep `fortify.php` + `config/auth.php` from starter kit.
- **Onboarding gate:** `OnboardingController` (3 steps above) writes to `marketplace_profiles`. Middleware `onboarded` redirects to `onboarding.index` if incomplete.
- **Spatie Permission:** tables from `2026_08_04_115340_create_permission_tables.php`. Roles: `Administrator`, `Moderator`, `Billing Admin`, `Support`, plus base `User`. Permissions like `admin.access`, `freight.view`, `freight.moderate`, `transporters.moderate`, `plans.manage`, `analytics.view`, etc. Check `database/seeders/*` for seeding.
- **Policies:** `FreightPostPolicy`, `TransporterProfilePolicy`, plus service-level checks. Every sensitive action (moderate, accept, reveal contact, download document) has a policy gate.
- **Wayfinder:** generated typed route helpers under `resources/js/actions` + `resources/js/routes`. Run `php artisan wayfinder:generate --with-form` after route changes and keep `npm run types:check` green.

---

## 13. Freight Posting — Domain & Wizard

See §8.2 for full UX. Technical notes:

- **Create draft first:** `POST /my-freight` creates `freight_posts` with `status=draft` + ULID. Wizard steps `PUT` to that ULID.
- **Route step** stores exact points + site notes + schedule + also persists a server-computed route snapshot (`freight_post_routes`). Coordinates are `decimal(10,7)` style — never expose raw coords prominently in UI.
- **Completion score** calculated server-side (how many required fields filled).
- **Validation** per step, keep valid data, keep map state. Use `FreightPost` model + Form Requests (`app/Http/Requests`).
- **Submit for review:** transitions `draft → submitted` → goes to admin moderation queue (`AdminFreightController`). **Not “publish now.”**

---

## 14. Discovery, Interest, Selection & Connection

**Discovery:**

- `PublicFreightDiscoveryController@index` (public `/freight` + `/discover`) — filters: origin/destination, cargo type, truck type, dates, expiration. Uses Eloquent scopes, indexes on `origin_location_id`, `destination_location_id`, `status`, `pickup_date`.
- Pagination via `LengthAwarePaginator`. Result counts shown.

**Interest:**

- `FreightInterestController@store` — checks entitlement via `MarketplaceEntitlementService` (e.g., `interests_per_day`), writes `MarketplaceUsageEvent`, creates `freight_interests` with `submitted`.
- Rate limits + duplicate checks prevent spam. Policies: cannot interest own post, cannot double-interest while active.

**Selection:**

- `FreightInterestStatusController`, `FreightInterestAcceptanceController` — consignor can `shortlist → accept / reject`. Each valid transition writes `freight_interest_status_histories` + notifies.
- `TransporterMyInterestsController` — transporter can `withdraw` before accept.

**Connection:**

- On `accept`, `MarketplaceConnection` created atomically (covers `freight_post_id`, `freight_interest_id`, `consignor_user_id`, `transporter_user_id`). Contact reveal is `MarketplaceConnection::contactDetailsFor($user)` — policy-guarded.
- `MarketplaceConnectionOutcomeController` — either party records outcome. Audit in `marketplace_connection_audit_logs`.

---

## 15. Transporter Profiles & Verification

- **Eligibility:** one `transporter_profiles` per user (DB unique). Draft & resumable onboarding, completion scoring, truck types M2M (`transporter_profile_truck_type`), operating routes (`transporter_routes` from reference locations), contact preferences, business description.
- **Documents:** `transporter_documents` — `license`, `insurance`, etc. Stored on **private disk** (`storage/app/private` or S3 private), randomized filename, `validation`, policy-controlled download, admin review (`AdminTransporterController@reviewDocument`).
- **Moderation states:** `pending / approved / rejected`, `verification_level`, `required decision reasons`, history (`transporter_profile_moderation`). Material change uses `transporter_profile_revisions` (preserves approved public data while revision pending).
- **Directory:** `PublicTransporterDirectoryController` — only `approved + visible` profiles, privacy flags respected (phone/WhatsApp/email per profile).
- **Duplicate detection:** heuristic on phone/name for `admin.transporters.index` + import pipeline.

> **Public trust principle:** Verification badge explains *what was reviewed* and *what it does not guarantee*. It is not insurance.

---

## 16. Plans, Entitlements & Revenue

**Tables:** `marketplace_plans` (`marketplace_role`: transporter/consignor), `marketplace_plan_prices` (ETB amounts), `marketplace_plan_features`, `marketplace_plan_entitlements` (JSON limits), `marketplace_subscriptions` (user↔plan with `provider_reference`, `period_start/end`, `status`), `marketplace_usage_events` (atomic per-consumption rows), `marketplace_entitlement_definitions`.

**Service:** `MarketplaceEntitlementService`

```php
$subscription = $entitlementService->subscriptionFor($user);
$plan = $subscription->plan;
$remaining = $entitlementService->remaining($user, 'active_freight_posts');
$entitlementService->consume($user, 'new_posts_this_month'); // atomic
```

**Role plans (pilot):**

| Role | Entry | Paid | Logic |
|------|-------|------|-------|
| Transporter | Free | Pro | Free seeds supply; Pro expands opportunity access & entitlements |
| Consignor | Free | Business | Free seeds demand; Business expands posting & management |

**Quotas (examples, configurable):** `active_freight_posts`, `new_posts_per_month`, `real_time_alerts`, `saved_templates`, `company_users`, `branches`, `bulk_creation`, `reports/exports`.

**What plans MUST NEVER restrict** (even Free can do): browsing freight, viewing detail, expressing genuine interest, transporter responses, tender posting/responses, profile creation, verification review. Abuse rate limits are separate from plans.

**Contact reveal is metered by plan.** Contact details are never inlined into a page. They are released only by the explicit reveal action, which:

- requires a real relationship first — a live interest (not `rejected`/`withdrawn`) or an existing connection between the two users. Anyone else gets a 403.
- reads phone/email/whatsapp from the user's **profile** and **transporter profile**, never from per-interest fields. The transporter's own `phone_visible` / `email_visible` / `whatsapp_visible` flags still apply.
- counts once per `(revealer, revealed)` user pair against the plan limit `contact_reveals_per_week`, over a rolling window of `free_contact_reveal_window_days`. Re-revealing a partner you have already revealed is free and unlimited — that pairing is recorded permanently in the `contact_reveals` ledger.
- is unlimited (`-1`) for an active paid subscription.
- must not leak through any other surface. Every public and counterparty-facing page uses an explicit API resource, never a whole Eloquent model, so contact and bank columns cannot ride along under an unexpected key.

**The Free cap is a product decision.** It is set by the admin in `marketplace_settings` (`contact_reveals_per_week`) with a config fallback, and is enforced on the backend: a blocked reveal returns no contact keys at all and a Subscribe link, never a partial or degraded payload.

**Admin:** `AdminMarketplacePlanController` (create/edit/duplicate/archive/set-default) + `AdminMarketplaceSubscriptionController` (assign/cancel/suspend/restore) — permission `plans.manage`, `subscriptions.manage`.

> **Phase 0 note:** `docs/NUMANFREIGHT_PROJECT_RULES.md` says “Do not implement subscriptions or plan enforcement in Phase 0.” Keep entitlement enforcement behind Pennant flags or simple bypass until marketplace core loop works.

---

## 17. Payments — Chapa Integration

**Single gateway:** Chapa (Ethiopia). Razorpay/Stripe/PayPal code (if found) must be deleted — see Rebuild Analysis §7 (critical payment manipulation risk).

**Flow (server-verified, idempotent):**

```
Checkout init (CheckoutController)
  → creates MarketplacePaymentTransaction (unique tx_ref, amount, plan_id, status=pending)
  → redirects to Chapa checkout
  → Chapa callback → ChapaCallbackController
  → Chapa webhook → ChapaWebhookController (signature verified, writes ChapaWebhookEvent, deduped)
  → server-side verification via Chapa API (never trust browser redirect)
  → fulfillment (atomic, idempotent: verified_at + unique provider_ref)
  → MarketplaceSubscription activated + MarketplacePaymentReceipt/invoice
  → user sees receipt at /billing/*, admin at /admin/payments
```

**Key invariants:**

- Transactions / invoices / fulfillment have **separate states**.
- Webhook signature + critical fields validated.
- Fulfillment is **atomic + idempotent** (duplicate webhook does not double-activate).
- Admin can reconcile (`AdminPaymentController@reconcile`, `AdminFinanceController`).
- **Scope:** Chapa sells NumanFreight *plans only*. Transport fees are settled off-platform.

**Docs:** `docs/operations/payment-incident-playbook.md`, `docs/operations/production-environment.md`, `.env` keys `CHAPA_SECRET_KEY`, `CHAPA_WEBHOOK_SECRET`.

---

## 18. Maps, Locations & Route Preview

**Reference data:** admin CRUD at `/admin/reference-data/locations|cargo-types|truck-types` (`AdminLocationController` etc.). Types toggle `is_active`.

**Frontend search:** `LocationSearchInput` hits `LocationSearchController` → Ethiopian town search with `id, name, region_name, latitude, longitude`. Example result:

```
Addis Ababa
Addis Ababa

Kombolcha
South Wollo • Amhara
```

**Route preview:** `RoutePreviewController@preview` (POST `/freight/routes/preview`, CSRF, JSON: `from_lat/lng, to_lat/lng`) → server calls **OSRM** (configured URL), returns `distance_meters, distance_km, duration_seconds, geometry ([lat,lng][]), provider`. Browser never calculates authoritative values.

**Maps:** `Leaflet` + `React Leaflet` (`RoutePreviewMap` lazy-loaded), OSM tiles from `map_config` (`tile_url`, `tile_attribution`, `default_center`, `default_zoom`). Markers: A (green, origin), B (blue, dest), polyline for route, fitBounds. Show loading + route-unavailable + below-map summary (e.g., `Addis Ababa → Hawassa — 275 km — approx. 4h 45m`) using server distance/duration.

---

## 19. Notifications & Telegram

**Channels:** database (in-app) + email + **Telegram** custom channel (`irazasyed/telegram-bot-sdk`).

**Categories:** interests, selections, payments, subscriptions, moderation, matching freight, safety, exports, account events. See `MarketplaceNotificationPreference` + `MarketplaceNotificationDelivery`.

**Preferences:** user controls optional categories at `/settings/notifications` (`UserNotificationController`). Critical/security notifications are **non-disableable**.

**Telegram:** connection via expiring `MarketplaceTelegramLinkToken` → verified webhook (`TelegramWebhookController`) → stores `MarketplaceTelegramAccount` + `telegram_webhook_updates` (deduped). Retry + deduplication (`MarketplaceNotificationDeduplication` window) prevent spam. Scheduled reminders: freight expiration, subscription expiry, connection feedback, cleanup.

**Admin:** `AdminNotificationDeliveryController` — filter/delivery inspection/retry.

> Telegram is strategic for ET pilot (fast response) but **optional + privacy-aware + secondary to in-app record**.

---

## 20. Trust, Safety, Privacy & Legal

| Area | Approach |
|------|----------|
| **Contact privacy** | Consignor contact never public; transporter phone/WhatsApp/email per privacy flags |
| **Documents** | Private disk, randomized filenames, validation, policy-gated access |
| **Moderation** | Permission checks, valid transitions, required reasons, transactions, history |
| **Abuse reporting** | `ReportController` → `MarketplaceReport` (polymorphic: freight/transporter/user) with evidence (`MarketplaceReportEvidence`), rate limits, duplicate checks, reporter protected, admin triage at `/admin/safety/reports` |
| **Privacy center** | `/privacy` — `PrivacyCenterController` — exports (`MarketplaceDataExportRequest`), retention, `MarketplaceAccountDeletionRequest` (scheduled, legal hold, supports `AdminPrivacyRequestController`), restrictions |
| **Legal** | `LegalDocumentController` — `terms, privacy, marketplace_rules, verification_explanation, safety_policy, subscription_terms, payment_terms`. Versioned (`MarketplaceLegalDocument` + `MarketplaceLegalDocumentVersion` + `MarketplaceLegalAcceptance`). Admin at `/admin/legal-documents`. |
| **Search indexing** | `noindex` where privacy/account prefs require (via `artesaos/seotools`) |

**Marketplace disclaimer:** *NumanFreight facilitates discovery & introductions. Users must independently assess counterparties, cargo, vehicles, licenses, insurance, pricing, compliance before transport.*

---

## 21. Admin Operations

**Guard:** `admin.access` + per-feature permissions (`permission` middleware in `routes/admin.php`).

**Prefix:** `/admin` (via `routes/web.php` → `require admin.php`).

**Resources:**

| Area | Route | Controller | Notes |
|------|-------|------------|-------|
| Dashboard | `GET /admin` | `DashboardController` | Links to real queues, not demo metrics |
| Freight moderation | `GET/POST /admin/freight` | `AdminFreightController` | Queue, show, `moderate` with reasons |
| Freight interests | `GET /admin/freight-interests` | `AdminFreightInterestController` | Read-only overview |
| Connections audit | `GET /admin/connections` | `AdminMarketplaceConnectionController` | `reveal-contact` needs `connections.view_sensitive` |
| Plans | `CRUD /admin/plans` | `AdminMarketplacePlanController` | duplicate/archive/set-default |
| Subscriptions | `/admin/subscriptions` | `AdminMarketplaceSubscriptionController` | assign/cancel/suspend/restore |
| Finance | `GET /admin/finance` | `AdminFinanceController` | Reconciliation view |
| Payments | `/admin/payments` | `AdminPaymentController` | show/receipt/reconcile |
| Transporters | `/admin/transporters` | `AdminTransporterController` | moderation + `documents/review|download` + `revisions/review` |
| Reference data | `/admin/reference-data/*` | `Admin*Controller` | locations/cargo-types/truck-types CRUD + toggle |
| Notification deliveries | `/admin/notification-deliveries` | `AdminNotificationDeliveryController` | retry |
| Safety reports | `/admin/safety/reports` | `AdminSafetyReportController` | triage/transition + evidence download |
| Privacy | `/admin/privacy` | `AdminPrivacyRequestController` | exports + deletions + hold |
| Legal | `/admin/legal-documents` | `AdminLegalDocumentController` | versioning |
| Analytics | `/admin/analytics` | `AdminAnalyticsController` + `AdminAnalyticsExportController` | Marketplace health (see §22) + CSV export (formula-injection protected) |

**Analytics health (pilot):** supply/ demand/ liquidity/ connection quality/ trust/ retention/ revenue/ operations — see Business Overview § “Marketplace Metrics”. North-star widget built on `MarketplaceDailyMetric`.

---

## 22. What NOT to Build (Guardrails + Roadmap)

**Never in pilot:** dispatch, GPS tracking, proof-of-delivery, cargo-payment escrow, settlement, internal chat/tender bidding, pallet/temperature/hazmat workflows, dimensions/equipment beyond current domain — unless evidence + business-case approved.

**Roadmap (evidence-led):**

| Horizon | Objective | Priority |
|---------|-----------|----------|
| **H1 — Pilot readiness** | Dependable + understandable product | Manual UI review (desktop+mobile), prod config, MySQL validation, Chapa test-mode end-to-end, notification reliability, legal review, support playbooks |
| **H2 — Controlled launch** | Repeat matches in selected corridors | Seed 50+ verified transporters, recruit 20+ consignors, moderation ops, KPI reporting, feedback, pricing experiments |
| **H3 — Evidence-led expansion** | Scale what users value | Additional corridors, refined matching, stronger business accounts, operational integrations, *carefully selected* value-added services |

**Guardrail:** Schedule next horizon only after prior horizon metrics prove value.

---

## 23. Rebuild Strategy — Why Fresh, Not Port

Per `NumanFreight_Rebuild_Analysis.md` — **Gadgetza** (CodeCanyon retail/coupons PHP, no framework) was audited vs NumanFreight Laravel:

- **Do NOT port Gadgetza domain code.** Insecure (SQLi, XSS, auth bypass, SHA-256 passwords, public uploads, mass assignment, payment manipulation) and wrong domain (deals/stores/coupons/ads/taxes/reviews/favorites). Almost zero reusable freight logic.
- **Only portable value:** Ethiopian location seed structure, truck/cargo reference *shape* (re-seed in Amharic/English), file-upload *validation patterns* (port to Laravel Storage), `Str::slug()` helper.
- **Target is clean Laravel** — current migrations/models/domains are the correct freight model. Archive Gadgetza; complete & harden Laravel implementation against the phased plan (Phase 0 done → Phase 1 marketplace loop → … → Phase 5 hardening).

Use the **container-first / package-first rule** for any UI refresh:

- Reuse: Inertia React, Tailwind, shadcn/ui, React Leaflet/Leaflet, existing Ethiopian search + OSRM endpoints, existing draft/save services.
- Do NOT create a second map/UI/form/calendar/combobox framework without a proven gap.
- Reference UX `ecab-taxi-booking-manager` **only as inspiration** (stepper, route-first, large OSM map, immediate distance/time) — never port its WordPress/jQuery/taxi pricing/vehicle selection code.

---

## 24. Fresh Repository Bootstrap — Step by Step

**Goal:** new empty repo → working NumanFreight with full journey.

### 24.1 Start from correct starter (do NOT use Gadgetza)

```bash
composer create-project laravel/laravel numanfreight-fresh
cd numanfreight-fresh

# React starter kit (Inertia + React + TypeScript per §9)
php artisan starter-kit:install react --no-interaction
# or: laravel new numanfreight-fresh --using=react (Laravel installer)

composer require laravel/fortify laravel/pennant spatie/laravel-permission \
  spatie/laravel-health spatie/laravel-backup artesaos/seotools \
  irazasyed/telegram-bot-sdk:^3.16 laravel/wayfinder

npm install leaflet react-leaflet @types/leaflet
npm install zustand zod recharts @tanstack/react-table react-hook-form @hookform/resolvers \
  date-fns react-day-picker lucide-react class-variance-authority clsx tailwind-merge

php artisan fortify:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan wayfinder:generate
```

Keep `package.json` + `composer.json` from this guide’s §9 as reference — versions in current repo are the pin.

### 24.2 Environment

```bash
cp .env.example .env
php artisan key:generate
# Edit .env — see docs/operations/environment-variables.md + production-environment.md
# DB: sqlite locally (database/database.sqlite), mysql in production
# CHAPA_SECRET_KEY, CHAPA_WEBHOOK_SECRET, TELEGRAM_BOT_TOKEN, OSRM_URL,
# TILE_URL/TILE_ATTRIBUTION, MAIL_*, APP_URL
touch database/database.sqlite  # if sqlite
```

### 24.3 Migrations & seeders (order matters)

```bash
php artisan migrate --force

# Seed in this order:
php artisan db:seed --class=RolePermissionSeeder     # roles + permissions
php artisan db:seed --class=LocationSeeder           # Ethiopia - Major Towns.json
php artisan db:seed --class=CargoTypeSeeder
php artisan db:seed --class=TruckTypeSeeder
php artisan db:seed --class=MarketplacePlanSeeder    # Free/Pro/Business + prices + entitlements
php artisan db:seed --class=MarketplaceLegalDocumentSeeder
php artisan db:seed --class=DemoDataSeeder           # optional, for local QA
# See database/seeders/* + docs/data-sources/ethiopia-major-towns.md
```

> **Critical:** Do NOT try to migrate Gadgetza `deals/stores/codes/ads` tables. Rebuild Analysis §3 lists exact Gadgetza→NumanFreight mapping — only reference-data shapes are portable; everything else is deleted conceptually.

### 24.4 App scaffolding (rebuild in this order — aligns with Phase plan)

1. **Foundation:** `MarketplaceProfile`, `OnboardingController`, `onboarded` middleware, `routes/web.php` onboarding group, `resources/js/pages/onboarding/index.tsx`.
2. **Reference data:** `Location`, `CargoType`, `TruckType` models + admin CRUD (`Admin*Controller` + `resources/js/pages/admin/reference-data/*`) + `LocationSearchController`.
3. **Transporter profiles:** `TransporterProfile` + `TransporterDocument` + `TransporterRoute` + moderation + privacy flags + `TransporterProfileController` + `PublicTransporterDirectoryController` + policies.
4. **Freight:** `FreightPost` + `FreightPostRoute` + `FreightPostModerationLog` + `RoutePreviewController` (OSRM) + `Freight*Controller` wizard (route→cargo→schedule→review) + `resources/js/pages/freight/*` + `resources/js/components/freight/*` + `resources/js/components/maps/*`.
5. **Interest→Select→Connect:** `FreightInterest` + status history, `MarketplaceConnection` + outcomes + audit logs, their controllers + policies + notifications.
6. **Plans/Entitlements:** `MarketplacePlan` family + `MarketplaceEntitlementService` + `MarketplaceUsageEvent` + admin plan/subs controllers + `resources/js/pages/pricing.tsx` + `resources/js/pages/admin/plans/*`.
7. **Billing:** `MarketplacePaymentTransaction` + `MarketplacePaymentReceipt` + `Chapa*Controller` + webhooks + invoices (`resources/js/pages/billing/*` + `resources/js/pages/admin/payments/*`).
8. **Notifications/Telegram:** notification classes (`app/Notifications`), custom `TelegramChannel`, `UserNotificationController`, `TelegramWebhookController`, `resources/js/pages/notifications/*` + `resources/js/pages/settings/*`.
9. **Safety/Privacy/Legal:** `MarketplaceReport` + `PrivacyCenterController` + `LegalDocumentController` + admin safety/privacy/legal pages.
10. **Admin/analytics:** `routes/admin.php` + all `Admin*Controller` + `resources/js/pages/admin/*` + `MarketplaceDailyMetric` + export protection.

Copy the **file map** from §29 when scaffolding — keep controller/route/policy names identical so Wayfinder output stays compatible.

### 24.5 Frontend bootstrap

```bash
npm install
npm run build        # must produce public/build/manifest.json + public/build/assets/*
# Do NOT leave public/hot on disk — it makes @vite point to 5173 (un-styled HTML bug)
rm -f public/hot
php artisan wayfinder:generate --with-form
npm run types:check  # 0 errors required before shipping wizard
```

---

## 25. Development Workflow, Commands & Quality Gates

```bash
# Dev loop
composer run dev            # php artisan serve + vite (host 127.0.0.1 per vite.config.ts)
npm run dev                 # Vite HMR alone
php artisan tinker          # quick domain checks

# Quality gates (must pass before PR)
vendor/bin/pint --dirty --format agent   # or composer run lint
npm run lint:check && npm run format:check
npm run types:check          # tsc --noEmit
php artisan test --compact   # or php artisan test --compact --filter=testName
php artisan config:show app.name   # dot-notation config checks
php artisan route:list --except-vendor --name=freight

# After route changes
php artisan wayfinder:generate --with-form
npm run types:check

# After PHP changes
vendor/bin/pint --format agent
phpstan analyse --level 7

npm run build  # before any user-facing QA (keeps public/build in sync)
```

**Tailwind 4 note:** `resources/css/app.css` must contain `@source '../js';` so React `.tsx` classes are generated. `resources/js/app.tsx` must `import '../css/app.css';`. Missing either = unstyled/plain-HTML bug (plain blue links, no layout).

**Common pitfall — `public/hot`:** Vite dev creates `public/hot` pointing to `http://[::1]:5173`. If you run `php artisan serve` on `127.0.0.1:8000` with only `public/hot` present and no `npm run dev`, CSS 404s → unstyled. Fix: `rm public/hot` and `npm run build`, or run `npm run dev` alongside serve with `server.host: '127.0.0.1'` in `vite.config.ts`.

---

## 26. Seed Accounts & Local URLs

**Password for ALL demo accounts:** `Password123!` (hashed via Fortify).

| Role | Email | Use |
|------|-------|-----|
| System Administrator | `admin@numanfreight.com` | `/admin` |
| Moderator | `moderator@numanfreight.com` | `/admin/freight`, `/admin/transporters`, `/admin/safety/reports` |
| Consignor (shipper) | `shipper.ethiopia@numanfreight.com` | Post freight, review interests |
| Consignor (Adama) | `shipper.adama@numanfreight.com` | Second consignor for discovery tests |
| Verified Transporter (Express) | `transporter.express@numanfreight.com` | Directory, express interest |
| Verified Transporter (Heavy) | `transporter.heavy@numanfreight.com` | Heavy-truck freight tests |
| Pending Transporter | `transporter.pending@numanfreight.com` | Moderation queue |

**Local URLs:**

```
http://127.0.0.1:8000/                      welcome
http://127.0.0.1:8000/login                login
http://127.0.0.1:8000/register             register
http://127.0.0.1:8000/onboarding           onboarding gate
http://127.0.0.1:8000/dashboard            role-aware dashboard
http://127.0.0.1:8000/my-freight           my freight posts
http://127.0.0.1:8000/my-freight/{ulid}/route   wizard step 1
http://127.0.0.1:8000/freight               public discovery
http://127.0.0.1:8000/transporters         transporter directory
http://127.0.0.1:8000/pricing              plans
http://127.0.0.1:8000/billing/*            invoices & payments
http://127.0.0.1:8000/admin                admin shell
```

Verify login greedily: `php artisan tinker --execute 'echo \Illuminate\Support\Facades\Hash::check("Password123!", \App\Models\User::where("email","admin@numanfreight.com")->first()->password) ? "YES":"NO";'`

---

## 27. Testing Strategy

| Layer | Tool | What to cover |
|-------|------|---------------|
| Unit | PHPUnit 12 | Entitlement resolution, verification workflow, route preview service |
| Feature | PHPUnit | Full loop: Post → Interest → Select → Connect + 3 negative cases per transition; onboarding guard; policy gates; Chapa webhook idempotency |
| Browser | (optional) Dusk | HMR wizard, contact reveal, Chapa checkout redirect |
| Contract | — | Chapa webhook payload, Telegram webhook |
| Static | Larastan level 7 | All domain code |
| Security | `composer audit`, `npm audit` | In CI |

Run minimum before finalizing: `php artisan test --compact tests/Feature/FreightPostTest.php` (or affected file). Full suite: `php artisan test --compact`.

> Guideline: every change must be programmatically tested — new/updated test + affected tests green. Phase 0 repo already has focused feature tests; extend them.

---

## 28. Deployment & Pilot Launch Checklist

**Pre-deploy (per `docs/operations/*` playbooks):**

- [ ] Manual UI approval: public, auth, marketplace, admin at desktop + mobile (check unstyled-HMLT bug)
- [ ] `npm run build` committed, `public/hot` removed
- [ ] `php artisan test --compact` + `phpstan` level 7 + `pint` + `npm run types:check` clean
- [ ] MySQL/MariaDB migration dry-run (pilot does NOT use SQLite in prod)
- [ ] Queue + scheduler + `storage/app/private` + email (DKIM/SPF/DMARC) verified
- [ ] Chapa test-mode E2E: init → callback → webhook → verify → fulfillment idempotency → invoice → subscription
- [ ] Telegram bot token + webhook URL set, dedup + retry proven
- [ ] Legal docs published (Amharic + English), disclaimer final
- [ ] `.env` audited (no secrets in repo)

**Pilot operations (H2):**

1. Seed 50+ verified transporters in 3 launch corridors (by truck category).
2. Activate them (profile completion, Telegram, alerts).
3. Recruit consignors with recurring needs, guide high-quality posting.
4. Concentrate liquidity corridor-by-corridor.
5. Operate manually where needed (moderation, support, imports).
6. Measure daily: supply/demand/liquidity/connection quality/trust/retention/revenue/operations + north-star.

**Incident playbooks:** `docs/operations/backup-restore-drill.md`, `notification-failure-playbook.md`, `payment-incident-playbook.md`, `safety-report-playbook.md`, `production-rollback.md`, `pilot-rollout-checklist.md`.

---

## 29. Appendix — File Map, Routes & FAQ

### 29.1 File map (keep names stable for Wayfinder)

```
app/Models/*                    # ~40 marketplace models (see §11.1)
app/Http/Controllers/*
  Freight/*                     # Post/Route/Cargo/Schedule/Review + Interests
  Billing/*                     # Checkout/Chapa/Receipt
  Admin/*                       # 13 admin controllers
  Onboarding/OnboardingController
  Safety/ReportController, Privacy/PrivacyCenterController, Legal/LegalDocumentController
resources/js/pages/*
  freight/{index,show,route,cargo,schedule,review,interests,my-interests,pricing}
  onboarding/index, discover/index, freight-discovery/index
  transporter-profile/index, transporters/index, connections/{index,show}
  billing/{payment,payments,receipt}, admin/**, settings/*, auth/*
resources/js/components/*
  freight/{freight-wizard-shell,freight-wizard-footer}
  maps/{location-search-input,route-preview-map,location-map-picker}
  ui/*                          # shadcn primitives
resources/js/layouts/{app-layout,auth-layout}
routes/{web.php,admin.php}      # web = public+onboarded, admin = permission-gated
config/{fortify,pennant,permission,health,backup}
database/migrations/2026_08_04_* # marketplace domains
database/seeders/*               # roles, locations, cargo/truck, plans, legal, demo
docs/operations/*                # 13 runbooks
docs/NUMANFREIGHT_PROJECT_RULES.md  # Phase 0 guardrails (read before coding)
```

### 29.2 Route highlights

```
Public:     GET /  GET /freight  GET /transporters  GET /pricing
Auth:       /login /register /forgot-password /verify-email /two-factor-challenge
Onboarding: /onboarding  POST /onboarding/{capabilities,account-type,details}
Marketplace: /dashboard  /discover  /my-freight  /my-freight/{ulid}/{route,cargo,schedule,review}
            POST /freight/routes/preview  POST /my-freight/{ulid}/interests
            /connections  /transporters
Admin:      /admin  /admin/freight  /admin/transporters  /admin/plans  /admin/payments  /admin/analytics
Webhooks:   POST /webhooks/chapa  POST /webhooks/telegram  POST /freight/routes/preview
```

### 29.3 Wayfinder usage

```ts
// Instead of hardcoded URLs:
import { store as freightStore } from '@/actions/App/Http/Controllers/Freight/FreightPostController';
import { index as freightIndex } from '@/routes/freight-discovery';

// Typed helpers generated from Laravel routes:
freightStore.form()  // form helper
freightIndex.url()    // url helper
```

Run `php artisan wayfinder:generate --with-form` after any route change.

### 29.4 FAQ

**Q: Do I need Gadgetza code?**
A: No. Archive it. Port nothing except (optionally) the *shape* of location seed data.

**Q: Can consignor phone be shown publicly?**
A: Never. Controlled reveal only after `FreightInterest` accepted → `MarketplaceConnection`.

**Q: Does buying Pro make a transporter verified?**
A: Never. Present them separately. Verification = moderation review outcome.

**Q: Where does authoritative distance come from?**
A: `RoutePreviewController` (OSRM). Browser only previews.

**Q: Why is my UI unstyled?**
A: Check `resources/css/app.css` has `@source '../js';`, `resources/js/app.tsx` imports CSS, `public/hot` handling (§25), and `npm run build` was run.

**Q: Which branch has the latest production ops work?**
A: `feature/production-operations-pilot-deployment` — verify tip before branching `feature/freight-post-creation-ui` or other feature branches.

---

## Document Basis

Built from:
- `NumanFreight_Business_and_Product_Overview.md` (July 2026 — business source of truth)
- `NumanFreight_Rebuild_Analysis.md` (Gadgetza vs Laravel audit — security + reuse matrix)
- `docs/NUMANFREIGHT_PROJECT_RULES.md` (stack + one-account + authorization guardrails)
- `routes/web.php` + `routes/admin.php` + `app/Models/*` + `resources/js/pages/*` (current code)
- `package.json` + `composer.json` (installed versions pinned)
- Past incident notes (`public/hot` + Tailwind @source fixes)

> Update this doc when business decisions, prices, legal terms, or production validation change. Product status here describes the **current pilot implementation** — keep code as the primary source of truth, in the order listed above.

