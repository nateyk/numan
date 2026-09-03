# Step-by-Step Remaining Work — Final (Wayfinder + Map Preview)

## Steps completed in this batch (by recommendation)
1. **Wayfinder duplicate fix** — `routes/settings.php:90` changed {connectedAccount} -> {provider} to match controller + web.php; removed duplicate web.php:238-240; regenerated with `--with-form` and quoted "2faRequired" in admin/workspace index.ts:266/178 → `npm run types` clean (0 errors vs previous 12+)
2. **Map preview alignment** — `routes/web.php:308` Route::match get+post for /freight/routes/preview; `LazyLeafletRoute.tsx` now POSTs JSON snake_case with CSRF token; fallback to GET; build passes, 50 freight tests pass
3. **Wayfinder guard** — `package.json` adds `wayfinder` script (`php artisan wayfinder:generate --with-form && sed ... 2faRequired`) and `wayfinder:check` (types) to prevent regression; rebuild verified

## Verification
- Types: clean (0 errors)
- Build: ✓ built (LazyLeafletRoute chunk, app chunk)
- Pint: passed
- Targeted freight/connection/interest/marketplace/admin: 50 pass (153 assertions) + 23 policy/notification: all pass
- Full suite: 2 pre-existing failures remain (OnboardingTest:109 redirect, PageComponentCoverageTest:35 wizard/index vs onboarding/index) — documented, not caused by this work; previously also 4, now 2 after wayfinder fix

## Next incremental steps (ready)
- Dashboard wizard entry verification (already added /my-freight quickAction)
- OSRM server proxy hardening if needed (current client POST works)
- Full suite if CI requires 0 failures (needs fixing OnboardingTest expectation and PageCoverage component mapping)
