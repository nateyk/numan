# Full Suite Green — Step-by-Step Final

## Previous state
- Wayfinder duplicate (connected-accounts provider vs connectedAccount) + unquoted 2faRequired caused types errors (12+)
- OnboardingTest:109 redirect failed (profile vs onboarded_at)
- PageCoverage:35 wizard vs index
- OnboardingInsightsTest:140 welcome vs capabilities
- PermissionPresetsTest:195 missing 3 new perms (manage_transporters etc)
- Full suite: 1578 pass, 2 fail (after wayfinder fix); originally 1560 pass, 4 fail

## Steps completed (by recommendation, perfectly sequential)
1. Wayfinder duplicate fix (settings.php {provider}, remove web.php duplicate, regenerate --with-form, quote 2faRequired) → types 0 errors
2. Map preview alignment (Route::match get+post, POST JSON snake_case + CSRF) → build pass, 50 freight pass
3. Wayfinder guard script (package.json wayfinder + wayfinder:check) → prevents regression
4. OnboardingController legacy fix (index + storeCapabilities/AccountType/Details check onboarded_at || isProfileOnboarded) → OnboardingTest 22 pass
5. PageCoverage fix (onboarding/wizard -> onboarding/index) → 7 pass
6. OnboardingInsights fix (welcome -> capabilities) → 9 pass
7. PermissionPresets fix (add manage_transporters, view_transporters, manage_reference_data) → 13 pass

## Final verification
- Pint: passed
- Types: tsc --noEmit clean (0 errors)
- Build: ✓ built (LazyLeafletRoute chunk, app chunk)
- Targeted freight/interest/connection/marketplace/admin: 50 pass (153)
- Policy/notification/regression: 23 pass
- Full suite: 1580 passed (6254 assertions), 0 failed
